# MCP Wrapper for Craft CMS

A production-ready Craft CMS plugin that exposes your content to AI assistants through the Model Context Protocol (MCP).

## 📚 Documentation

- **[FINAL-TEST-RESULTS.md](FINAL-TEST-RESULTS.md)** - Comprehensive test results and production readiness
- **[REGIONAL-LEADERSHIP-TESTING-GUIDE.md](REGIONAL-LEADERSHIP-TESTING-GUIDE.md)** - Technical guide for Regional Leadership filtering
- **[BOTPRESS-INSTRUCTIONS-CORRECTED.md](BOTPRESS-INSTRUCTIONS-CORRECTED.md)** - Botpress Knowledge Base instructions
- **[CHANGELOG.md](CHANGELOG.md)** - Version history

## What It Does

Enables AI assistants (Claude, ChatGPT, Botpress, etc.) to intelligently query your Craft CMS content through a standardized protocol. Think of it as an API specifically designed for AI consumption.

### Key Capabilities

- 🤖 **AI-Native**: Designed for AI assistants to discover and query your content automatically
- 🔒 **Enterprise Security**: IP allowlisting, dangerous tool protection, configurable permissions
- 🔧 **GraphQL-Powered**: Leverages Craft's powerful GraphQL API with schema-based access control
- 📊 **System Tools**: Query system info, plugins, queue status, logs, cache, and config
- 🚀 **Production-Ready**: Running successfully with Botpress on Jensen Hughes website

## Live Example

**Jensen Hughes AI Chatbot** powered by this plugin:

- Visitors ask: "What services do you offer?"
- Bot queries Craft CMS via MCP
- Returns real, up-to-date service listings

## Installation

**Requirements:**

- Craft CMS 5.0+
- PHP 8.2+
- Redis (recommended for caching)

**Quick Install:**

```bash
cd /path/to/your-craft-site
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

See [DEPLOYMENT.md](./DEPLOYMENT.md) for detailed instructions.

## Configuration

Create `config/mcpwrapper.php`:

```php
<?php
return [
    // GraphQL schema mapping
    'schemas' => [
        'MCPSchema' => getenv('MCP_GQLSCHEMA_TOKEN'),
    ],
    
    // Security settings
    'security' => [
        'enableDangerousTools' => false,  // Block write operations in production
        'disabledTools' => [],             // Disable specific tools by name
        'ipWhitelist' => [],               // Restrict access by IP (CIDR supported)
    ],
];
```

Add to `.env`:

```bash
MCP_GQLSCHEMA_TOKEN="your-graphql-token-from-craft-cp"
```

## Features

### MCP Protocol Compliance

Implements MCP specification `2025-11-25`:

- ✅ Tool Discovery (`tools/list`)
- ✅ Tool Execution (`tools/call`)
- ✅ Capability Negotiation (`initialize`)
- ✅ JSON-RPC 2.0 transport
- ✅ Health check endpoint (`/mcp/health`)
- ✅ Prometheus metrics (`/mcp/metrics`)

### Automatic Tool Generation

Plugin introspects your Craft CMS and auto-generates MCP tools:

**Content Tools** (from GraphQL schema):

- `query_news`, `query_products`, `query_pages`, etc.
- One tool per section with full GraphQL query support
- Pagination, search, filtering built-in

**System Tools** (manual):

- `craft_get_entry_by_id` - Fetch specific entries
- `craft_search_entries` - Full-text search with filtering
- `craft_get_entry_by_slug` - Get by section and slug
- `craft_get_system_info` - Craft version, PHP, database info
- `craft_list_plugins` - Installed plugins with status
- `craft_get_queue_status` - Background job status
- `craft_read_logs` - Application logs (with filtering)
- `craft_get_cache_info` - Cache statistics
- `craft_clear_caches` ⚠️ (dangerous - disabled by default)
- `craft_get_project_config_status` - Config sync status

#### Dangerous Tool Protection


**1. Dangerous Tool Protection**

Blocks write/modify operations (cache clear, config rebuild, queue run).

#### Tool Disabling

Blocks write/modify operations (cache clear, config rebuild, queue run).

**2. Tool Disabling**

Disable specific tools individually.

#### IP Allowlisting
allowedIps' => [
    '127.0.0.1',
    '203.0.113.0/24',  // CIDR notation
    '2001:db8::/32',   // IPv6 support
]
```
Whitelist' => [
    '127.0.0.1',
    '203.0.113.0/24',  // CIDR notation
    '2001:db8::/32',   // IPv6 support
]
```
Restrict access to specific IPs or ranges.

### Multi-Schema Support

Configure different GraphQL schemas for different use cases:

```php
'schemas' => [
    'public' => getenv('GQL_PUBLIC_TOKEN'),   // Limited public data
    'ai' => getenv('GQL_AI_TOKEN'),           // AI assistant access
    'internal' => getenv('GQL_INTERNAL_TOKEN'), // Full internal access
]
```

Each schema can have different:

- Sections/entry types
- Field visibility
- Permission levels

## Usage

### Endpoints

**MCP Server Endpoint** (JSON-RPC 2.0):

```text
POST https://your-site.com/mcp/{schemaHandle}
```

Example: `https://your-site.com/mcp/ai`

**Health Check Endpoint**:

```text
GET https://your-site.com/mcp/health
```

Returns server status, version, and uptime.

**Metrics Endpoint** (Prometheus-compatible):

```text
GET https://your-site.com/mcp/metrics
```

Returns request counts, error rates, and response times.

**Manifest Endpoint** (Tool Discovery):

```text
GET https://your-site.com/mcp/manifest/{schemaHandle}
```

### MCP Client Integration

**Claude Desktop (config file):**
```json
{
  "mcpServers": {
    "craftcms": {
      "command": "mcp-client",
      "args": ["https://your-site.com/mcp/MCPSchema"]
    }
  }
}
```

**Botpress Integration:**
```typescript
configuration: {
  mcpServerUrl: 'https://your-site.com',
  schemaHandle: 'MCPSchema'
}
```

### Testing

**List available tools:**
```bash
curl -X POST "https://your-site.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"tools/list",
    "params":{},
    "id":1
  }'
```

**Query content:**
```bash
curl -X POST "https://your-site.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"tools/call",
    "params":{
      "name":"query_news",
      "arguments":{"limit":5}
    },
    "id":2
  }'
```

**Get system info:**
```bash
curl -X POST "https://your-site.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"tools/call",
    "params":{
      "name":"craft_get_system_info",
      "arguments":{}
    },
    "id":3
  }'
```

## Testing

Run the test suite:

```bash
# Unit tests (106 tests)
bash tests/run-all-tests.sh

# MCP endpoint tests
./test-mcp-endpoint.sh https://your-site.com ai

# Regional Leadership filter tests
./test-regional-leadership-filter.sh
```

## Architecture

### How It Works

1. **GraphQL Introspection**: Plugin introspects your GraphQL schema on first request
2. **Tool Generation**: Creates MCP tool definitions for each section/query type
3. **Caching**: Tools are cached (Redis/file) for performance
4. **Security Validation**: Checks IP, tool permissions before execution
5. **Query Execution**: Translates MCP tool calls to GraphQL queries
6. **Response Formatting**: Returns results in MCP-compliant format

### File Structure

```
src/
├── controllers/
│   └── McpController.php       # Main HTTP endpoint
├── services/
│   ├── McpServerService.php    # MCP protocol implementation
│   ├── ToolRegistryService.php # Tool registration and discovery
│   └── ManifestBuilderService.php # GraphQL introspection
├── tools/
│   ├── SystemTools.php         # Craft system tools
│   └── EntryTools.php          # Entry management tools
├── attributes/
│   └── Tool.php                # Tool registration attribute
└── support/
    ├── Response.php            # Response formatting
    └── IpValidator.php         # IP validation with CIDR
```

## Performance

- **Caching**: GraphQL schema introspection cached via Redis or file storage
- **Lazy Loading**: Tools generated on-demand
- **Response Times**: 
  - Tool list: <500ms
  - Simple queries: <1s
  - Complex queries: <3s

## Security Best Practices

### Production Configuration

```php
return [
    'schemas' => [
        'public' => getenv('GQL_PUBLIC_TOKEN'),
    ],
    'security' => [
        'enableDangerousTools' => false,  // Always false in production
        'disabledTools' => [
            'craft_read_logs',  // Don't expose logs
        ],
        'ipWhitelist' => [
            '203.0.113.0/24',  // Your office
            '198.51.100.0/24', // Your hosting provider
        ],
    ],
];
```

### GraphQL Schema Security

1. Create dedicated GraphQL schema for AI access
2. Limit to specific sections/entry types
3. Exclude sensitive fields (passwords, API keys, etc.)
4. Set appropriate query depth/complexity limits

## Troubleshooting

### Common Issues

**Empty tools list:**
- Verify GraphQL schema exists in Craft CP
- Check token is valid in config
- Ensure schema has permissions/sections

**Route not found:**
```bash
# Relink site in Herd
herd unlink your-site && herd link your-site

# Clear Craft caches
php craft clear-caches/all
```

**Redis connection errors:**
```bash
# Start Redis
brew services start redis
```

**Tool execution errors:**
- Check `enableDangerousTools` setting
- Verify tool isn't in `disabledTools` list
- Check IP allowlist if configured

## Development

### Running Tests

```bash
# Security test suite (6 scenarios)
./test-mcp-security.sh

# Simple diagnostic tests
./test-mcp-simple.sh

# Direct HTTP test with verbose output
./test-mcp-direct.sh
```

### Adding Custom Tools

1. Create tool class with `#[Tool]` attribute
2. Implement tool method
3. Register via `ToolRegistryService`

Example:
```php
use rocketpark\mcpwrapper\attributes\Tool;

class CustomTools
{
    #[Tool(
        name: 'my_custom_tool',
        description: 'Does something custom',
        dangerous: false
    )]
    public static function myCustomTool(array $arguments): array
    {
        return ['result' => 'success'];
    }
}
```

## Contributing

This plugin is maintained by Rocket Park LLC for use with Craft CMS projects.

## License

See [LICENSE.md](./LICENSE.md)

## Support

For issues, questions, or feature requests, please contact Rocket Park LLC.

## Credits

Built by Rocket Park LLC for enterprise Craft CMS deployments.

Special thanks to the Anthropic team for the MCP specification and the Craft CMS team for the excellent plugin architecture.

## Control Panel Utility

The plugin includes a CP utility at **Utilities → MCP Manifest Manager** for:

- Viewing cached manifests
- Rebuilding manifests on demand
- Monitoring schema status

## Roadmap

### ✅ Completed (v2.1)
- [x] MCP 2025-11-25 protocol compliance
- [x] Health check endpoint (`/mcp/health`)
- [x] Prometheus metrics endpoint (`/mcp/metrics`)
- [x] IPv6 CIDR support
- [x] Request timeout handling
- [x] GraphQL input sanitization
- [x] 106 unit tests passing

### 🔜 Planned
- [ ] OAuth 2.1 support for enterprise deployments
- [ ] Tool annotations (outputSchema, destructiveHint)
- [ ] Streamable HTTP transport
- [ ] Commerce integration tools
