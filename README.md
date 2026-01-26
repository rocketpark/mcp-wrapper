# MCP Wrapper for Craft CMS

A production-ready Craft CMS plugin that exposes your content to AI assistants through the Model Context Protocol (MCP).

## 📚 Documentation

- **[BOTPRESS-TESTING-SIMPLE.md](BOTPRESS-TESTING-SIMPLE.md)** - Quick 20-minute testing guide for Botpress
- **[BOTPRESS-INSTRUCTIONS-UPDATED.md](BOTPRESS-INSTRUCTIONS-UPDATED.md)** - Complete instructions for Botpress Knowledge Base
- **[FINAL-TEST-RESULTS.md](FINAL-TEST-RESULTS.md)** - Comprehensive test results (76% pass rate, production ready)
- **[BOTPRESS-TEST-CHECKLIST.md](BOTPRESS-TEST-CHECKLIST.md)** - Detailed testing checklist
- **[QUICK-START-TESTING.md](QUICK-START-TESTING.md)** - 30-minute comprehensive testing workflow
- **[REGIONAL-LEADERSHIP-TESTING-GUIDE.md](REGIONAL-LEADERSHIP-TESTING-GUIDE.md)** - Technical guide for Regional Leadership filtering

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

Implements MCP specification `2025-06-18`:

- ✅ Tool Discovery (`tools/list`)
- ✅ Tool Execution (`tools/call`)
- ✅ Capability Negotiation (`initialize`)
- ✅ JSON-RPC 2.0 transport

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

Example: `https://your-site.com/mcp/MCPSchema`

**Manifest Endpoint** (Tool Discovery):

```text
GET https://your-site.com/mcp/manifest/{schemaHandle}
```

Example: `https://your-site.com/mcp/manifest/MCPSchema`

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

See [TESTING.md](./TESTING.md) for comprehensive testing guide.

## Documentation

- **[QUICK-START.md](./QUICK-START.md)** - Quick start guide for developers
- **[DEPLOYMENT.md](./DEPLOYMENT.md)** - Deployment and configuration instructions
- **[TESTING.md](./TESTING.md)** - Complete testing guide
- **[FIELD-PRIVACY-GUIDE.md](./FIELD-PRIVACY-GUIDE.md)** - Security and privacy best practices
- **[BOTPRESS-BOT-INSTRUCTIONS-V2.md](./BOTPRESS-BOT-INSTRUCTIONS-V2.md)** - Botpress integration guide
- **[CHANGELOG.md](./CHANGELOG.md)** - Version history

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

See [FIELD-PRIVACY-GUIDE.md](./FIELD-PRIVACY-GUIDE.md) for detailed security recommendations.

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

See [TESTING.md](./TESTING.md) troubleshooting section for more details.

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

This is the modern MCP transport (spec 2025-06-18). Use this for:
- Claude Desktop (via mcp-remote)
- Custom MCP clients
- Most new integrations

#### 2. Server-Sent Events (SSE) - Legacy Compatibility

```text
GET https://your-site.com/actions/mcp-wrapper/mcp/sse/{schemaHandle}
```

This is the deprecated HTTP+SSE transport for compatibility with clients that require SSE:
- Airia platform
- Older MCP implementations
- Clients expecting event streams

**Note:** SSE transport opens a persistent connection and streams MCP events. The connection automatically sends the `initialize` and `tools/list` responses on connection.

### Example: Tool Discovery

```bash

curl -X POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
  }'
```

**Response:**

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "query_news",
        "description": "Query News entries from Craft CMS",
        "inputSchema": {
          "type": "object",
          "properties": {
            "limit": {"type": "integer", "default": 10},
            "search": {"type": "string"},
            "id": {"type": "array"}
          }
        }
      }
    ]
  }
}
```

### Example: Query Content

```bash
curl -X POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "query_news",
      "arguments": {"limit": 5, "search": "launch"}
    }
  }'
```

## Connecting AI Clients

### Claude Desktop

Add to your MCP config:

```json
{
  "mcpServers": {
    "craft-cms": {
      "url": "https://your-site.com/actions/mcp-wrapper/mcp/index/ai"
    }
  }
}
```

Then ask Claude: *"Show me the latest news articles about product launches"*

Claude will automatically:

1. Discover the `query_news` tool
2. Call it with appropriate search parameters
3. Display the results to you

## Control Panel Utility

The plugin includes a CP utility at **Utilities → MCP Manifest Manager** for:

- Viewing cached manifests
- Rebuilding manifests on demand
- Monitoring schema status

*Note: This utility shows the legacy manifest format. The primary MCP server uses JSON-RPC dynamically.*

## Use Cases

- **AI-Powered Content Discovery**: Let Claude/ChatGPT search your Craft content
- **Natural Language Queries**: "Find blog posts about sustainability from 2024"
- **Cross-Site Integration**: Connect multiple Craft sites to one AI assistant
- **Custom AI Workflows**: Build agents that can read and reason about your CMS data

## Development

**Requirements:**

- PHP 8.2+
- Craft CMS 5.0+
- GraphQL schemas configured in Craft

**Local Development:**

```bash
git clone https://github.com/rocketpark/mcp-wrapper.git
cd mcp-wrapper
git checkout -b feature/my-improvements
```

See [DEPLOYMENT.md](./DEPLOYMENT.md) for Forge deployment workflow.

## Architecture

```text
MCP Client (Claude, ChatGPT, etc.)
    ↓ JSON-RPC 2.0
McpController (/actions/mcp-wrapper/mcp/index/{schema})
    ↓
McpServerService (handles initialize, tools/list, tools/call)
    ↓
GraphQL API (/api) with bearer token
    ↓
Craft CMS Content
```

## Roadmap

### ✅ Completed (v2.0)
- [x] Security: IP allowlisting with CIDR support
- [x] Security: Dangerous tools protection
- [x] MCP Prompts (3 prompts: schema_explorer, content_health, query_builder)
- [x] MCP Resources (5 resources: schema, sections, entries, volumes)
- [x] System Tools (7 admin tools: info, plugins, queue, logs, cache, project config)
- [x] Tool registry architecture with attributes
- [x] Safe execution wrapper for error handling

### 🔜 Planned (v2.1+)
- [ ] Completion providers (section handles, field handles, etc.)
- [ ] Installation wizard (`php craft mcp-wrapper/install`)
- [ ] Client config generator (Claude Desktop, Cursor, Cline)
- [ ] Extension API events for plugins
- [ ] Hot reload support
- [ ] Multi-site specific tools
- [ ] Commerce integration tools

## Available Tools

### Dynamic Tools (GraphQL)
Auto-generated from your Craft sections (10-20 tools):
- `query_{section}` - Query entries from any section
- Supports pagination, search, filtering
- Respects GraphQL schema permissions

### Manual Tools - Content (2 tools)
- `craft_get_entry_by_id` - Get full entry data bypassing GraphQL permissions
- `craft_search_entries` - Advanced entry search with filters

### Manual Tools - System (7 tools)
- `craft_get_system_info` - Craft/PHP/DB version, plugins, server info
- `craft_list_plugins` - List installed plugins with status
- `craft_get_queue_status` - View queue jobs (waiting, failed)
- `craft_read_logs` - Read recent log entries
- `craft_get_cache_info` - Cache configuration
- `craft_clear_caches` ⚠️ - Clear data/template/compiled/transforms
- `craft_get_project_config_status` - Check for pending changes

### MCP Prompts (3 prompts)
- `schema_explorer` - Explore Craft content model with AI guidance
- `content_health` - Analyze content freshness and maintenance needs
- `query_builder` - Help build GraphQL queries for sections

### MCP Resources (5 resources)
- `craft://{schema}/schema` - Complete GraphQL SDL
- `craft://{schema}/sections` - List all sections
- `craft://{schema}/sections/{handle}` - Section field layout
- `craft://{schema}/entries/{section}` - Recent 50 entries
- `craft://{schema}/volumes` - Asset volumes

## Security Configuration

```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
    ],
    
    'security' => [
        // IP whitelist (supports CIDR notation)
        'allowedIps' => [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',  // Private network
            // getenv('ADMIN_IP'),
        ],
        
        // Require authentication
        'requireAuth' => true,
        
        // Enable dangerous operations (mutations, deletions)
        'enableDangerousTools' => getenv('CRAFT_ENVIRONMENT') === 'dev',
        
        // Disabled tools (won't appear in manifest)
        'disabledTools' => [
            // 'craft_clear_caches',
        ],
    ],
];
```

## License

See [LICENSE.md](./LICENSE.md)
