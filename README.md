# MCP Wrapper for Craft CMS

## Overview

MCP Wrapper is a Craft CMS plugin that implements the **Model Context Protocol (MCP)** specification, exposing your Craft CMS content as **MCP Tools** that AI assistants (like Claude Desktop, ChatGPT, and other MCP clients) can discover and query via GraphQL.

### What is MCP?

The Model Context Protocol is an open standard that enables AI applications to securely connect to external data sources and tools. Think of it as "USB-C for AI" - one standardized interface that works across different AI assistants.

### What This Plugin Does

1. **Exposes MCP Tools**: Each Craft section becomes an MCP tool (e.g., `query_news`, `query_products`)
2. **JSON-RPC 2.0 Server**: Implements the official MCP protocol for tool discovery and execution
3. **GraphQL Integration**: Tools execute queries against your Craft GraphQL API
4. **Multi-Schema Support**: Configure different GraphQL schemas for different use cases (AI, public, internal)

## Key Features

### MCP Protocol Compliance

Implements MCP specification `2025-06-18`:

- **Tool Discovery** (`tools/list`): AI clients can discover available Craft content types
- **Tool Execution** (`tools/call`): AI clients can query your Craft content
- **Capability Negotiation** (`initialize`): Proper MCP handshake and version negotiation

### Automatic Tool Generation

The plugin automatically generates MCP tools by introspecting your Craft CMS setup:

- One tool per section (News, Products, Pages, etc.)
- Proper JSON Schema for tool parameters
- Filters by GraphQL schema permissions

### Secure Multi-Schema Architecture

Configure different GraphQL tokens for different purposes:

```php
'schemas' => [
    'ai' => getenv('GQL_AI_TOKEN'),      // For AI assistants (limited data)
    'public' => getenv('GQL_PUBLIC_TOKEN'), // For public integrations
    'internal' => getenv('GQL_INTERNAL_TOKEN'), // For internal tools
]
```

### GraphQL-Powered Queries

When AI tools are called, they execute real GraphQL queries with:

- Limit/offset pagination
- Full-text search
- ID filtering
- Access control via GraphQL schema permissions

## Installation

See [DEPLOYMENT.md](./DEPLOYMENT.md) for detailed installation instructions.

**Quick Install:**

```bash

cd /path/to/your-craft-site
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

## Configuration

Create `config/mcpwrapper.php`:

```php
<?php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
    ],
];
```

Add to `.env`:

```bash
GQL_AI_TOKEN="your-graphql-token-from-craft-cp"
```

## Usage

### MCP Endpoints

The plugin provides two transport endpoints:

#### 1. HTTP POST (Streamable HTTP) - Recommended

```text
POST https://your-site.com/actions/mcp-wrapper/mcp/index/{schemaHandle}
```

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
