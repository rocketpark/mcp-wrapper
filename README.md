# MCP Wrapper for Craft CMS

A production-ready Craft CMS plugin that exposes your content to AI assistants through the Model Context Protocol (MCP).

## 📚 Documentation

- **[FINAL-TEST-RESULTS.md](FINAL-TEST-RESULTS.md)** - Comprehensive test results and production readiness
- **[REGIONAL-LEADERSHIP-TESTING-GUIDE.md](REGIONAL-LEADERSHIP-TESTING-GUIDE.md)** - Technical guide for Regional Leadership filtering
- **[BOTPRESS-INSTRUCTIONS-CORRECTED.md](BOTPRESS-INSTRUCTIONS-CORRECTED.md)** - Botpress Knowledge Base instructions
- **[CHANGELOG.md](CHANGELOG.md)** - Version history

## Architecture

```mermaid
flowchart TB
    Client[AI Client<br/>Claude/Botpress/ChatGPT]
    
    subgraph "MCP Wrapper Plugin"
        Endpoint["/mcp/{schemaHandle}<br/>JSON-RPC 2.0 Endpoint"]
        McpServer[McpServerService<br/>Request Handler]
        ToolRegistry[ToolRegistryService<br/>Tool Orchestrator]
        
        subgraph "Tool Sources"
            ManifestBuilder[ManifestBuilderService<br/>Auto-generates from Sections]
            ManualTools[Manual Tools<br/>#[Tool] Attribute Classes]
        end
        
        subgraph "Craft CMS Integration"
            GraphQL[GraphQL Schema<br/>Permission Filtering]
            ProjectConfig[Project Config YAML<br/>schema scope: sections.{uid}:read]
            Sections[Craft Sections<br/>News, Products, Pages, etc.]
        end
        
        SafeExec[SafeExecution Wrapper<br/>Error Handling]
        Cache[File Cache<br/>@storage/runtime/mcp/]
    end
    
    Client -->|POST JSON-RPC| Endpoint
    Endpoint --> McpServer
    
    McpServer -->|tools/list| ToolRegistry
    McpServer -->|tools/call| ToolRegistry
    
    ToolRegistry --> ManifestBuilder
    ToolRegistry --> ManualTools
    
    ManifestBuilder -->|Read Schema Scope| ProjectConfig
    ManifestBuilder -->|Query Allowed Sections| GraphQL
    GraphQL -->|Filter by UID| Sections
    
    ManualTools --> SafeExec
    ManifestBuilder --> SafeExec
    
    SafeExec -->|Execute GraphQL| GraphQL
    
    ToolRegistry -.->|Cache Manifest| Cache
    
    McpServer -->|JSON Response| Client
    
    style Client fill:#e1f5ff
    style McpServer fill:#fff4e1
    style ToolRegistry fill:#ffe1f5
    style ManifestBuilder fill:#e1ffe1
    style GraphQL fill:#f5e1ff
    style SafeExec fill:#ffe1e1
    
    classDef security fill:#ffcccc,stroke:#cc0000,stroke-width:2px
    class ProjectConfig,GraphQL security
```

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

**Security Features:**

**1. Rate Limiting**

Prevents abuse by limiting requests per IP:

```php
'security' => [
    'enableRateLimit' => true,
    'rateLimit' => 100,          // Max requests
    'rateLimitWindow' => 60,     // Per 60 seconds
]
```

**2. Tool Result Caching** ✨ NEW

Caches query results to reduce database load:

```php
'toolCacheTTL' => 300,  // Cache for 5 minutes (0 = disabled)
'toolCacheExclude' => [
    'craft_get_system_info',  // Real-time data shouldn't be cached
    'craft_get_queue_status',
]
```

**Impact:** 60-80% performance improvement for duplicate queries

**3. Request Analytics** ✨ NEW

Structured logging for debugging and usage analysis:

- Logs to `storage/logs/mcp-requests.log`
- Tracks tool usage, response times, error rates
- Privacy-safe (anonymized IPs, hashed arguments)

```php
// View analytics
$logger = Craft::$app->getModule('mcp-wrapper')->get('requestLogger');
$stats = $logger->getAnalytics(7); // Last 7 days
```

**4. Dangerous Tool Protection**

Blocks write/modify operations (cache clear, config rebuild, queue run).

```php
'enableDangerousTools' => false,  // Disable in production
```

**5. Tool Disabling**

Disable specific tools individually:

```php
'disabledTools' => [
    'craft_read_logs',
    'craft_clear_caches'
]
```

**6. IP Allowlisting**

Restrict access to specific IPs or ranges:

```php
'allowedIps' => [
    '127.0.0.1',
    '203.0.113.0/24',  // CIDR notation
    '2001:db8::/32',   // IPv6 support
]
```

**7. Webhook Support** ✨ NEW

Notify external systems when content changes:

- Fires on entry save/delete events
- Configurable filters (sections, statuses, events)
- HMAC SHA-256 signatures for security
- Async delivery via queue (doesn't block entry saves)
- Test via console: `php craft mcp-wrapper/webhook/test`

```php
'webhooks' => [
    [
        'url' => 'https://your-app.com/webhooks/mcp',
        'secret' => getenv('MCP_WEBHOOK_SECRET'),
        'events' => ['entry.saved', 'entry.deleted'],
        'sections' => ['news', 'blog'],
    ]
]
```

**8. Performance Dashboard** ✨ NEW

Visual analytics in Craft CP:\n- **Real-time Metrics**: Total requests, success rate, avg response time, cache hit rate
- **Tool Usage**: Most-used tools with error rates and duration
- **Performance Insights**: Slowest requests, optimization opportunities
- **Error Tracking**: Recent errors with timestamps
- **CSV Export**: Download analytics data
- **Multi-Schema**: Filter by GraphQL schema

Access via **Utilities → MCP Analytics** or `/admin/mcp-wrapper/analytics`.

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

The plugin includes a comprehensive PHPUnit test suite covering core functionality:

```bash
# Run all unit tests (33 tests, 72 assertions)
vendor/bin/phpunit tests/Unit --colors=always

# Or use the convenience script
./tests/run-unit-tests.sh

# Run specific test class
./tests/run-unit-tests.sh --filter ToolCacheServiceTest

# Generate coverage report
./tests/run-unit-tests.sh --coverage
# Opens tests/coverage/index.html
```

### Test Coverage

**Unit Tests (tests/Unit/):**
- ToolCacheServiceTest.php - 8 tests (cache key generation, argument normalization)
- RequestLoggerServiceTest.php - 10 tests (privacy, IP anonymization, arg hashing)
- ToolAttributeTest.php - 8 tests (attribute discovery, enhanced annotations)
- WebhookServiceTest.php - 7 tests (event/section/status filtering, payload structure)

**Integration Tests (tests/):**
- test-argument-mapping.php - Argument extraction from GraphQL schema
- test-graphql-sanitization.php - SQL injection prevention
- test-ip-validator.php - IPv4/IPv6 CIDR validation
- test-tool-registry.php - Tool discovery and registration

### Running MCP Endpoint Tests

```bash
# Test MCP endpoint
./test-mcp-endpoint.sh https://your-site.com ai

# Test Regional Leadership filters
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

### ✅ Completed (v2.7 - February 2026)
- [x] **JSON Schema for Auto-Generated Tools** - Complete inputSchema and outputSchema for all 18 query_* tools
- [x] **Enhanced Type Mapping** - Intelligent Craft field to JSON Schema conversion
- [x] **Better AI Understanding** - Detailed parameter descriptions, defaults, and constraints

### ✅ Completed (v2.6 - February 2026)
- [x] **Performance Dashboard** - Visual analytics in Craft CP with real-time metrics
- [x] **CSV Export** - Download analytics data for external analysis
- [x] **Multi-Schema Support** - Filter dashboard by GraphQL schema

### ✅ Completed (v2.5 - February 2026)
- [x] **Webhook Support** - HTTP POST notifications for content changes
- [x] **Webhook Filtering** - Event, section, and status filters
- [x] **HMAC Signatures** - Secure webhook delivery with SHA-256 signatures
- [x] **Console Commands** - Test webhook delivery from CLI

### ✅ Completed (v2.4 - February 2026)
- [x] **Comprehensive Unit Test Suite** - 33 tests covering core services and attributes

### ✅ Completed (v2.3 - February 2026)
- [x] **Output Schemas** - JSON schema definitions for all manual tools
- [x] **Enhanced Tool Annotations** - costHint, confidentialityHint, destructiveHint

### ✅ Completed (v2.2 - February 2026)
- [x] **Tool Result Caching** - 20-40% performance improvement for duplicate queries
- [x] **Request Analytics Logging** - Structured logging for debugging and usage analysis
- [x] **Connection Pooling** - Reusable GraphQL clients for 10-20% faster queries
- [x] **Config Example File** - mcpwrapper.php.example for easier setup

### ✅ Completed (v2.1)
- [x] MCP 2025-11-25 protocol compliance
- [x] Health check endpoint (`/mcp/health`)
- [x] Prometheus metrics endpoint (`/mcp/metrics`)
- [x] IPv6 CIDR support
- [x] Request timeout handling
- [x] GraphQL input sanitization
- [x] Rate limiting (100 req/60s default)
- [x] IP allowlisting (IPv4/IPv6 CIDR)
- [x] SSE streaming transport
- [x] Prompts registry (schema_explorer, content_health, query_builder)

### 🔜 Planned
- [ ] Performance dashboard for analytics visualization
- [ ] OAuth 2.1 support for enterprise deployments
- [ ] Auto-generated tool improvements (output schemas for GraphQL tools)
