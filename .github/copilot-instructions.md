# MCP Wrapper - Craft CMS Plugin

## Project Overview

This plugin generates MCP (Model Context Protocol) manifests for Craft CMS GraphQL APIs by introspecting GraphQL schemas and mapping Craft field types to MCP-compatible metadata. It enables AI/LLM tools to discover and interact with Craft CMS content.

**Core Architecture:**
- `ManifestBuilderService`: Introspects GraphQL schemas, generates manifests with field/relationship metadata
- `ManifestController`: Public API endpoint at `/actions/mcpwrapper/manifest/{schemaHandle}`
- `UtilityController`: Admin-only CP utility for viewing/rebuilding manifests
- File-based caching in `@storage/runtime/mcp/manifest-{schemaHandle}.json`

## Configuration Pattern

Schema-to-token mapping in `config.php`:

```php
'schemas' => [
    'ai' => getenv('GQL_AI_TOKEN'),
    'frontend' => getenv('GQL_FRONTEND_TOKEN'),
]
```

Each schema handle maps to a GraphQL bearer token. Manifests are accessible at `/actions/mcpwrapper/manifest/{schemaHandle}` (e.g., `/actions/mcpwrapper/manifest/ai`).

## Key Components

### Manifest Generation Flow

1. **GraphQL Introspection** (`ManifestBuilderService::introspectGraphQL`): Uses Guzzle to query `/__schema` with bearer token
2. **Field Type Mapping**: PlainText→string, Lightswitch→boolean, Date→datetime, Entries/Assets/Categories/Users/Tags→relation
3. **Relationship Metadata Extraction**: Parses source restrictions (e.g., `section:news`, `volume:images`) from relational fields
4. **Caching**: Writes JSON to `@storage/runtime/mcp/` directory

### Cache Invalidation

Auto-clears on these Craft events (see `Plugin.php`):
- `ProjectConfig::EVENT_REBUILD`
- `Gql::EVENT_AFTER_SAVE_SCHEMA`

## Common Tasks

### Testing Manifest Generation

```bash
# Via browser/curl with force rebuild
curl http://your-site.test/actions/mcpwrapper/manifest/ai?force=1

# Check cached manifest
cat storage/runtime/mcp/manifest-ai.json
```

### Adding New Field Type Mappings

Modify `ManifestBuilderService::mapFieldType()` match expression. Relationship fields (Entries, Assets, etc.) automatically extract source metadata via `extractSourceHandles()`.

### Debugging Schema Issues

The `introspectGraphQL()` method expects valid GraphQL tokens from `config.php`. Check:
1. Token is set in environment variables
2. GraphQL schema exists in Craft CP → GraphQL → Schemas
3. Token has proper permissions for the schema

## Craft CMS Specifics

- **PSR-4 Namespace**: `rocketpark\mcpwrapper` (defined in `composer.json`)
- **Plugin Handle**: `mcp-wrapper` (used in URL routes)
- **Plugin Class**: `rocketpark\mcpwrapper\McpWrapper` (bootstraps in `Plugin.php`)
- **Module Access**: `Craft::$app->getModule('mcpwrapper')->get('manifestBuilder')`

## Dependencies

- PHP 8.2+
- Craft CMS 5.0+
- Guzzle (for GraphQL introspection via HTTP)
