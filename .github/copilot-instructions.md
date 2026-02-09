# MCP Wrapper - Craft CMS Plugin

## Architecture Overview

This plugin implements the Model Context Protocol (MCP) for Craft CMS, exposing content via JSON-RPC 2.0. It auto-generates tools from Craft sections AND allows manual tool registration via PHP attributes.

**Data Flow:**
1. Client → `/mcp/{schemaHandle}` (JSON-RPC endpoint)
2. McpServerService → ToolRegistry → ManifestBuilderService
3. Filters tools by GraphQL schema permissions (section UIDs from project config)
4. Returns MCP-compliant tool list or executes tool calls

**Key Services:**
- `ManifestBuilderService`: Generates tools from Craft sections, filters by GraphQL schema scope
- `ToolRegistryService`: Combines auto-generated + manual tools (via #[Tool] attribute)
- `McpServerService`: JSON-RPC 2.0 handler (initialize, tools/list, tools/call)

## Configuration (config/mcpwrapper.php)

```php
'schemas' => [
    'MCPSchema' => getenv('MCP_GQLSCHEMA_TOKEN'),
],
'security' => [
    'enableDangerousTools' => false,  // Production safety
    'disabledTools' => [],
    'ipWhitelist' => [],
]
```

**CRITICAL**: Schema filtering reads GraphQL schema scope from `config/project/graphql/schemas/{uid}.yaml`. If schema shows wrong sections, run `php craft project-config/apply` to sync DB with project config.

## Tool Development Pattern

**Auto-generated tools** (from Craft sections):
- One tool per section: `query_{sectionHandle}`
- Filtering happens in `ManifestBuilderService::getAllowedSectionsForSchema()` which parses `sections.{uid}:read` from GraphQL schema scope
- See `getGeneratedTools()` for implementation

**Manual tools** (src/tools/):
```php
#[Tool(
    name: 'craft_get_entry_by_id',
    description: 'Get entry by ID bypassing GraphQL',
    inputSchema: ['type' => 'object', 'properties' => [...]],
    dangerous: false
)]
public function getEntryById(int $id): array {
    return SafeExecution::run(fn() => /* ... */);
}
```

Register in `McpWrapper::registerToolClasses()`:
```php
$toolRegistry->registerToolClass(EntryTools::class);
```

## Routing (src/McpWrapper.php)

```php
'mcp/<schemaHandle>' => 'mcp-wrapper/mcp/index'           // JSON-RPC endpoint
'mcp/manifest/<schemaHandle>' => 'mcp-wrapper/manifest/index'  // Tool discovery
```

**URLs:** `/mcp/MCPSchema` (NOT `/actions/mcp-wrapper/mcp/index?schemaHandle=X`)

## Debugging Common Issues

**Problem**: Production showing all 75 tools instead of filtered 18
**Root cause**: Fail-safe logic was "allow all" when GraphQL schema lookup failed. Production DB has empty schema scope while project config YAML has correct scope.
**Fix Applied (commit 2204514)**: Changed fail-safe from "allow all" to "allow none" (secure by default)
**Production Fix**: Run `php craft project-config/apply` to sync DB with YAML files

**Problem**: Schema filtering not working / showing 0 or wrong number of tools  
**Check**: `storage/logs/mcpwrapper.log` for messages:
- "Could not find GQL token" = Token not in DB
- "Schema has empty scope" = DB out of sync, run `php craft project-config/apply`
- "Skipping section" messages = Filtering working correctly

**Problem**: Composer not updating vendor code
**Fix**: Add `--no-cache` to composer install in deployment script

## Testing

```bash
# Local: List tools for MCPSchema
curl -X POST "http://site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'

# Production: Check tool count (should be ~18, not 75)
curl -s "https://site.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}' | jq '.result.tools | length'

# Force manifest rebuild
curl "https://site.com/mcp/manifest/MCPSchema?force=1"
```

## Project Conventions

- **Logging**: All info/warnings go to `storage/logs/mcpwrapper.log` (category: 'mcp-wrapper')
- **Error handling**: Wrap tool execution in `SafeExecution::run()` for consistent error responses
- **Config access**: `Craft::$app->getConfig()->getConfigFromFile('mcpwrapper')` NOT `Craft::$app->config`
- **Service access**: Via Craft module system: `Craft::$app->getModule('mcp-wrapper')->get('serviceName')`
- **Caching**: File-based at `@storage/runtime/mcp/manifest-{schema}.json`
