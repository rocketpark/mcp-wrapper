# MCP Wrapper - Deep Comparison & Implementation Analysis

**Date:** January 13, 2026  
**Comparing:** rocketpark/mcp-wrapper vs stimmtdigital/craft-mcp

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Architecture Deep Dive](#architecture-deep-dive)
3. [Protocol Implementation](#protocol-implementation)
4. [Tools & Capabilities](#tools--capabilities)
5. [MCP Features Matrix](#mcp-features-matrix)
6. [Security & Configuration](#security--configuration)
7. [Setup & Developer Experience](#setup--developer-experience)
8. [Extension & Extensibility](#extension--extensibility)
9. [Performance & Caching](#performance--caching)
10. [Implementation Recommendations](#implementation-recommendations)
11. [Prioritized Feature Roadmap](#prioritized-feature-roadmap)

---

## Executive Summary

### Our Implementation (rocketpark/mcp-wrapper)

**Philosophy:** GraphQL-first MCP server exposing Craft CMS content via HTTP transport with dynamic tool generation.

**Strengths:**
- ✅ HTTP/Web-based transport (unique approach)
- ✅ Multi-schema routing per URL path
- ✅ Direct GraphQL query execution
- ✅ SSE streaming support
- ✅ Simpler architecture (web-native)
- ✅ Dynamic tool generation from Craft structure
- ✅ Can leverage Craft's built-in security (`allowedIps`, `allowedGraphqlOrigins`)

**Gaps:**
- ❌ Missing MCP Prompts (guided workflows)
- ❌ Missing MCP Resources (URI-based data)
- ❌ Limited tool catalog (~10-20 vs 50+)
- ❌ No security features (IP filtering, dangerous tool protection)
- ❌ No extension API for other plugins
- ❌ Manual client setup
- ❌ No completion providers

### Their Implementation (stimmtdigital/craft-mcp)

**Philosophy:** Comprehensive MCP toolkit following standard stdio protocol with maximum feature coverage.

**Strengths:**
- ✅ Full MCP specification compliance
- ✅ 50+ pre-built tools across 11 categories
- ✅ MCP Prompts for guided analysis
- ✅ MCP Resources for read-only data access
- ✅ Robust security (IP filtering, dangerous tools, production defaults)
- ✅ Extension API (events for plugins)
- ✅ Installation wizard with auto-configuration
- ✅ Hot reload capability
- ✅ Dedicated logging
- ✅ Completion providers

**Trade-offs:**
- ⚠️ stdio-only (no web/HTTP access)
- ⚠️ Single-access model (no multi-schema routing)
- ⚠️ More complex setup (requires process execution)

---

## Architecture Deep Dive

### Transport Layer Comparison

#### Our HTTP/SSE Architecture

```
┌─────────────┐         HTTP POST          ┌──────────────────┐
│  AI Client  │ ──────────────────────────> │ Craft Web Server │
│  (Claude)   │ <────────────────────────── │   (Yii/PHP-FPM)  │
└─────────────┘    JSON-RPC Response        └──────────────────┘
                                                      │
                                                      ▼
                                          ┌──────────────────────┐
                                          │  ManifestController  │
                                          │   McpController      │
                                          └──────────────────────┘
                                                      │
                                                      ▼
                                          ┌──────────────────────┐
                                          │  McpServerService    │
                                          │  (JSON-RPC Handler)  │
                                          └──────────────────────┘
                                                      │
                                                      ▼
                                          ┌──────────────────────┐
                                          │   GraphQL API        │
                                          │   Craft CMS          │
                                          └──────────────────────┘
```

**Request Flow:**
1. AI client makes HTTP POST to `/actions/mcp-wrapper/mcp/{schema}`
2. McpController receives request, parses JSON-RPC
3. McpServerService dispatches to method handler (initialize, tools/list, tools/call)
4. Tool execution runs GraphQL query with bearer token
5. Results returned as JSON-RPC response over HTTP

**URLs:**
- Manifest: `GET /actions/mcp-wrapper/manifest/{schema}`
- MCP Server: `POST /actions/mcp-wrapper/mcp/{schema}`
- SSE Stream: `GET /actions/mcp-wrapper/mcp/sse/{schema}`

**Advantages:**
- ✅ RESTful/web-native (easy to test with curl/Postman)
- ✅ Works with web-based AI integrations
- ✅ Multi-schema routing in URL
- ✅ Familiar Craft controller pattern
- ✅ Can leverage Craft's auth/CSRF if needed
- ✅ Scalable (horizontal scaling, load balancing)

**Disadvantages:**
- ❌ Not standard MCP stdio approach
- ❌ Requires web server/PHP-FPM running
- ❌ HTTP overhead per request
- ❌ More attack surface (publicly accessible endpoints)

#### Their stdio Architecture

```
┌─────────────┐         stdio          ┌──────────────────┐
│  AI Client  │ ──────────────────────> │  PHP Process     │
│  (Claude)   │ <────────────────────── │  (mcp-server)    │
└─────────────┘    stdin/stdout         └──────────────────┘
                                                  │
                                                  ▼
                                      ┌──────────────────────┐
                                      │  McpServerFactory    │
                                      │  Server::builder()   │
                                      └──────────────────────┘
                                                  │
                                                  ▼
                                      ┌──────────────────────┐
                                      │  Tool Registry       │
                                      │  Prompt Registry     │
                                      │  Resource Registry   │
                                      └──────────────────────┘
                                                  │
                                                  ▼
                                      ┌──────────────────────┐
                                      │  50+ Tool Classes    │
                                      │  9 Prompt Classes    │
                                      │  12 Resource Classes │
                                      └──────────────────────┘
```

**Request Flow:**
1. AI client launches: `php vendor/stimmt/craft-mcp/bin/mcp-server`
2. PHP process starts, loads Craft CMS
3. MCP SDK Server listens on stdin/stdout
4. JSON-RPC messages exchanged via stdio
5. Tools execute Craft API calls directly
6. Results returned via stdout

**Command:**
```bash
# Direct execution
php vendor/stimmt/craft-mcp/bin/mcp-server

# With DDEV
ddev exec php vendor/stimmt/craft-mcp/bin/mcp-server
```

**Advantages:**
- ✅ Standard MCP protocol (official stdio transport)
- ✅ Direct process communication (no HTTP overhead)
- ✅ Better security (no web exposure)
- ✅ Long-lived process (better performance)
- ✅ Signal handling (SIGHUP for reload)
- ✅ Works offline

**Disadvantages:**
- ❌ Only accessible to local/SSH clients
- ❌ No web-based integrations
- ❌ Process management complexity
- ❌ Harder to test (need MCP client)

### Code Architecture Comparison

#### Our Structure

```
src/
├── McpWrapper.php                 # Main plugin class
├── controllers/
│   ├── ManifestController.php    # GET /manifest/{schema}
│   ├── McpController.php          # POST /mcp/{schema}
│   └── UtilityController.php      # CP utility
├── services/
│   ├── ManifestBuilderService.php # Introspects GraphQL, builds tools
│   └── McpServerService.php       # JSON-RPC 2.0 handler
└── utilities/
    └── McpManifestUtility.php     # Control Panel utility
```

**Key Classes:**

**ManifestBuilderService:**
- Introspects GraphQL schemas
- Generates tool definitions per section
- Maps Craft fields to JSON Schema types
- Caches manifests to `@storage/runtime/mcp/`

**McpServerService:**
- Implements JSON-RPC 2.0 protocol
- Handles: initialize, tools/list, tools/call, ping
- Builds tool input schemas with Craft query params
- Executes GraphQL queries via HTTP

**Tool Generation Logic:**
```php
// One tool per section
$tools = [];
foreach ($sections as $section) {
    $tools[] = [
        'name' => "query_{$section->handle}",
        'description' => "Query {$section->name} entries",
        'inputSchema' => [...],  // Filters, limit, etc.
    ];
}
```

#### Their Structure

```
src/
├── Mcp.php                        # Main plugin class
├── bin/
│   └── mcp-server                 # Executable entry point
├── tools/
│   ├── AssetTools.php             # 3 asset tools
│   ├── CraftTools.php             # 4 schema tools
│   ├── ContentTools.php           # 10 content tools
│   ├── DatabaseTools.php          # 4 database tools
│   ├── DebugTools.php             # 7 debugging tools
│   ├── GraphqlTools.php           # 4 GraphQL tools
│   ├── McpTools.php               # 3 self-awareness tools
│   ├── MultisiteTools.php         # 3 multisite tools
│   └── SystemTools.php            # 7 system tools
├── prompts/
│   ├── ContentPrompts.php         # 3 content analysis prompts
│   ├── EntryPrompts.php           # 3 entry management prompts
│   └── SchemaPrompts.php          # 3 schema exploration prompts
├── resources/
│   ├── ConfigResources.php        # 5 config resources
│   ├── EntryResources.php         # 3 content resources
│   └── SchemaResources.php        # 4 schema resources
├── services/
│   ├── McpServerFactory.php       # Builds MCP Server
│   ├── ToolRegistry.php           # Manages tools
│   ├── PromptRegistry.php         # Manages prompts
│   └── ResourceRegistry.php       # Manages resources
└── support/
    ├── SafeExecution.php          # Error handling wrapper
    ├── FileLogger.php             # Dedicated MCP log
    └── SignalHandler.php          # Process signals (SIGHUP)
```

**Key Patterns:**

**Attribute-Based Discovery:**
```php
#[McpTool(
    name: 'list_entries',
    description: 'Query entries with filtering',
)]
#[McpToolMeta(category: ToolCategory::CONTENT)]
public function listEntries(/* params */): array {
    // Implementation
}
```

**Registry Pattern:**
- ToolRegistry discovers and manages all tools
- Uses PHP attributes for metadata
- Supports external plugin registration via events
- Tracks tool categories, dangerous status, sources

**Safe Execution Wrapper:**
```php
return SafeExecution::run(function() {
    // Tool logic
    return $result;
});
```
Converts all exceptions to proper MCP error responses.

---

## Protocol Implementation

### MCP Specification Coverage

The Model Context Protocol defines these core capabilities:

| Capability | Our Plugin | Their Plugin |
|------------|------------|--------------|
| **Tools** | ✅ Partial | ✅ Full |
| **Prompts** | ❌ No | ✅ Yes (9) |
| **Resources** | ❌ No | ✅ Yes (12) |
| **Completion** | ❌ No | ✅ Yes (7 providers) |
| **Logging** | ❌ No | ✅ Yes |
| **Roots** | ❌ No | ❌ No |
| **Sampling** | ❌ No | ❌ No |

### JSON-RPC 2.0 Implementation

#### Our Implementation

**Methods Supported:**
```javascript
// Initialize handshake
{
  "jsonrpc": "2.0",
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-06-18",
    "capabilities": {}
  },
  "id": 1
}

// List available tools
{
  "jsonrpc": "2.0",
  "method": "tools/list",
  "params": {
    "schemaHandle": "ai"  // Injected by controller
  },
  "id": 2
}

// Call a tool
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "query_news",
    "arguments": {
      "limit": 10,
      "filters": {"status": "live"}
    }
  },
  "id": 3
}

// Ping
{
  "jsonrpc": "2.0",
  "method": "ping",
  "params": {},
  "id": 4
}
```

**Response Format:**
```javascript
// Success
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Query results..."
      }
    ]
  }
}

// Error
{
  "jsonrpc": "2.0",
  "id": 3,
  "error": {
    "code": -32603,
    "message": "Internal error: ..."
  }
}
```

**Implementation:**
```php
public function handleRequest(array $jsonRpcRequest): array {
    $method = $jsonRpcRequest['method'] ?? null;
    $params = $jsonRpcRequest['params'] ?? [];
    $id = $jsonRpcRequest['id'] ?? null;

    try {
        $result = $this->dispatchMethod($method, $params);
        return $this->successResponse($id, $result);
    } catch (\Exception $e) {
        return $this->errorResponse($id, $e);
    }
}
```

#### Their Implementation

Uses the official MCP PHP SDK (`mcp-php/server`) which handles:
- Full JSON-RPC 2.0 protocol
- stdio transport with proper framing
- Error handling and validation
- Progress notifications
- Cancellation support

**Builder Pattern:**
```php
$server = Server::builder()
    ->setServerInfo(
        name: 'Craft CMS MCP Server',
        version: '1.0.0'
    )
    ->setInstructions($instructions)
    ->setDiscovery(
        basePath: dirname(__DIR__),
        scanDirs: ['tools', 'prompts', 'resources']
    )
    ->setContainer($container)
    ->setLogger($logger)
    ->build();

$transport = new StdioTransport();
$server->run($transport);
```

**Advantages:**
- Handles protocol complexity automatically
- Progress updates: `$context->getClientGateway()->progress(1, 10, 'Processing...')`
- Proper error codes and messages
- Request cancellation
- Logging integration

---

## Tools & Capabilities

### Our Dynamic Tool Generation

**Approach:** Introspect Craft sections and generate one tool per section dynamically.

**Example Generated Tools:**
- `query_news` - Query news section entries
- `query_products` - Query products section entries
- `query_team` - Query team section entries
- `query_pages` - Query pages section entries

**Tool Definition:**
```php
[
    'name' => 'query_news',
    'description' => 'Query news entries (schema ai) with relationships.',
    'endpoint' => '/api',
    'method' => 'POST',
    'parameters' => [
        'query' => 'query { entries(section: "news") { title slug body } }',
        'variables' => [
            'section' => 'news',
            'limit' => 'integer',
            'filters' => [
                'status' => ['eq' => 'string'],
                'author' => ['eq' => 'integer'],
                // ... field-specific filters
            ]
        ]
    ],
    'returns' => [
        'entries' => [
            ['handle' => 'title', 'type' => 'string'],
            ['handle' => 'slug', 'type' => 'string'],
            ['handle' => 'body', 'type' => 'string'],
            ['handle' => 'featuredImage', 'type' => 'relation', 'relationTo' => ['elementType' => 'asset']],
        ]
    ]
]
```

**Field Type Mapping:**
```php
private function mapFieldType(FieldInterface $field): string {
    $type = (new \ReflectionClass($field))->getShortName();
    
    return match ($type) {
        'PlainText' => 'string',
        'Lightswitch' => 'boolean',
        'Date' => 'datetime',
        'Number' => 'number',
        'Dropdown', 'RadioButtons' => 'enum',
        'Entries', 'Assets', 'Categories', 'Users', 'Tags' => 'relation',
        default => 'string',
    };
}
```

**Relationship Metadata:**
```php
if ($field instanceof Entries) {
    $base['relationTo'] = [
        'elementType' => 'entry',
        'sources' => $this->extractSourceHandles($field->sources)
    ];
}
// Extracts: ['section:news', 'section:blog'] => ['news', 'blog']
```

**Input Schema (Generated):**
```javascript
{
  "type": "object",
  "properties": {
    "id": {"type": "array", "items": {"type": "integer"}},
    "slug": {"type": "array", "items": {"type": "string"}},
    "status": {"type": "array", "items": {"type": "string"}},
    "limit": {"type": "integer"},
    "offset": {"type": "integer"},
    "search": {"type": "string"},
    "orderBy": {"type": "string"}
  }
}
```

**Strengths:**
- ✅ Automatically adapts to site structure
- ✅ Respects GraphQL schema permissions
- ✅ Field-level metadata (types, relationships)
- ✅ Clean, predictable tool naming

**Limitations:**
- ❌ Only content querying (no create/update)
- ❌ No system/admin tools
- ❌ No database tools
- ❌ Limited to what GraphQL exposes

### Their Comprehensive Tool Catalog

**Approach:** 50+ pre-built, hand-crafted tools organized by category.

#### Content Tools (10 tools)

| Tool | Purpose | Dangerous |
|------|---------|-----------|
| `list_entries` | Query entries with rich filtering | No |
| `get_entry` | Get single entry by ID/slug | No |
| `create_entry` | Create new entry | ✅ Yes |
| `update_entry` | Update existing entry | ✅ Yes |
| `list_globals` | List global sets | No |
| `list_categories` | List categories with hierarchy | No |
| `list_users` | List users with filtering | No |
| `list_assets` | List assets with filtering | No |
| `get_asset` | Get single asset details | No |
| `list_asset_folders` | List folder hierarchy | No |

**Example: list_entries**
```php
#[McpTool(
    name: 'list_entries',
    description: 'Query entries with filtering by section, status, author, and limit',
)]
#[McpToolMeta(category: ToolCategory::CONTENT)]
public function listEntries(
    ?array $section = null,
    ?array $type = null,
    ?array $status = null,
    ?int $authorId = null,
    ?string $search = null,
    int $limit = 20,
    int $offset = 0,
    ?RequestContext $context = null
): array {
    return SafeExecution::run(function() use ($section, $type, $status, $authorId, $search, $limit, $offset): array {
        $query = Entry::find()
            ->limit($limit)
            ->offset($offset);
        
        if ($section) $query->section($section);
        if ($type) $query->type($type);
        if ($status) $query->status($status);
        if ($authorId) $query->authorId($authorId);
        if ($search) $query->search($search);
        
        $entries = $query->all();
        
        return [
            'count' => count($entries),
            'total' => $query->count(),
            'entries' => array_map([$this, 'serializeEntry'], $entries),
        ];
    });
}
```

#### System Tools (7 tools)

| Tool | Purpose |
|------|---------|
| `get_system_info` | Craft version, PHP version, environment |
| `get_config` | Read config values by dot notation |
| `read_logs` | Search/filter log files |
| `get_last_error` | Get most recent error |
| `clear_caches` | Clear specific or all caches |
| `list_routes` | List registered routes |
| `list_console_commands` | List CLI commands |

**Example: read_logs**
```php
#[McpTool(
    name: 'read_logs',
    description: 'Read and filter Craft CMS log files with optional pattern matching',
)]
public function readLogs(
    int $limit = 50,
    string $level = 'error',
    ?string $source = null,
    ?string $pattern = null,
    string $output = 'structured',
    ?RequestContext $context = null
): array | TextContent {
    // Supports:
    // - Level filtering (error, warning, info, debug)
    // - Source filtering (web, console, queue, plugin-name)
    // - Pattern search (case-insensitive)
    // - Output format (structured array or colored text)
    // - Progress notifications while parsing
}
```

#### Database Tools (4 tools)

| Tool | Purpose | Dangerous |
|------|---------|-----------|
| `get_database_info` | Connection details, version | No |
| `get_database_schema` | Full schema with tables/columns | No |
| `get_table_counts` | Row counts for core tables | No |
| `run_query` | Execute SELECT queries | ✅ Yes |

**Example: get_database_schema**
```php
#[McpTool(name: 'get_database_schema', description: 'Get complete database schema')]
public function getDatabaseSchema(?RequestContext $context = null): array {
    return SafeExecution::run(function(): array {
        $tables = Craft::$app->db->schema->getTableSchemas();
        
        $schema = [];
        foreach ($tables as $table) {
            $schema[$table->name] = [
                'columns' => array_map(fn($col) => [
                    'name' => $col->name,
                    'type' => $col->type,
                    'size' => $col->size,
                    'allowNull' => $col->allowNull,
                    'isPrimaryKey' => $col->isPrimaryKey,
                ], $table->columns),
                'indexes' => $table->indexes,
                'foreignKeys' => $table->foreignKeys,
            ];
        }
        
        return ['tables' => $schema];
    });
}
```

#### Debugging Tools (7 tools)

| Tool | Purpose | Dangerous |
|------|---------|-----------|
| `get_queue_jobs` | Inspect queue jobs by status | No |
| `get_project_config_diff` | Show pending config changes | No |
| `get_deprecations` | List deprecation warnings | No |
| `explain_query` | Run EXPLAIN on queries | No |
| `get_environment` | Environment vars (safe subset) | No |
| `list_event_handlers` | Inspect registered events | No |
| `tinker` | Execute arbitrary PHP code | ✅ Yes |

**Example: tinker**
```php
#[McpTool(
    name: 'tinker',
    description: 'Execute PHP code within Craft application context',
)]
#[McpToolMeta(category: ToolCategory::DEBUGGING, dangerous: true)]
public function tinker(
    string $code,
    string $output = 'dump',
    ?RequestContext $context = null
): array {
    // Output modes: dump, json, raw, print_r
    // Security: No eval(), uses isolated closure
    // Returns: formatted output + execution time
}
```

#### Multi-Site Tools (3 tools)

| Tool | Purpose |
|------|---------|
| `list_sites` | List all sites with config | No |
| `get_site` | Get specific site by ID/handle | No |
| `list_site_groups` | List site groups | No |

#### GraphQL Tools (4 tools)

| Tool | Purpose | Dangerous |
|------|---------|-----------|
| `list_graphql_schemas` | List schemas with permissions | No |
| `get_graphql_schema` | Get schema SDL | No |
| `execute_graphql` | Run GraphQL queries/mutations | ✅ Yes |
| `list_graphql_tokens` | List API tokens | No |

#### Backup Tools (2 tools)

| Tool | Purpose | Dangerous |
|------|---------|-----------|
| `list_backups` | List available backups | No |
| `create_backup` | Create new database backup | ✅ Yes |

#### Self-Awareness Tools (3 tools)

| Tool | Purpose |
|------|---------|
| `get_mcp_info` | Plugin version, status, tool count |
| `list_mcp_tools` | List all tools with descriptions |
| `reload_mcp` | Hot reload for new plugins |

**Example: get_mcp_info**
```php
public function getMcpInfo(?RequestContext $context = null): array {
    $registry = Mcp::getToolRegistry();
    $summary = $registry->getSummary();
    
    return [
        'name' => 'Craft MCP',
        'version' => $plugin->version,
        'status' => [
            'enabled' => $settings->enabled,
            'dangerousToolsEnabled' => $settings->enableDangerousTools,
            'environment' => Craft::$app->env,
        ],
        'tools' => [
            'total' => $summary['total'],
            'bySource' => $summary['by_source'],
            'byCategory' => $summary['by_category'],
            'dangerous' => $summary['dangerous'],
        ],
        'configuration' => [
            'disabledTools' => $settings->disabledTools,
        ],
    ];
}
```

#### Commerce Tools (6 tools - when installed)

| Tool | Purpose |
|------|---------|
| `list_products` | List products with filtering |
| `get_product` | Get product with variants |
| `list_orders` | List orders with filtering |
| `get_order` | Get order details |
| `list_order_statuses` | List order statuses |
| `list_product_types` | List product types |

---

## MCP Features Matrix

### Prompts (Guided Workflows)

**What are MCP Prompts?**
Pre-built conversation starters that guide AI assistants through complex analysis tasks with structured data and specific instructions.

#### Their Implementation (9 Prompts)

**Content Analysis Prompts:**
1. **content_health_analysis**: Analyze content freshness, status distribution, workflow efficiency
2. **content_audit**: Comprehensive audit with field analysis, asset usage, SEO assessment
3. **debug_content_issue**: Diagnose content problems with system context

**Entry Management Prompts:**
4. **create_entry_guide**: Step-by-step entry creation with field validation
5. **query_entries_guide**: How to effectively query a specific section
6. **bulk_entry_operations**: Guidance for bulk operations

**Schema Exploration Prompts:**
7. **explore_section_schema**: Deep dive into section structure
8. **field_usage_analysis**: Analyze how a field is used across site
9. **explore_content_model**: Complete content architecture overview

**Example Prompt:**
```php
#[McpPrompt(
    name: 'content_health_analysis',
    description: 'Analyze content health including entry statistics and freshness',
)]
public function contentHealthAnalysis(): array {
    $sections = Craft::$app->getEntries()->getAllSections();
    
    $healthData = array_map(function($section) {
        return [
            'section' => $section->handle,
            'name' => $section->name,
            'live' => Entry::find()->section($section->handle)->status('live')->count(),
            'disabled' => Entry::find()->section($section->handle)->status('disabled')->count(),
            'pending' => Entry::find()->section($section->handle)->status('pending')->count(),
            'expired' => Entry::find()->section($section->handle)->status('expired')->count(),
            'drafts' => Entry::find()->section($section->handle)->drafts()->count(),
            'lastUpdated' => Entry::find()->section($section->handle)->orderBy('dateUpdated DESC')->one()?->dateUpdated?->format('Y-m-d'),
        ];
    }, $sections);
    
    return [
        [
            'role' => 'user',
            'content' => <<<PROMPT
Analyze this content health report for a Craft CMS installation:

```json
{$healthJson}
```

Please provide insights on:
1. Overall content health score and assessment
2. Sections that may need attention (high disabled/expired ratio, stale content)
3. Content freshness analysis
4. Workflow efficiency (draft vs published ratio)
5. Recommendations for content maintenance
PROMPT
        ]
    ];
}
```

**Our Status:** ❌ Not implemented

**Recommendation:** Implement 3-5 key prompts:
- `graphql_query_builder`: Help build GraphQL queries for sections
- `schema_explorer`: Explore content model and relationships
- `content_strategy`: Analyze content organization and suggest improvements

### Resources (URI-Based Data Access)

**What are MCP Resources?**
Read-only, URI-based data endpoints that AI assistants can reference without executing tools.

#### Their Implementation (12 Resources)

**Schema Resources (4):**
- `craft://schema/sections` - List all sections
- `craft://schema/fields` - List all fields
- `craft://schema/sections/{handle}` - Section details with entry types
- `craft://schema/fields/{handle}` - Field details with usage

**Config Resources (5):**
- `craft://config/general` - General config values (safe subset)
- `craft://config/routes` - Custom routes
- `craft://config/sites` - Site configurations
- `craft://config/volumes` - Asset volumes
- `craft://config/plugins` - Installed plugins

**Content Resources (3):**
- `craft://entries/{section}` - List entries in section (limit 50)
- `craft://entries/{section}/{slug}` - Single entry by slug
- `craft://entries/{section}/stats` - Entry statistics by status/type

**Example Resource:**
```php
#[McpResource(
    uri: 'craft://schema/sections',
    name: 'all-sections',
    description: 'List of all sections with basic metadata',
    mimeType: 'application/json',
)]
#[McpResourceMeta(category: ResourceCategory::SCHEMA)]
public function allSections(): array {
    $sections = Craft::$app->getEntries()->getAllSections();
    
    return [
        'sections' => array_map(fn($section) => [
            'handle' => $section->handle,
            'name' => $section->name,
            'type' => $section->type,
            'entryTypeCount' => count($section->getEntryTypes()),
        ], $sections),
    ];
}
```

**URI Template Resources:**
```php
#[McpResourceTemplate(
    uriTemplate: 'craft://schema/sections/{handle}',
    name: 'section-schema',
    description: 'Detailed schema for specific section',
    mimeType: 'application/json',
)]
public function sectionSchema(
    #[CompletionProvider(provider: SectionHandleProvider::class)]
    string $handle
): array {
    // Returns section + entry types + fields
}
```

**Our Status:** ❌ Not implemented

**Recommendation:** Implement key resources:
- `mcp://graphql/schemas` - List GraphQL schemas
- `mcp://graphql/schema/{handle}` - Schema details with SDL
- `mcp://sections/{handle}/fields` - Field layout for section
- `mcp://content/{section}/stats` - Content statistics

### Completion Providers

**What are Completion Providers?**
Auto-complete suggestions for tool/prompt/resource parameters.

#### Their Implementation (7 Providers)

1. **SectionHandleProvider**: Suggests section handles
2. **FieldHandleProvider**: Suggests field handles
3. **SiteHandleProvider**: Suggests site handles
4. **VolumeHandleProvider**: Suggests volume handles
5. **UserGroupHandleProvider**: Suggests user group handles
6. **EntryTypeHandleProvider**: Suggests entry type handles
7. **CategoryGroupHandleProvider**: Suggests category group handles

**Example:**
```php
#[McpTool(name: 'list_entries')]
public function listEntries(
    #[CompletionProvider(provider: SectionHandleProvider::class)]
    ?array $section = null,
    #[CompletionProvider(provider: EntryTypeHandleProvider::class)]
    ?array $type = null
): array {
    // When AI asks for section, provider suggests: ['news', 'blog', 'pages']
}
```

**Our Status:** ❌ Not implemented

**Recommendation:** Implement basic providers:
- Schema handle completion (from config)
- Section handle completion (from GraphQL)
- Field handle completion (from GraphQL)

---

## Security & Configuration

### Security Features Comparison

#### Our Security Model

**Current State:** ⚠️ **Minimal security - production-unsafe**

**What We Have:**
- ✅ Bearer token authentication (GraphQL tokens)
- ✅ GraphQL schema permissions (inherited from Craft)
- ✅ Craft's user authentication (if desired)
- ✅ GraphQL-level access control via `config/general.php`

**What We're Missing:**
- ❌ No IP allowlisting/filtering
- ❌ No dangerous operation protection
- ❌ No tool enable/disable controls
- ❌ No audit logging
- ❌ No rate limiting
- ❌ No request validation
- ❌ No safe execution wrappers
- ❌ Publicly accessible HTTP endpoints

**Vulnerability Analysis:**

1. **Public Web Exposure:**
   - `/actions/mcp-wrapper/mcp/{schema}` is publicly accessible
   - No authentication beyond GraphQL token
   - Susceptible to brute force/enumeration
   - **Mitigation:** Use `allowedIps` in `config/general.php` to restrict access
   - **Mitigation:** Use `allowedGraphqlOrigins` for web-based clients
   
2. **No Operation Restrictions:**
   - If GraphQL schema allows mutations, they're executable
   - No concept of "dangerous" vs "safe" tools
   - **Mitigation:** Configure GraphQL schemas to be read-only (queries only)
   - **Mitigation:** Use `allowAdminChanges: false` in production to prevent schema tampering
   
3. **No Audit Trail:**
   - No logging of who did what
   - Can't track AI assistant actions
   - **Partial Mitigation:** Craft's general request logs capture endpoint access
   
4. **Error Disclosure:**
   - Stack traces may leak system info
   - GraphQL errors expose schema structure
   - **Mitigation:** Set `devMode: false` in production

**Current Config:**
```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
        'frontend' => getenv('GQL_FRONTEND_TOKEN'),
    ],
];
```

**Additional GraphQL Security via Craft's General Config:**

Craft CMS provides GraphQL-level access control that can restrict MCP Wrapper's capabilities:

```php
// config/general.php
use craft\config\GeneralConfig;

return GeneralConfig::create()
    // Enable GraphQL (required for MCP Wrapper)
    ->enableGql(true)
    
    // Restrict GraphQL API access by IP
    ->allowedGraphqlOrigins([
        'https://app.botpress.cloud',
        'https://trusted-client.com',
    ])
    
    // Or restrict by IP address (server-side)
    ->allowedIps([
        '10.0.0.0/8',      // Internal network only
        '192.168.1.0/24',  // VPN subnet
    ])
    
    // Disable public GraphQL access entirely (force auth)
    ->requireMatchingUserAgentForCsrf(true)
    
    // Production hardening
    ->allowAdminChanges(false)  // Prevent schema changes in production
    ->devMode(false);           // Hide debug info
```

**Documentation:** [Craft CMS General Settings](https://craftcms.com/docs/5.x/reference/config/general.html#graphql)

**Key Settings for MCP Security:**

| Setting | Purpose | Impact on MCP |
|---------|---------|---------------|
| `enableGql` | Enable/disable GraphQL API | MCP requires this to be `true` |
| `allowedGraphqlOrigins` | CORS origins for GraphQL | Restricts web-based MCP clients by origin |
| `allowedIps` | IP allowlist for all requests | Restricts MCP endpoint access by IP |
| `allowAdminChanges` | Prevent CP changes in production | Protects schema from unauthorized modification |
| `devMode` | Enable debug output | Should be `false` in production to prevent info leakage |

**Limitations:**
- `allowedGraphqlOrigins` only works for browser-based CORS (doesn't affect server-to-server)
- `allowedIps` restricts all Craft traffic, not just GraphQL/MCP
- No per-schema IP restrictions (global only)
- No request rate limiting at Craft level

#### Their Security Model

**Philosophy:** Production-safe by default with explicit opt-in for dangerous operations.

**Security Features:**

**1. Dangerous Tool Classification**

Tools marked with `#[McpToolMeta(dangerous: true)]`:
- create_entry
- update_entry
- create_backup
- run_query
- execute_graphql
- tinker

**Default Behavior:**
- Dangerous tools hidden by default
- Require explicit `enableDangerousTools: true` in config
- Clear warnings in tool descriptions

**2. Tool Enable/Disable Controls**

```php
// config/mcp.php
return [
    'enabled' => true,
    
    // Require explicit opt-in
    'enableDangerousTools' => false,
    
    // Fine-grained control
    'disabledTools' => [
        'tinker',        // Never allow in production
        'run_query',     // Only enable when debugging
    ],
];
```

**3. Environment-Based Defaults**

```php
public function init(): void {
    // Production-safe defaults
    if (Craft::$app->env === 'production') {
        $this->enableDangerousTools = false;
    }
}
```

**4. IP Allowlisting**

```php
// config/mcp.php
return [
    'allowedIps' => [
        '127.0.0.1',           // Local only
        '10.0.0.0/8',          // Internal network
        '192.168.1.0/24',      // VPN subnet
    ],
];
```

**Implementation:**
```php
public function beforeAction($action): bool {
    if (!$this->checkIpAccess()) {
        throw new ForbiddenHttpException('Access denied from this IP');
    }
    return parent::beforeAction($action);
}

private function checkIpAccess(): bool {
    $settings = Mcp::getInstance()->getSettings();
    
    if (empty($settings->allowedIps)) {
        return true; // No restrictions
    }
    
    $clientIp = Craft::$app->getRequest()->getUserIP();
    
    foreach ($settings->allowedIps as $allowed) {
        if ($this->ipMatches($clientIp, $allowed)) {
            return true;
        }
    }
    
    return false;
}
```

**5. Safe Execution Wrapper**

```php
class SafeExecution {
    public static function run(callable $callback): mixed {
        try {
            $result = $callback();
            return $result;
            
        } catch (\yii\base\InvalidConfigException $e) {
            throw new McpException(
                message: 'Configuration error: ' . $e->getMessage(),
                code: McpErrorCode::INTERNAL_ERROR
            );
        } catch (\yii\db\Exception $e) {
            throw new McpException(
                message: 'Database error',
                code: McpErrorCode::INTERNAL_ERROR
            );
        } catch (\Exception $e) {
            Craft::error($e->getMessage(), 'mcp');
            throw new McpException(
                message: 'Tool execution failed',
                code: McpErrorCode::INTERNAL_ERROR
            );
        }
    }
}
```

**Prevents:**
- Stack trace leaks
- Sensitive error messages
- Unhandled exceptions crashing server

**6. Request Validation**

```php
#[McpTool(name: 'create_entry')]
public function createEntry(
    string $section,
    string $title,
    array $fields = [],
    ?int $authorId = null,
    ?RequestContext $context = null
): array {
    // Validate section exists
    $sectionModel = Craft::$app->getEntries()->getSectionByHandle($section);
    if (!$sectionModel) {
        throw new McpException("Section not found: {$section}");
    }
    
    // Validate author
    if ($authorId && !User::find()->id($authorId)->exists()) {
        throw new McpException("Author not found: {$authorId}");
    }
    
    // Validate fields against field layout
    $fieldLayout = $sectionModel->getEntryTypes()[0]->getFieldLayout();
    foreach ($fields as $handle => $value) {
        if (!$fieldLayout->getFieldByHandle($handle)) {
            throw new McpException("Invalid field: {$handle}");
        }
    }
    
    return SafeExecution::run(function() use ($section, $title, $fields, $authorId) {
        // Create entry
    });
}
```

**7. Audit Logging**

```php
// storage/logs/mcp.log
[2026-01-13 10:23:45] mcp.INFO: Tool called: list_entries {"params":{"section":"news","limit":10}} {"ip":"192.168.1.100","session":"xyz"}
[2026-01-13 10:24:12] mcp.WARNING: Dangerous tool called: create_entry {"params":{"section":"news"}} {"ip":"192.168.1.100"}
[2026-01-13 10:25:03] mcp.ERROR: Tool execution failed: Database connection lost {"tool":"update_entry"}
```

**FileLogger Implementation:**
```php
class FileLogger {
    private string $logFile;
    
    public function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $ip = Craft::$app->getRequest()->getUserIP();
        $context['ip'] = $ip;
        
        $line = sprintf(
            "[%s] mcp.%s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            json_encode($context)
        );
        
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
```

**8. Read-Only Mode**

```php
// config/mcp.php for production
return [
    'enabled' => true,
    'enableDangerousTools' => false, // No create/update/delete
    'disabledTools' => [
        'create_entry',
        'update_entry',
        'create_backup',
        'execute_graphql',  // Disable mutations
        'tinker',
    ],
];
```

**Effectively creates a read-only MCP server:**
- AI can query/analyze content
- AI cannot modify anything
- Perfect for content analysis/documentation use cases

### Configuration Comparison

#### Our Configuration

**File:** `config/mcpwrapper.php` (or `mcp-wrapper.php`)

```php
<?php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
        'frontend' => getenv('GQL_FRONTEND_TOKEN'),
        'public' => getenv('GQL_PUBLIC_TOKEN'),
    ],
];
```

**That's it.** Super simple but limited.

**Pros:**
- ✅ Easy to understand
- ✅ Quick setup
- ✅ Multi-schema support

**Cons:**
- ❌ No security options
- ❌ No tool controls
- ❌ No environment-specific settings
- ❌ No extensibility

#### Their Configuration

**File:** `config/mcp.php`

```php
<?php
return [
    // Main toggle
    'enabled' => true,
    
    // Security
    'enableDangerousTools' => false,
    'allowedIps' => [
        '127.0.0.1',
        '::1',
    ],
    
    // Tool management
    'disabledTools' => [
        'tinker',
    ],
    
    // Logging
    'logLevel' => 'info', // debug, info, warning, error
    'logToFile' => true,
    'logFile' => '@storage/logs/mcp.log',
    
    // Server info
    'serverName' => 'Craft CMS MCP Server',
    'serverVersion' => '1.0.0',
    'instructions' => <<<'INSTRUCTIONS'
You are connected to a Craft CMS installation.
Available tools allow you to:
- Query and analyze content
- Inspect system configuration
- Debug issues
- Execute GraphQL queries

Be careful with dangerous tools (marked with ⚠️).
INSTRUCTIONS,
    
    // Performance
    'cacheManifests' => true,
    'cacheDuration' => 3600,
    
    // Extensions
    'autoloadToolsFrom' => [
        '@plugins/*/tools',
        '@modules/*/mcp/tools',
    ],
];
```

**Environment-Specific:**
```php
// config/mcp/dev.php
return [
    'enableDangerousTools' => true,
    'logLevel' => 'debug',
    'allowedIps' => [], // Allow all in dev
];

// config/mcp/production.php
return [
    'enableDangerousTools' => false,
    'logLevel' => 'warning',
    'allowedIps' => [
        '10.0.0.0/8', // Internal only
    ],
    'disabledTools' => [
        'tinker',
        'run_query',
        'execute_graphql',
    ],
];
```

**Pros:**
- ✅ Comprehensive security controls
- ✅ Environment-specific overrides
- ✅ Fine-grained tool management
- ✅ Logging configuration
- ✅ Extension support

**Cons:**
- ⚠️ More complex (but worth it)
- ⚠️ Requires more setup

---

## Setup & Developer Experience

### Installation & Setup

#### Our Setup Process

**1. Install Plugin:**
```bash
composer require rocketpark/mcp-wrapper
```

**2. Configure:**
```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
    ],
];
```

**3. Create GraphQL Schema:**
- Go to Craft CP → GraphQL → Schemas
- Create "AI" schema
- Generate token
- Set permissions

**4. Configure AI Client Manually:**

**Claude Desktop:**
```json
{
  "mcpServers": {
    "craft-cms": {
      "url": "https://mysite.com/actions/mcp-wrapper/mcp/ai",
      "transport": {
        "type": "sse",
        "url": "https://mysite.com/actions/mcp-wrapper/mcp/sse/ai"
      },
      "headers": {
        "Authorization": "Bearer YOUR_GRAPHQL_TOKEN"
      }
    }
  }
}
```

**Cursor:**
```json
{
  "mcp": {
    "servers": {
      "craft-cms": {
        "command": "curl",
        "args": [
          "-X", "POST",
          "-H", "Content-Type: application/json",
          "-H", "Authorization: Bearer YOUR_TOKEN",
          "https://mysite.com/actions/mcp-wrapper/mcp/ai"
        ]
      }
    }
  }
}
```

**Total Time:** ~15 minutes (if you know what you're doing)

**Pain Points:**
- ❌ Manual client configuration (copy/paste)
- ❌ Token management
- ❌ No validation until you try it
- ❌ Errors are cryptic

#### Their Setup Process

**1. Install Plugin:**
```bash
composer require stimmt/craft-mcp
```

**2. Run Installation Wizard:**
```bash
php craft mcp/install
```

**Interactive Prompts:**
```
Craft MCP Installation Wizard
=============================

Select AI Client:
  1) Claude Desktop
  2) Cursor
  3) Cline (VSCode)
  4) Other
> 1

Environment:
  1) Local (direct)
  2) DDEV
  3) Docker
  4) Remote (SSH)
> 2

Enable dangerous tools? (create, update, delete) [y/N]: n

Allow all IPs? [y/N]: n

Enter allowed IPs (comma-separated):
> 127.0.0.1, 10.0.0.0/8

Generating configuration...
✓ Created config/mcp.php
✓ Updated DDEV config
✓ Generated ~/.config/Claude/claude_desktop_config.json

Configuration complete! Restart Claude Desktop to connect.
```

**Auto-Generated Files:**

**config/mcp.php:**
```php
return [
    'enabled' => true,
    'enableDangerousTools' => false,
    'allowedIps' => ['127.0.0.1', '10.0.0.0/8'],
];
```

**~/.config/Claude/claude_desktop_config.json:**
```json
{
  "mcpServers": {
    "craft-cms-mysite": {
      "command": "ddev",
      "args": ["exec", "php", "vendor/stimmt/craft-mcp/bin/mcp-server"]
    }
  }
}
```

**.ddev/docker-compose.mcp.yaml:** (if DDEV)
```yaml
services:
  web:
    labels:
      com.ddev.site-name: "${DDEV_SITENAME}"
      com.ddev.mcp.enabled: "true"
```

**3. Test Connection:**
```bash
php craft mcp/test
```

**Output:**
```
Testing MCP Server...
✓ Server starts successfully
✓ Responds to initialize
✓ Lists 47 tools
✓ Lists 9 prompts
✓ Lists 12 resources
✓ Completion providers working

All checks passed! Your MCP server is ready.
```

**Total Time:** ~3 minutes

**Advantages:**
- ✅ Guided setup
- ✅ Auto-configuration for clients
- ✅ Validates configuration
- ✅ Environment detection
- ✅ Helpful error messages
- ✅ Test command

### Hot Reload

#### Our Approach

**Manual:**
- Modify code
- Clear Craft caches
- Restart PHP-FPM (maybe)
- Reload AI client

**No dedicated reload mechanism.**

#### Their Approach

**Signal-Based Reload:**
```bash
# Send SIGHUP to reload without restarting
kill -HUP $(pgrep -f "mcp-server")

# Or via craft command
php craft mcp/reload
```

**Implementation:**
```php
// src/support/SignalHandler.php
class SignalHandler {
    private static bool $reloadRequested = false;
    
    public static function register(): void {
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGHUP, [self::class, 'handleReload']);
        }
    }
    
    public static function handleReload(int $signal): void {
        self::$reloadRequested = true;
    }
    
    public static function shouldReload(): bool {
        if (extension_loaded('pcntl')) {
            pcntl_signal_dispatch();
        }
        return self::$reloadRequested;
    }
}

// In server loop
while ($request = $transport->receive()) {
    if (SignalHandler::shouldReload()) {
        // Reload tool registry
        $registry->refresh();
        $logger->info('Hot reload complete');
    }
    
    $server->handle($request);
}
```

**Use Case:**
- Install new plugin with MCP tools
- Run `php craft mcp/reload`
- New tools available immediately (no client restart)

---

## Extension & Extensibility

### Plugin Integration

#### Our Extensibility

**Current State:** ❌ No extension mechanism

**If you wanted to add custom tools:**
1. Fork the plugin
2. Modify ManifestBuilderService
3. Maintain your fork forever

**OR:**

Create a separate plugin that:
- Adds its own controller
- Implements JSON-RPC server
- Manages its own manifest

**Issues:**
- No shared infrastructure
- Duplicate code
- No unified tool registry
- Client must configure multiple servers

#### Their Extensibility

**Event-Based Plugin System:**

**1. Register Tools from Other Plugins:**

```php
// In your plugin's init()
Event::on(
    ToolRegistry::class,
    ToolRegistry::EVENT_REGISTER_TOOLS,
    function(RegisterToolsEvent $event) {
        $event->tools[] = new MyCustomTool();
    }
);
```

**2. Register Prompts:**

```php
Event::on(
    PromptRegistry::class,
    PromptRegistry::EVENT_REGISTER_PROMPTS,
    function(RegisterPromptsEvent $event) {
        $event->prompts[] = new MyCustomPrompt();
    }
);
```

**3. Register Resources:**

```php
Event::on(
    ResourceRegistry::class,
    ResourceRegistry::EVENT_REGISTER_RESOURCES,
    function(RegisterResourcesEvent $event) {
        $event->resources[] = new MyCustomResource();
    }
);
```

**Example: Commerce Tools Plugin**

```php
// plugins/my-commerce-tools/src/Plugin.php
class Plugin extends \craft\base\Plugin {
    public function init(): void {
        parent::init();
        
        // Only register if Commerce is installed
        if (class_exists(\craft\commerce\Plugin::class)) {
            Event::on(
                ToolRegistry::class,
                ToolRegistry::EVENT_REGISTER_TOOLS,
                [$this, 'registerCommerceTools']
            );
        }
    }
    
    public function registerCommerceTools(RegisterToolsEvent $event): void {
        $event->tools[] = new tools\ListProducts();
        $event->tools[] = new tools\GetOrder();
        $event->tools[] = new tools\ListOrderStatuses();
    }
}

// plugins/my-commerce-tools/src/tools/ListProducts.php
use Stimmt\CraftMcp\Attributes\McpTool;
use Stimmt\CraftMcp\Attributes\McpToolMeta;

class ListProducts {
    #[McpTool(
        name: 'commerce_list_products',
        description: 'List Commerce products with filtering',
    )]
    #[McpToolMeta(
        category: ToolCategory::COMMERCE,
        source: 'my-commerce-tools',
    )]
    public function listProducts(
        ?int $limit = 20,
        ?string $type = null,
        ?bool $available = null,
        ?RequestContext $context = null
    ): array {
        return SafeExecution::run(function() use ($limit, $type, $available) {
            $query = \craft\commerce\elements\Product::find()
                ->limit($limit);
            
            if ($type) $query->type($type);
            if ($available !== null) $query->hasStock($available);
            
            return [
                'products' => array_map([$this, 'serializeProduct'], $query->all()),
            ];
        });
    }
}
```

**After installing the plugin:**
```bash
php craft mcp/reload  # Tools immediately available!
```

**Tool Registry Tracks Source:**
```php
Mcp::getToolRegistry()->getSummary();
// Returns:
[
    'total' => 53,
    'by_source' => [
        'craft-mcp' => 47,
        'my-commerce-tools' => 6,
    ],
    'by_category' => [
        'content' => 10,
        'system' => 7,
        'commerce' => 6,
        // ...
    ],
]
```

**4. Auto-Discovery:**

```php
// config/mcp.php
return [
    'autoloadToolsFrom' => [
        '@plugins/*/mcp/tools',      // Any plugin can add tools here
        '@modules/*/mcp/tools',
        '@app/mcp/tools',            // Project-specific tools
    ],
];
```

**Folder structure:**
```
plugins/
  my-plugin/
    mcp/
      tools/
        MyTool.php         # Auto-discovered!
      prompts/
        MyPrompt.php       # Auto-discovered!
```

**5. Conditional Tool Loading:**

```php
class MyTools {
    #[McpTool(name: 'my_tool')]
    #[McpToolMeta(
        requires: ['craft', '>=4.0'],
        requiresPlugins: ['commerce', 'my-other-plugin'],
    )]
    public function myTool(): array {
        // Only loaded if requirements met
    }
}
```

**Registry validates at boot:**
```php
public function registerTool(object $toolClass): void {
    $meta = $this->extractMetadata($toolClass);
    
    if (!$this->checkRequirements($meta->requires)) {
        $this->logger->debug("Skipping tool due to unmet requirements: {$meta->name}");
        return;
    }
    
    $this->tools[$meta->name] = $toolClass;
}
```

### Extension API Comparison

| Feature | Our Plugin | Their Plugin |
|---------|------------|--------------|
| Event-based registration | ❌ No | ✅ Yes |
| Auto-discovery | ❌ No | ✅ Yes |
| Tool registry | ❌ No | ✅ Yes |
| Prompt registration | ❌ N/A | ✅ Yes |
| Resource registration | ❌ N/A | ✅ Yes |
| Hot reload | ❌ No | ✅ Yes |
| Source tracking | ❌ No | ✅ Yes |
| Conditional loading | ❌ No | ✅ Yes |

---

## Performance & Caching

### Manifest Caching

#### Our Implementation

**Location:** `@storage/runtime/mcp/manifest-{schema}.json`

**Cache Strategy:**
```php
public function getOrBuildManifest(string $schemaHandle, bool $force = false): array {
    $cacheFile = $this->getCacheFilePath($schemaHandle);
    
    if (!$force && file_exists($cacheFile)) {
        $manifest = json_decode(file_get_contents($cacheFile), true);
        if ($manifest) {
            return $manifest;
        }
    }
    
    // Build from scratch
    $manifest = $this->buildManifest($schemaHandle);
    
    // Cache it
    $this->cacheManifest($schemaHandle, $manifest);
    
    return $manifest;
}
```

**Cache Invalidation:**
```php
// In Plugin.php
Event::on(
    ProjectConfig::class,
    ProjectConfig::EVENT_REBUILD,
    function() {
        $this->manifestBuilder->clearAllCaches();
    }
);

Event::on(
    Gql::class,
    Gql::EVENT_AFTER_SAVE_SCHEMA,
    function() {
        $this->manifestBuilder->clearAllCaches();
    }
);
```

**Manual Clear:**
- CP Utility → "Clear All Caches"
- URL: `/actions/mcp-wrapper/manifest/{schema}?force=1`

**Performance:**
- First request: ~200-500ms (introspection)
- Cached: ~5-10ms (file read + JSON parse)

#### Their Implementation

**Tool Metadata Caching:**
- Reflection/attribute parsing is expensive
- Cache tool definitions at boot
- Only re-parse on hot reload

**No manifest files** (stdio doesn't need them), but:
- Metadata extracted once per process lifetime
- Stored in memory
- SIGHUP reload refreshes cache

**Resource Caching:**
```php
#[McpResource(
    uri: 'craft://entries/news',
    name: 'news-entries',
    cacheDuration: 300,  // 5 minutes
)]
public function newsEntries(): array {
    // Results cached for 5 minutes
}
```

**Performance:**
- Process startup: ~100-300ms (load Craft + discover tools)
- Tool calls: ~10-50ms (direct Craft API)
- Long-lived process (no boot overhead per request)

### Performance Comparison

| Metric | Our Plugin (HTTP) | Their Plugin (stdio) |
|--------|-------------------|----------------------|
| **Cold Start** | 200-500ms | 100-300ms |
| **Warm Request** | 5-10ms | 10-50ms |
| **Connection Overhead** | HTTP handshake per request | None (persistent) |
| **Process Lifetime** | Per-request (PHP-FPM) | Long-lived |
| **Memory Usage** | Lower (stateless) | Higher (persistent) |
| **Concurrent Requests** | Excellent (HTTP pooling) | Single-threaded |
| **Scalability** | Horizontal | Vertical |

**Verdict:**
- **Our approach:** Better for multiple concurrent AI clients
- **Their approach:** Better for single AI assistant doing many operations

---

## Implementation Recommendations

### Priority 1: Critical Security (Implement Immediately)

These gaps pose production risks and should be addressed before wider deployment.

#### 1.1 IP Allowlisting

**Problem:** Public HTTP endpoints with no access control beyond GraphQL tokens.

**Solutions (Multiple Layers):**

**Layer 1: Craft's Built-in IP Restrictions (Immediate, No Code)**
```php
// config/general.php
use craft\config\GeneralConfig;

return GeneralConfig::create()
    // Restrict ALL Craft access by IP (includes MCP endpoints)
    ->allowedIps([
        '127.0.0.1',
        '10.0.0.0/8',           // Internal network
        '192.168.1.100',        // Specific AI server
        getenv('BOTPRESS_IP'),  // Dynamic from env
    ]);
```

**Pros:**
- ✅ No code changes required
- ✅ Proven Craft functionality
- ✅ Works immediately

**Cons:**
- ⚠️ Restricts ALL Craft traffic (frontend, CP, MCP)
- ⚠️ Can't have different rules per schema
- ⚠️ May break frontend if too restrictive

**Layer 2: Plugin-Specific IP Control (Custom Implementation)**
```php
// config/mcpwrapper.php
return [
    'schemas' => [...],
    
    'security' => [
        // Per-schema IP restrictions
        'allowedIps' => [
            'ai' => [
                '127.0.0.1',
                getenv('BOTPRESS_IP'),
            ],
            'internal' => [
                '10.0.0.0/8',  // VPN only
            ],
        ],
        'enabled' => true,
    ],
];

// src/controllers/McpController.php
public function beforeAction($action): bool {
    if (!$this->checkIpAccess()) {
        throw new ForbiddenHttpException('MCP access denied from this IP address');
    }
    return parent::beforeAction($action);
}

private function checkIpAccess(): bool {
    $config = McpWrapper::getInstance()->getSettings();
    
    if (!$config['security']['enabled'] ?? false) {
        return true;
    }
    
    $allowedIps = $config['security']['allowedIps'] ?? [];
    if (empty($allowedIps)) {
        return true;
    }
    
    $clientIp = Craft::$app->getRequest()->getUserIP();
    
    foreach ($allowedIps as $allowed) {
        if ($this->ipInRange($clientIp, $allowed)) {
            return true;
        }
    }
    
    Craft::warning("MCP access denied from IP: {$clientIp}", 'mcp-wrapper');
    return false;
}

private function ipInRange(string $ip, string $range): bool {
    if (strpos($range, '/') === false) {
        return $ip === $range; // Exact match
    }
    
    // CIDR notation
    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    
    return ($ip & $mask) == ($subnet & $mask);
}
```

**Testing:**
```bash
# Should succeed from allowed IP
curl -H "Authorization: Bearer TOKEN" https://site.test/actions/mcp-wrapper/mcp/ai

# Should fail from other IPs
# Returns: 403 Forbidden
```

**Priority:** 🔴 **CRITICAL - Week 1**

---

#### 1.2 Dangerous Tool Protection

**Problem:** All tools treated equally. No distinction between safe (read) and dangerous (write) operations.

**Solution:**

**1. Add tool metadata to manifest:**
```php
// src/services/ManifestBuilderService.php
private function buildToolDefinition(Section $section): array {
    $tool = [
        'name' => "query_{$section->handle}",
        'description' => "Query {$section->name} entries",
        'inputSchema' => [...],
        'meta' => [
            'readOnly' => true,
            'dangerous' => false,
            'category' => 'content',
        ],
    ];
    
    return $tool;
}

// If we add mutation tools in the future:
private function buildMutationTool(Section $section): array {
    return [
        'name' => "create_{$section->handle}",
        'description' => "⚠️ DANGEROUS: Create new {$section->name} entry",
        'meta' => [
            'readOnly' => false,
            'dangerous' => true,
            'requiresConfirmation' => true,
        ],
    ];
}
```

**2. Add config controls:**
```php
// config/mcpwrapper.php
return [
    'schemas' => [...],
    
    'enableMutations' => false, // Default: read-only
    
    'disabledTools' => [
        // Specific tools to disable
        'query_sensitive_section',
    ],
];
```

**3. Enforce at runtime:**
```php
// src/services/McpServerService.php
private function callTool(string $name, array $arguments): array {
    $config = McpWrapper::getInstance()->getSettings();
    
    // Check if tool is disabled
    if (in_array($name, $config['disabledTools'] ?? [])) {
        throw new \Exception("Tool '{$name}' is disabled in configuration");
    }
    
    // Check if mutations are enabled
    if ($this->isWriteTool($name) && !($config['enableMutations'] ?? false)) {
        throw new \Exception("Write operations are disabled. Set 'enableMutations' => true to enable.");
    }
    
    // Execute tool
    return $this->executeTool($name, $arguments);
}

private function isWriteTool(string $name): bool {
    return str_starts_with($name, 'create_') 
        || str_starts_with($name, 'update_') 
        || str_starts_with($name, 'delete_');
}
```

**Priority:** 🔴 **CRITICAL - Week 1**

---

#### 1.3 Audit Logging

**Problem:** No tracking of what AI assistants are doing with Craft content.

**Solution:**
```php
// src/services/McpLogger.php
namespace rocketpark\mcpwrapper\services;

use Craft;
use yii\base\Component;
use yii\log\Logger;

class McpLogger extends Component {
    private string $logFile;
    
    public function init(): void {
        $this->logFile = Craft::getAlias('@storage/logs/mcp-wrapper.log');
    }
    
    public function logToolCall(string $schema, string $toolName, array $arguments): void {
        $this->log('info', "Tool called: {$toolName}", [
            'schema' => $schema,
            'tool' => $toolName,
            'arguments' => $this->sanitizeArguments($arguments),
            'ip' => Craft::$app->getRequest()->getUserIP(),
            'userAgent' => Craft::$app->getRequest()->getUserAgent(),
        ]);
    }
    
    public function logToolResult(string $toolName, bool $success, ?string $error = null): void {
        $level = $success ? 'info' : 'error';
        $message = $success ? "Tool succeeded: {$toolName}" : "Tool failed: {$toolName}";
        
        $this->log($level, $message, [
            'tool' => $toolName,
            'success' => $success,
            'error' => $error,
        ]);
    }
    
    public function logSecurityEvent(string $event, array $context = []): void {
        $this->log('warning', "Security: {$event}", $context);
    }
    
    private function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES);
        
        $line = sprintf(
            "[%s] mcp-wrapper.%s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextJson
        );
        
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        
        // Also log to Craft's main log for errors/warnings
        if (in_array($level, ['error', 'warning'])) {
            Craft::getLogger()->log($message, Logger::LEVEL_WARNING, 'mcp-wrapper');
        }
    }
    
    private function sanitizeArguments(array $arguments): array {
        // Remove sensitive data from logs
        $sanitized = $arguments;
        $sensitiveKeys = ['password', 'token', 'secret', 'apiKey'];
        
        array_walk_recursive($sanitized, function(&$value, $key) use ($sensitiveKeys) {
            if (in_array($key, $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });
        
        return $sanitized;
    }
}

// Register service in Plugin.php
public function init(): void {
    $this->setComponents([
        'logger' => McpLogger::class,
    ]);
}

// Use in McpServerService.php
private function callTool(string $name, array $arguments): array {
    $logger = McpWrapper::getInstance()->logger;
    $logger->logToolCall($this->schemaHandle, $name, $arguments);
    
    try {
        $result = $this->executeTool($name, $arguments);
        $logger->logToolResult($name, true);
        return $result;
        
    } catch (\Exception $e) {
        $logger->logToolResult($name, false, $e->getMessage());
        throw $e;
    }
}
```

**Example Log Output:**
```
[2026-01-13 14:23:45] mcp-wrapper.INFO: Tool called: query_news {"schema":"ai","tool":"query_news","arguments":{"limit":10},"ip":"192.168.1.100","userAgent":"Claude Desktop/1.0"}
[2026-01-13 14:23:46] mcp-wrapper.INFO: Tool succeeded: query_news {"tool":"query_news","success":true}
[2026-01-13 14:24:12] mcp-wrapper.WARNING: Security: IP access denied {"ip":"203.0.113.45","path":"/actions/mcp-wrapper/mcp/ai"}
```

**Priority:** 🔴 **CRITICAL - Week 1**

---

### Priority 2: MCP Feature Parity (4-6 Weeks)

These features make the MCP server much more useful and unlock new AI workflows.

#### 2.1 Implement MCP Prompts

**What:** Pre-built conversation starters with structured data.

**Implementation:**
```php
// src/services/PromptRegistry.php
namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\elements\Entry;

class PromptRegistry {
    public function listPrompts(): array {
        return [
            $this->schemaExplorerPrompt(),
            $this->contentHealthPrompt(),
            $this->queryBuilderPrompt(),
        ];
    }
    
    public function getPrompt(string $name, array $arguments = []): array {
        return match($name) {
            'schema_explorer' => $this->schemaExplorerPrompt(),
            'content_health' => $this->contentHealthPrompt(),
            'query_builder' => $this->queryBuilderPrompt($arguments),
            default => throw new \Exception("Unknown prompt: {$name}"),
        };
    }
    
    private function schemaExplorerPrompt(): array {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $sectionData = array_map(function($section) {
            $fieldLayout = $section->getEntryTypes()[0]->getFieldLayout();
            
            return [
                'handle' => $section->handle,
                'name' => $section->name,
                'type' => $section->type,
                'fields' => array_map(fn($field) => [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => (new \ReflectionClass($field))->getShortName(),
                ], $fieldLayout->getCustomFields()),
            ];
        }, $sections);
        
        return [
            'name' => 'schema_explorer',
            'description' => 'Explore the Craft CMS content model and relationships',
            'arguments' => [],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
I'm sharing the complete content model for this Craft CMS installation.
Please analyze it and help me understand:

1. What types of content are available?
2. What relationships exist between sections?
3. What queries would be most useful?

Content Model:
```json
{$this->formatJson($sectionData)}
```

Please provide a comprehensive overview and suggest some useful queries.
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    private function contentHealthPrompt(): array {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $healthData = array_map(function($section) {
            return [
                'section' => $section->handle,
                'name' => $section->name,
                'counts' => [
                    'live' => Entry::find()->section($section->handle)->status('live')->count(),
                    'disabled' => Entry::find()->section($section->handle)->status('disabled')->count(),
                    'drafts' => Entry::find()->section($section->handle)->drafts()->count(),
                ],
                'lastUpdated' => Entry::find()
                    ->section($section->handle)
                    ->orderBy('dateUpdated DESC')
                    ->one()
                    ?->dateUpdated
                    ?->format('Y-m-d H:i:s'),
            ];
        }, $sections);
        
        return [
            'name' => 'content_health',
            'description' => 'Analyze content health, freshness, and identify maintenance needs',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
Please analyze this content health report:

```json
{$this->formatJson($healthData)}
```

Provide insights on:
1. Overall content health score
2. Sections needing attention (stale content, high disabled ratio)
3. Content freshness analysis
4. Recommendations for content maintenance
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    private function queryBuilderPrompt(array $arguments): array {
        $section = $arguments['section'] ?? null;
        
        if (!$section) {
            // List available sections
            $sections = Craft::$app->getEntries()->getAllSections();
            $sectionList = implode(', ', array_map(fn($s) => $s->handle, $sections));
            
            return [
                'name' => 'query_builder',
                'description' => 'Help build GraphQL queries for specific sections',
                'arguments' => [
                    ['name' => 'section', 'description' => "Section to query. Available: {$sectionList}", 'required' => true],
                ],
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => "Which section would you like to query? Available sections: {$sectionList}"
                        ]
                    ]
                ]
            ];
        }
        
        // Get section details
        $sectionModel = Craft::$app->getEntries()->getSectionByHandle($section);
        $fieldLayout = $sectionModel->getEntryTypes()[0]->getFieldLayout();
        
        return [
            'name' => 'query_builder',
            'description' => 'Help build GraphQL queries',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
I want to query the "{$sectionModel->name}" section.

Available fields:
{$this->formatFieldList($fieldLayout->getCustomFields())}

Example query:
```graphql
query {
  entries(section: "{$section}", limit: 10) {
    title
    slug
    ... add fields here
  }
}
```

Please help me build an appropriate query based on what I'm looking for.
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    private function formatJson(array $data): string {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    private function formatFieldList(array $fields): string {
        return implode("\n", array_map(fn($f) => "- {$f->handle} ({$f->name}): " . (new \ReflectionClass($f))->getShortName(), $fields));
    }
}
```

**Add to McpServerService:**
```php
private function dispatchMethod(string $method, array $params): array {
    return match($method) {
        'initialize' => $this->handleInitialize($params),
        'tools/list' => $this->handleToolsList($params),
        'tools/call' => $this->handleToolsCall($params),
        'prompts/list' => $this->handlePromptsList(),        // NEW
        'prompts/get' => $this->handlePromptsGet($params),   // NEW
        'ping' => $this->handlePing(),
        default => throw new \Exception("Unknown method: {$method}"),
    };
}

private function handlePromptsList(): array {
    $registry = McpWrapper::getInstance()->promptRegistry;
    $prompts = $registry->listPrompts();
    
    return ['prompts' => $prompts];
}

private function handlePromptsGet(array $params): array {
    $name = $params['name'] ?? throw new \Exception('Prompt name required');
    $arguments = $params['arguments'] ?? [];
    
    $registry = McpWrapper::getInstance()->promptRegistry;
    $prompt = $registry->getPrompt($name, $arguments);
    
    return $prompt;
}
```

**Priority:** 🟡 **HIGH - Week 3-4**

---

#### 2.2 Implement MCP Resources

**What:** URI-based read-only data access (like REST API for AI).

**Implementation:**
```php
// src/services/ResourceRegistry.php
namespace rocketpark\mcpwrapper\services;

use Craft;

class ResourceRegistry {
    private string $schemaHandle;
    
    public function __construct(string $schemaHandle) {
        $this->schemaHandle = $schemaHandle;
    }
    
    public function listResources(): array {
        return [
            [
                'uri' => "mcp://{$this->schemaHandle}/schema",
                'name' => 'GraphQL Schema',
                'description' => 'Complete GraphQL schema (SDL)',
                'mimeType' => 'text/plain',
            ],
            [
                'uri' => "mcp://{$this->schemaHandle}/sections",
                'name' => 'Content Sections',
                'description' => 'List of all content sections',
                'mimeType' => 'application/json',
            ],
            [
                'uriTemplate' => "mcp://{$this->schemaHandle}/sections/{handle}",
                'name' => 'Section Details',
                'description' => 'Detailed schema for a specific section',
                'mimeType' => 'application/json',
            ],
            [
                'uriTemplate' => "mcp://{$this->schemaHandle}/entries/{section}",
                'name' => 'Section Entries',
                'description' => 'Recent entries from a section (limit 50)',
                'mimeType' => 'application/json',
            ],
        ];
    }
    
    public function readResource(string $uri): array {
        // Parse URI: mcp://{schema}/{type}/{param}
        $parts = parse_url($uri);
        $path = trim($parts['path'] ?? '', '/');
        $segments = explode('/', $path);
        
        return match($segments[0] ?? null) {
            'schema' => $this->getGraphQLSchema(),
            'sections' => isset($segments[1]) 
                ? $this->getSectionDetails($segments[1])
                : $this->listSections(),
            'entries' => $this->listSectionEntries($segments[1] ?? null),
            default => throw new \Exception("Unknown resource: {$uri}"),
        };
    }
    
    private function getGraphQLSchema(): array {
        $schema = Craft::$app->getGql()->getSchemaByAccessToken($this->getAccessToken());
        $sdl = $schema ? Craft::$app->getGql()->getSchemaDef($schema) : '';
        
        return [
            'contents' => [
                [
                    'uri' => "mcp://{$this->schemaHandle}/schema",
                    'mimeType' => 'text/plain',
                    'text' => $sdl,
                ]
            ]
        ];
    }
    
    private function listSections(): array {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $data = array_map(fn($s) => [
            'handle' => $s->handle,
            'name' => $s->name,
            'type' => $s->type,
            'uri' => "mcp://{$this->schemaHandle}/sections/{$s->handle}",
        ], $sections);
        
        return [
            'contents' => [
                [
                    'uri' => "mcp://{$this->schemaHandle}/sections",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                ]
            ]
        ];
    }
    
    private function getSectionDetails(string $handle): array {
        $section = Craft::$app->getEntries()->getSectionByHandle($handle);
        if (!$section) {
            throw new \Exception("Section not found: {$handle}");
        }
        
        $entryType = $section->getEntryTypes()[0];
        $fieldLayout = $entryType->getFieldLayout();
        
        $data = [
            'handle' => $section->handle,
            'name' => $section->name,
            'type' => $section->type,
            'fields' => array_map(fn($f) => [
                'handle' => $f->handle,
                'name' => $f->name,
                'type' => (new \ReflectionClass($f))->getShortName(),
                'required' => $f->required,
            ], $fieldLayout->getCustomFields()),
        ];
        
        return [
            'contents' => [
                [
                    'uri' => "mcp://{$this->schemaHandle}/sections/{$handle}",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                ]
            ]
        ];
    }
    
    private function listSectionEntries(?string $section): array {
        if (!$section) {
            throw new \Exception('Section parameter required');
        }
        
        $entries = Entry::find()
            ->section($section)
            ->limit(50)
            ->orderBy('dateCreated DESC')
            ->all();
        
        $data = array_map(fn($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'status' => $e->status,
            'dateCreated' => $e->dateCreated->format('Y-m-d H:i:s'),
        ], $entries);
        
        return [
            'contents' => [
                [
                    'uri' => "mcp://{$this->schemaHandle}/entries/{$section}",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                ]
            ]
        ];
    }
    
    private function getAccessToken(): string {
        $config = McpWrapper::getInstance()->getSettings();
        return $config['schemas'][$this->schemaHandle] ?? '';
    }
}
```

**Add to McpServerService:**
```php
private function dispatchMethod(string $method, array $params): array {
    return match($method) {
        // ... existing methods
        'resources/list' => $this->handleResourcesList(),      // NEW
        'resources/read' => $this->handleResourcesRead($params), // NEW
        default => throw new \Exception("Unknown method: {$method}"),
    };
}

private function handleResourcesList(): array {
    $registry = new ResourceRegistry($this->schemaHandle);
    return ['resources' => $registry->listResources()];
}

private function handleResourcesRead(array $params): array {
    $uri = $params['uri'] ?? throw new \Exception('Resource URI required');
    $registry = new ResourceRegistry($this->schemaHandle);
    return $registry->readResource($uri);
}
```

**AI Usage:**
```
User: "Show me the GraphQL schema"
AI: *reads resource mcp://ai/schema*

User: "What sections are available?"
AI: *reads resource mcp://ai/sections*

User: "What fields does the news section have?"
AI: *reads resource mcp://ai/sections/news*
```

**Priority:** 🟡 **HIGH - Week 3-4**

---

#### 2.3 Add More System/Admin Tools

**Current:** Only content querying via GraphQL  
**Needed:** System inspection, debugging, administration

**Recommended New Tools:**

**1. System Information:**
```php
// src/tools/SystemTools.php
public function getSystemInfo(): array {
    return [
        'craft' => [
            'version' => Craft::$app->getVersion(),
            'edition' => Craft::$app->getEditionName(),
            'env' => Craft::$app->env,
        ],
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ],
        'database' => [
            'driver' => Craft::$app->getDb()->getDriverName(),
            'version' => Craft::$app->getDb()->getServerVersion(),
        ],
        'plugins' => array_map(fn($p) => [
            'handle' => $p->handle,
            'name' => $p->name,
            'version' => $p->version,
        ], Craft::$app->getPlugins()->getAllPlugins()),
    ];
}
```

**2. Log Reader:**
```php
public function readLogs(int $limit = 50, string $level = 'error'): array {
    $logFile = Craft::getAlias('@storage/logs/web.log');
    
    if (!file_exists($logFile)) {
        return ['logs' => []];
    }
    
    $logs = array_slice(file($logFile), -$limit);
    
    return [
        'logs' => array_map(fn($line) => $this->parseLogLine($line), $logs),
    ];
}
```

**3. Cache Management:**
```php
public function clearCaches(array $caches = []): array {
    if (empty($caches)) {
        Craft::$app->getCache()->flush();
        return ['cleared' => 'all'];
    }
    
    foreach ($caches as $cache) {
        match($cache) {
            'data' => Craft::$app->getCache()->flush(),
            'template' => Craft::$app->getView()->clearCaches(),
            'asset' => Craft::$app->getAssetTransforms()->deleteAllTransformIndexes(),
            default => null,
        };
    }
    
    return ['cleared' => $caches];
}
```

**4. Queue Status:**
```php
public function getQueueJobs(string $status = 'waiting'): array {
    $jobs = Craft::$app->getQueue()->getJobInfo($status);
    
    return [
        'jobs' => array_map(fn($job) => [
            'id' => $job->id,
            'description' => $job->description,
            'progress' => $job->progress,
            'status' => $job->status,
        ], $jobs),
    ];
}
```

**5. Project Config Status:**
```php
public function getProjectConfigDiff(): array {
    $diffs = Craft::$app->getProjectConfig()->getDiff();
    
    return [
        'hasChanges' => !empty($diffs),
        'changes' => $diffs,
    ];
}
```

**Priority:** 🟢 **MEDIUM - Week 5-6**

---

### Priority 3: Developer Experience (6-8 Weeks)

#### 3.1 Installation Wizard

**Goal:** One-command setup like `php craft mcp-wrapper/install`

**Features:**
- Detect environment (local, DDEV, Docker, Lando)
- Generate GraphQL schema if needed
- Create config file
- Generate client configs (Claude Desktop, Cursor, Cline)
- Test connection

**Implementation:**
```php
// src/console/controllers/InstallController.php
namespace rocketpark\mcpwrapper\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;

class InstallController extends Controller {
    public function actionIndex(): int {
        $this->stdout("MCP Wrapper Installation Wizard\n", Console::FG_CYAN);
        $this->stdout("================================\n\n");
        
        // 1. Choose schema
        $schema = $this->prompt('Schema handle:', ['default' => 'ai']);
        
        // 2. Check if GraphQL schema exists
        $gqlSchema = Craft::$app->getGql()->getSchemaByHandle($schema);
        
        if (!$gqlSchema) {
            $create = $this->confirm("GraphQL schema '{$schema}' doesn't exist. Create it?");
            
            if ($create) {
                $this->createGraphQLSchema($schema);
            }
        }
        
        // 3. Get/generate token
        $token = $this->getOrCreateToken($schema);
        
        // 4. Save config
        $this->saveConfig($schema, $token);
        
        // 5. Choose AI client
        $client = $this->select('Select AI client:', [
            'claude' => 'Claude Desktop',
            'cursor' => 'Cursor',
            'cline' => 'Cline (VSCode)',
            'manual' => 'Manual setup',
        ]);
        
        // 6. Generate client config
        if ($client !== 'manual') {
            $this->generateClientConfig($client, $schema, $token);
        }
        
        // 7. Test
        $this->testConnection($schema, $token);
        
        $this->stdout("\n✓ Installation complete!\n", Console::FG_GREEN);
        return 0;
    }
    
    private function createGraphQLSchema(string $handle): void {
        // Create GraphQL schema with full permissions
        $schema = new \craft\models\GqlSchema([
            'name' => ucfirst($handle) . ' MCP',
            'handle' => $handle,
        ]);
        
        $schema->scope = [
            'sections.*:read',
            'volumes.*:read',
            'globalsets.*:read',
            // Add all read permissions
        ];
        
        Craft::$app->getGql()->saveSchema($schema);
        $this->stdout("✓ Created GraphQL schema '{$handle}'\n", Console::FG_GREEN);
    }
    
    private function getOrCreateToken(string $schema): string {
        $gqlSchema = Craft::$app->getGql()->getSchemaByHandle($schema);
        
        // Check for existing tokens
        $tokens = Craft::$app->getGql()->getTokensBySchemaId($gqlSchema->id);
        
        if (!empty($tokens)) {
            $token = $tokens[0]->accessToken;
            $this->stdout("✓ Using existing token\n");
        } else {
            $token = Craft::$app->getSecurity()->generateRandomString(32);
            Craft::$app->getGql()->saveToken(new \craft\models\GqlToken([
                'name' => "{$schema} MCP Token",
                'accessToken' => $token,
                'schemaId' => $gqlSchema->id,
            ]));
            $this->stdout("✓ Generated new token\n", Console::FG_GREEN);
        }
        
        return $token;
    }
    
    private function saveConfig(string $schema, string $token): void {
        $configFile = Craft::getAlias('@config/mcpwrapper.php');
        
        $config = file_exists($configFile) 
            ? include($configFile)
            : ['schemas' => []];
        
        $config['schemas'][$schema] = $token;
        
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
        
        $this->stdout("✓ Saved config to config/mcpwrapper.php\n", Console::FG_GREEN);
    }
    
    private function generateClientConfig(string $client, string $schema, string $token): void {
        $siteUrl = Craft::$app->sites->getPrimarySite()->getBaseUrl();
        
        match($client) {
            'claude' => $this->generateClaudeConfig($siteUrl, $schema, $token),
            'cursor' => $this->generateCursorConfig($siteUrl, $schema, $token),
            'cline' => $this->generateClineConfig($siteUrl, $schema, $token),
        };
    }
    
    private function generateClaudeConfig(string $siteUrl, string $schema, string $token): void {
        $config = [
            'mcpServers' => [
                'craft-cms' => [
                    'url' => rtrim($siteUrl, '/') . "/actions/mcp-wrapper/mcp/{$schema}",
                    'transport' => [
                        'type' => 'sse',
                        'url' => rtrim($siteUrl, '/') . "/actions/mcp-wrapper/mcp/sse/{$schema}",
                    ],
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                    ],
                ],
            ],
        ];
        
        $configPath = $_SERVER['HOME'] . '/.config/Claude/claude_desktop_config.json';
        $dir = dirname($configPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        
        $this->stdout("✓ Generated Claude Desktop config: {$configPath}\n", Console::FG_GREEN);
        $this->stdout("  Restart Claude Desktop to connect.\n");
    }
    
    private function testConnection(string $schema, string $token): void {
        $this->stdout("\nTesting connection...\n");
        
        $siteUrl = Craft::$app->sites->getPrimarySite()->getBaseUrl();
        $url = rtrim($siteUrl, '/') . "/actions/mcp-wrapper/mcp/{$schema}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => ['protocolVersion' => '2025-06-18'],
                'id' => 1,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$token}",
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $this->stdout("✓ Connection successful!\n", Console::FG_GREEN);
        } else {
            $this->stdout("✗ Connection failed (HTTP {$httpCode})\n", Console::FG_RED);
            $this->stdout("Response: {$response}\n");
        }
    }
}
```

**Usage:**
```bash
php craft mcp-wrapper/install
```

**Priority:** 🟢 **MEDIUM - Week 6-7**

---

#### 3.2 Extension API

**Goal:** Let other Craft plugins add their own MCP tools.

**Implementation:**
```php
// src/events/RegisterToolsEvent.php
namespace rocketpark\mcpwrapper\events;

use yii\base\Event;

class RegisterToolsEvent extends Event {
    public array $tools = [];
    public string $schemaHandle;
}

// In Plugin.php
use rocketpark\mcpwrapper\events\RegisterToolsEvent;

const EVENT_REGISTER_TOOLS = 'registerTools';

// In ManifestBuilderService.php
public function buildManifest(string $schemaHandle): array {
    // Build core tools from sections
    $tools = $this->buildCoreTools($schemaHandle);
    
    // Allow plugins to register custom tools
    $event = new RegisterToolsEvent([
        'schemaHandle' => $schemaHandle,
        'tools' => $tools,
    ]);
    
    $this->trigger(self::EVENT_REGISTER_TOOLS, $event);
    
    return [
        'name' => 'Craft CMS MCP Wrapper',
        'version' => McpWrapper::getInstance()->getVersion(),
        'tools' => $event->tools,
    ];
}
```

**Example: Commerce Plugin Integration**
```php
// In a Commerce plugin:
use rocketpark\mcpwrapper\McpWrapper;
use rocketpark\mcpwrapper\events\RegisterToolsEvent;
use yii\base\Event;

Event::on(
    McpWrapper::class,
    McpWrapper::EVENT_REGISTER_TOOLS,
    function(RegisterToolsEvent $event) {
        $event->tools[] = [
            'name' => 'list_products',
            'description' => 'List Commerce products',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer'],
                    'type' => ['type' => 'string'],
                ],
            ],
        ];
    }
);
```

**Priority:** 🟢 **MEDIUM - Week 7-8**

---

### Priority 4: Architecture Improvements (8+ Weeks)

#### 4.1 Consider stdio Transport Option

**Problem:** HTTP-only limits use cases.

**Solution:** Support both transports.

**Implementation:**
```php
// bin/mcp-server (new file)
#!/usr/bin/env php
<?php
// CLI entry point for stdio transport
define('CRAFT_BASE_PATH', dirname(__DIR__, 2));
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

require_once CRAFT_VENDOR_PATH . '/autoload.php';
require_once CRAFT_VENDOR_PATH . '/craftcms/craft/bootstrap.php';

$config = require CRAFT_BASE_PATH . '/config/app.php';
$config['components']['request'] = ['class' => 'craft\console\Request'];

$application = Craft::createObject($config);
$service = $application->getModule('mcpwrapper')->mcpServer;

// Run stdio server
$service->runStdio();
exit(0);
```

**Dual Transport Support:**
- HTTP: `/actions/mcp-wrapper/mcp/{schema}` (existing)
- stdio: `php vendor/rocketpark/mcp-wrapper/bin/mcp-server --schema=ai`

**Priority:** 🔵 **LOW - Week 8+** (nice-to-have, but HTTP works well)

---

#### 4.2 Performance: GraphQL Query Optimization

**Problem:** Each tool call makes a new GraphQL HTTP request.

**Solution:** Use Craft's native GraphQL execution.

**Current:**
```php
// Makes HTTP request to /api
$response = $this->guzzle->post($endpoint, [
    'json' => ['query' => $query],
    'headers' => ['Authorization' => "Bearer {$token}"],
]);
```

**Optimized:**
```php
// Direct execution (no HTTP overhead)
$schema = Craft::$app->getGql()->getSchemaByAccessToken($token);
$result = Craft::$app->getGql()->executeQuery($schema, $query, $variables);
```

**Performance Gain:** ~20-50ms per request (no HTTP round-trip)

**Priority:** 🟢 **MEDIUM - Week 5**

---

## Prioritized Feature Roadmap

### Phase 1: Production Security (Weeks 1-2) 🔴 CRITICAL

**Goal:** Make the plugin production-safe

| Task | Effort | Impact | Dependencies |
|------|--------|--------|--------------|
| IP Allowlisting | 2 days | Critical | None |
| Dangerous Tool Protection | 2 days | Critical | None |
| Audit Logging | 3 days | High | None |
| Error Sanitization | 1 day | Medium | None |
| Security Documentation | 1 day | High | All above |

**Deliverables:**
- ✅ Config option: `security.allowedIps`
- ✅ Config option: `enableMutations` (default false)
- ✅ Config option: `disabledTools`
- ✅ Log file: `@storage/logs/mcp-wrapper.log`
- ✅ Security event logging
- ✅ Production deployment guide

**Success Criteria:**
- Plugin can be safely deployed to production
- All dangerous operations disabled by default
- Complete audit trail of AI actions
- IP-restricted access

---

### Phase 2: MCP Feature Parity (Weeks 3-4) 🟡 HIGH

**Goal:** Implement missing MCP capabilities

| Task | Effort | Impact | Dependencies |
|------|--------|--------|--------------|
| MCP Prompts (3 core) | 4 days | High | None |
| MCP Resources (4 core) | 3 days | High | None |
| Completion Providers | 2 days | Medium | None |
| Update initialize response | 1 day | High | Prompts & Resources |

**Deliverables:**
- ✅ Prompts: schema_explorer, content_health, query_builder
- ✅ Resources: mcp://schema, mcp://sections, mcp://sections/{handle}, mcp://entries/{section}
- ✅ Completions: schema, section, field handles
- ✅ Updated capabilities in initialize handshake

**Success Criteria:**
- AI assistants can use prompts for guided workflows
- AI can reference resources without tool calls
- Auto-complete suggestions work in AI clients
- Manifest includes all MCP features

---

### Phase 3: System Tools & Admin (Weeks 5-6) 🟢 MEDIUM

**Goal:** Expand beyond content querying

| Task | Effort | Impact | Dependencies |
|------|--------|--------|--------------|
| System Info Tool | 1 day | Medium | None |
| Log Reader Tool | 2 days | High | Audit Logging |
| Cache Management Tool | 1 day | Medium | Dangerous Tools |
| Queue Status Tool | 1 day | Low | None |
| Project Config Tool | 1 day | Low | None |
| Config Management Tool | 1 day | Medium | None |
| GraphQL Query Optimization | 2 days | High | None |

**Deliverables:**
- ✅ 6 new system/admin tools
- ✅ Direct GraphQL execution (no HTTP)
- ✅ Enhanced debugging capabilities
- ✅ AI can diagnose system issues

**Success Criteria:**
- AI can inspect system health
- AI can read logs and diagnose errors
- AI can check queue status
- 30-50% performance improvement on tool calls

---

### Phase 4: Developer Experience (Weeks 7-8) 🟢 MEDIUM

**Goal:** Make setup effortless

| Task | Effort | Impact | Dependencies |
|------|--------|--------|--------------|
| Installation Wizard | 3 days | High | None |
| Client Config Generation | 2 days | High | Wizard |
| Test Connection Command | 1 day | Medium | None |
| Extension API | 2 days | Medium | None |
| Hot Reload Support | 1 day | Low | None |

**Deliverables:**
- ✅ Console command: `php craft mcp-wrapper/install`
- ✅ Auto-generates Claude Desktop / Cursor configs
- ✅ Console command: `php craft mcp-wrapper/test`
- ✅ Event: `EVENT_REGISTER_TOOLS`
- ✅ Plugin extension documentation
- ✅ Manifest cache invalidation

**Success Criteria:**
- Setup time < 3 minutes
- Client configs auto-generated
- Other plugins can add tools
- Zero manual client configuration

---

### Phase 5: Advanced Features (Weeks 9+) 🔵 OPTIONAL

**Goal:** Differentiate from competition

| Task | Effort | Impact | Dependencies |
|------|--------|--------|--------------|
| stdio Transport Support | 5 days | Medium | Phase 4 |
| Multi-Site Tools | 2 days | Low | Phase 3 |
| User Management Tools | 3 days | Low | Dangerous Tools |
| Asset Management Tools | 3 days | Medium | None |
| Revision History Tools | 2 days | Low | None |
| GraphQL Mutation Tools | 4 days | Medium | Dangerous Tools |
| Backup/Restore Tools | 3 days | Low | Dangerous Tools |

**Deliverables:**
- ✅ Both HTTP and stdio transports
- ✅ 15+ additional tools
- ✅ Full CRUD operations (opt-in)
- ✅ Competitive feature parity

**Success Criteria:**
- Feature parity with stimmtdigital/craft-mcp
- Unique advantages (HTTP transport, multi-schema)
- Production-proven at scale

---

## Key Differentiators to Maintain

### Our Unique Strengths

**1. HTTP/Web Transport**
- Web-based AI integrations (Botpress, Make, Zapier)
- Easier testing (curl/Postman)
- Horizontal scalability
- **Keep this!** It's a real advantage.

**2. Multi-Schema Routing**
- Multiple GraphQL schemas = multiple MCP "personalities"
- `/actions/mcp-wrapper/mcp/ai` - Full access
- `/actions/mcp-wrapper/mcp/public` - Read-only subset
- `/actions/mcp-wrapper/mcp/admin` - Admin tools
- **Unique feature!** They don't have this.

**3. Dynamic Tool Generation**
- Auto-adapts to site structure
- No manual tool creation
- Respects GraphQL permissions
- **Keep this!** Lower maintenance.

### Their Unique Strengths (Learn From)

**1. Security Model**
- Production-safe defaults
- Dangerous tool classification
- IP allowlisting
- **We need this!** Critical gap.

**2. MCP Feature Coverage**
- Prompts, Resources, Completions
- **We need this!** AI workflows improved.

**3. Extension Ecosystem**
- Event-based plugin integration
- **We need this!** Community growth.

**4. Developer Experience**
- Installation wizard
- Auto-configuration
- **We need this!** Lower adoption friction.

---

## Competitive Positioning

### When to Choose Our Plugin

✅ **Web-based AI integrations** (Botpress, n8n, Make)  
✅ **Multiple access levels** (need different schemas)  
✅ **Horizontal scaling** (load balanced web servers)  
✅ **Simpler architecture** (no process management)  
✅ **GraphQL-first** (already using GraphQL extensively)  
✅ **Testing/development** (easy to test with HTTP tools)

### When to Choose Their Plugin

✅ **Local AI assistants** (Claude Desktop, Cursor)  
✅ **Maximum tool catalog** (need 50+ pre-built tools)  
✅ **Read-only focus** (content analysis, documentation)  
✅ **Standard MCP protocol** (stdio preferred)  
✅ **Long-running sessions** (persistent connection)  
✅ **Offline usage** (no web server required)

### Our Target Niche

**Primary:** Web-based AI workflows + Multi-tenant access

**Secondary:** Content teams using AI assistants with multiple access levels

**Unique Value Prop:**
> "The only HTTP-based MCP server for Craft CMS with multi-schema routing, perfect for web-based AI integrations and fine-grained access control."

---

## Implementation Priority Matrix

```
                     HIGH IMPACT  │  LOW IMPACT
                                 │
    ┌─────────────────────────────┼─────────────────────┐
  H │ 🔴 IP Allowlisting         │ 🟡 Completion        │
  I │ 🔴 Audit Logging           │    Providers         │
  G │ 🔴 Dangerous Tools         │ 🟡 Hot Reload        │
  H │ 🟡 MCP Prompts             │ 🟢 Multi-Site Tools  │
    │ 🟡 MCP Resources           │                      │
  E │ 🟡 Installation Wizard     │                      │
  F │ 🟢 System Tools            │                      │
  F │ 🟢 GraphQL Optimization    │                      │
  O │ 🟢 Extension API           │                      │
  R │                            │                      │
  T │────────────────────────────┼──────────────────────│
    │ 🔵 stdio Transport         │ 🔵 Asset Tools       │
  L │ 🔵 Mutation Tools          │ 🔵 User Tools        │
  O │ 🔵 Backup Tools            │ 🔵 Revision Tools    │
  W │                            │                      │
    └─────────────────────────────┴─────────────────────┘

    Legend:
    🔴 Critical - Do First (Weeks 1-2)
    🟡 High - Do Next (Weeks 3-4)
    🟢 Medium - Then This (Weeks 5-8)
    🔵 Low - Nice to Have (Weeks 9+)
```

---

## Quick Wins (< 1 Day Each)

These high-value features can be implemented quickly:

1. **Error Sanitization** (2 hours)
   - Wrap all tool execution in try/catch
   - Return generic errors, log details
   
2. **Tool Metadata** (2 hours)
   - Add `meta` to tool definitions
   - Mark tools as readOnly/dangerous
   
3. **Config Validation** (2 hours)
   - Validate schema handles exist
   - Check token format
   - Warn on missing config
   
4. **Health Check Endpoint** (1 hour)
   - `/actions/mcp-wrapper/health`
   - Returns: version, status, tool count
   
5. **Documentation** (4 hours)
   - Security best practices
   - Multi-schema examples
   - Deployment guide
   
6. **Capability Reporting** (1 hour)
   - Update initialize response
   - Report: prompts, resources support

---

## Migration Path from Their Plugin

If users want to switch from stimmtdigital/craft-mcp:

### What They'll Gain
- ✅ HTTP/web access
- ✅ Multi-schema routing
- ✅ Web-based AI integrations
- ✅ Easier testing
- ✅ Horizontal scalability

### What They'll Lose
- ❌ 50+ pre-built tools (we have ~10)
- ❌ MCP Prompts (we'll add)
- ❌ MCP Resources (we'll add)
- ❌ stdio transport (HTTP only)
- ❌ Installation wizard (we'll add)

### Migration Steps

1. **Install both plugins** (they don't conflict)
2. **Map GraphQL schemas** to our config
3. **Test tools side-by-side**
4. **Update AI client config** (switch transport)
5. **Verify functionality**
6. **Uninstall old plugin** (when ready)

### Gradual Migration Strategy

**Week 1:** Install alongside, HTTP for web integrations  
**Week 2:** Test tool coverage gaps  
**Week 3:** Request missing tools / build custom  
**Week 4:** Switch AI clients to HTTP  
**Week 5:** Remove old plugin

---

## Code Examples & Patterns

### Example: Adding a Custom Tool (Post-Extension API)

```php
// modules/mymodule/Module.php
namespace modules\mymodule;

use Craft;
use rocketpark\mcpwrapper\McpWrapper;
use rocketpark\mcpwrapper\events\RegisterToolsEvent;
use yii\base\Event;

class Module extends \yii\base\Module {
    public function init(): void {
        parent::init();
        
        Event::on(
            McpWrapper::class,
            McpWrapper::EVENT_REGISTER_TOOLS,
            function(RegisterToolsEvent $event) {
                // Only for 'admin' schema
                if ($event->schemaHandle !== 'admin') {
                    return;
                }
                
                $event->tools[] = [
                    'name' => 'custom_analytics',
                    'description' => 'Get custom analytics data',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'startDate' => ['type' => 'string', 'format' => 'date'],
                            'endDate' => ['type' => 'string', 'format' => 'date'],
                        ],
                    ],
                    'handler' => [$this, 'handleAnalytics'],
                    'meta' => [
                        'category' => 'custom',
                        'dangerous' => false,
                        'source' => 'mymodule',
                    ],
                ];
            }
        );
    }
    
    public function handleAnalytics(array $arguments): array {
        $startDate = $arguments['startDate'] ?? null;
        $endDate = $arguments['endDate'] ?? null;
        
        // Your custom logic
        $data = $this->fetchAnalyticsData($startDate, $endDate);
        
        return [
            'data' => $data,
            'meta' => [
                'dateRange' => [$startDate, $endDate],
                'recordCount' => count($data),
            ],
        ];
    }
}
```

### Example: Multi-Schema Security Setup

```php
// config/mcpwrapper.php
return [
    // Public read-only access
    'schemas' => [
        'public' => getenv('GQL_PUBLIC_TOKEN'),  // Limited schema
        'ai' => getenv('GQL_AI_TOKEN'),          // Full read access
        'admin' => getenv('GQL_ADMIN_TOKEN'),    // Full access + mutations
    ],
    
    // Schema-specific security
    'security' => [
        'public' => [
            'allowedIps' => [],  // Allow all
            'enableMutations' => false,
            'disabledTools' => [],
        ],
        'ai' => [
            'allowedIps' => ['10.0.0.0/8'],  // Internal only
            'enableMutations' => false,
            'disabledTools' => [],
        ],
        'admin' => [
            'allowedIps' => ['127.0.0.1', '::1'],  // Localhost only
            'enableMutations' => true,
            'disabledTools' => [],
        ],
    ],
];
```

### Example: Custom Prompt

```php
// modules/mymodule/prompts/ContentStrategyPrompt.php
namespace modules\mymodule\prompts;

class ContentStrategyPrompt {
    public function getPrompt(): array {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $analysis = [];
        foreach ($sections as $section) {
            $analysis[] = [
                'section' => $section->name,
                'entries' => Entry::find()->section($section->handle)->count(),
                'avgPerMonth' => $this->calculateAvgPerMonth($section),
                'lastPublished' => $this->getLastPublished($section),
            ];
        }
        
        return [
            'name' => 'content_strategy_analysis',
            'description' => 'Analyze content strategy and publishing patterns',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
Analyze this content publishing data and provide strategic recommendations:

```json
{$this->formatJson($analysis)}
```

Please assess:
1. Publishing frequency and consistency
2. Section balance and content distribution
3. Content gaps and opportunities
4. Recommendations for content calendar
5. Resource allocation suggestions
PROMPT
                    ]
                ]
            ]
        ];
    }
}
```

---

## Testing Strategy

### Security Testing

```bash
# Test IP allowlisting
curl -H "Authorization: Bearer TOKEN" https://site.test/actions/mcp-wrapper/mcp/ai
# Expected: 403 if IP not allowed

# Test disabled tools
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"dangerous_tool"},"id":1}'
# Expected: Error "Tool disabled"

# Test mutation protection
# With enableMutations: false
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"create_entry"},"id":1}'
# Expected: Error "Mutations disabled"
```

### Functional Testing

```bash
# Test initialize
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-06-18"},"id":1}'

# Test tools/list
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}'

# Test prompts/list
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"prompts/list","params":{},"id":3}'

# Test resources/list
curl -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"jsonrpc":"2.0","method":"resources/list","params":{},"id":4}'
```

### Performance Testing

```bash
# Benchmark tool calls
time for i in {1..100}; do
  curl -s -X POST https://site.test/actions/mcp-wrapper/mcp/ai \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer TOKEN" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"query_news","arguments":{"limit":10}},"id":'$i'}' \
    > /dev/null
done

# Expected: < 5s for 100 requests (< 50ms avg)
```

---

## Documentation Needs

### User Documentation

1. **Installation Guide**
   - Requirements
   - Installation steps
   - Configuration
   - First connection

2. **Security Guide**
   - Production best practices
   - IP allowlisting setup
   - Multi-schema security
   - Audit log analysis

3. **Client Setup Guides**
   - Claude Desktop
   - Cursor
   - Cline
   - Custom HTTP clients

4. **Tool Reference**
   - Complete tool catalog
   - Input schemas
   - Example queries
   - Common patterns

### Developer Documentation

1. **Extension Guide**
   - Creating custom tools
   - Registering tools
   - Event system
   - Best practices

2. **Architecture Guide**
   - How it works
   - Request flow
   - Service structure
   - Caching strategy

3. **Migration Guide**
   - From stimmtdigital/craft-mcp
   - From custom solutions
   - Coexistence strategies

4. **API Reference**
   - Services
   - Events
   - Configuration options
   - Helper methods

---

## Conclusion & Next Steps

### Executive Summary

**Current State:**
- ✅ Working HTTP-based MCP server
- ✅ Dynamic tool generation
- ✅ Multi-schema routing
- ❌ Minimal security
- ❌ Limited tool catalog
- ❌ Missing MCP features (prompts, resources)

**After Phase 1-2 (4 weeks):**
- ✅ Production-safe with security controls
- ✅ Full MCP specification support
- ✅ Complete audit trail
- ✅ Enhanced AI workflows

**After Phase 3-4 (8 weeks):**
- ✅ Comprehensive tool catalog
- ✅ Effortless setup
- ✅ Plugin extensibility
- ✅ Optimized performance

**Our Advantage:**
- HTTP transport (unique)
- Multi-schema routing (unique)
- Dynamic tool generation (low maintenance)
- Web-based AI integrations (new market)

**Their Advantage:**
- Security model (we'll copy)
- MCP feature coverage (we'll implement)
- Tool catalog depth (we'll expand)
- Developer experience (we'll improve)

### Immediate Action Items

**This Week:**
1. Start Phase 1: Security implementation
2. Create GitHub issues for all Phase 1 tasks
3. Set up project board
4. Begin IP allowlisting feature

**Next Week:**
1. Complete Phase 1 security features
2. Test security controls
3. Update documentation
4. Start Phase 2: Prompts & Resources

**Within 30 Days:**
1. Complete Phase 1 & 2
2. Release v2.0.0 with security & MCP features
3. Announce production-ready status
4. Gather user feedback

### Success Metrics

**Security:**
- ✅ Zero security incidents in production
- ✅ Complete audit trail
- ✅ IP allowlisting active on all production sites

**Adoption:**
- 📈 50+ active installations in 6 months
- 📈 5+ community-contributed tools
- 📈 10+ web-based AI integrations

**Performance:**
- ⚡ < 50ms avg tool response time
- ⚡ < 5ms manifest read time (cached)
- ⚡ Support 100+ concurrent AI clients

**Developer Experience:**
- 🚀 < 3 min setup time (wizard)
- 🚀 Zero manual client configuration
- 🚀 Plugin extension API used by 3+ plugins

---

## Appendix: Tool Comparison Matrix

| Tool Category | Our Plugin | Their Plugin | Priority |
|---------------|------------|--------------|----------|
| **Content Querying** | ✅ Dynamic (GraphQL) | ✅ 10 tools | ✅ Have |
| **Content Mutations** | ❌ No | ✅ 3 tools | 🟢 Phase 5 |
| **System Info** | ❌ No | ✅ 7 tools | 🟢 Phase 3 |
| **Database** | ❌ No | ✅ 4 tools | 🔵 Phase 5 |
| **Debugging** | ❌ No | ✅ 7 tools | 🟢 Phase 3 |
| **Multi-Site** | ❌ No | ✅ 3 tools | 🔵 Phase 5 |
| **GraphQL** | ✅ Core | ✅ 4 tools | ✅ Have |
| **Assets** | ❌ No | ✅ 3 tools | 🔵 Phase 5 |
| **Backups** | ❌ No | ✅ 2 tools | 🔵 Phase 5 |
| **Commerce** | ❌ No | ✅ 6 tools | 🔵 Extension |
| **Self-Awareness** | ❌ No | ✅ 3 tools | 🟢 Phase 3 |
| **MCP Prompts** | ❌ No | ✅ 9 prompts | 🟡 Phase 2 |
| **MCP Resources** | ❌ No | ✅ 12 resources | 🟡 Phase 2 |
| **Completions** | ❌ No | ✅ 7 providers | 🟡 Phase 2 |

---

## Appendix: Configuration Examples

### Development Environment

```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'dev' => getenv('GQL_DEV_TOKEN'),
    ],
    
    'security' => [
        'allowedIps' => [],  // Allow all in dev
        'enabled' => false,  // Disable security for testing
    ],
    
    'enableMutations' => true,  // Allow write operations
    
    'logging' => [
        'level' => 'debug',
        'enabled' => true,
    ],
];
```

### Staging Environment

```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'staging' => getenv('GQL_STAGING_TOKEN'),
    ],
    
    'security' => [
        'allowedIps' => [
            '10.0.0.0/8',  // Internal network
        ],
        'enabled' => true,
    ],
    
    'enableMutations' => true,  // Test write operations
    
    'logging' => [
        'level' => 'info',
        'enabled' => true,
    ],
];
```

### Production Environment

```php
// config/mcpwrapper.php
return [
    'schemas' => [
        'public' => getenv('GQL_PUBLIC_TOKEN'),  // Read-only
        'admin' => getenv('GQL_ADMIN_TOKEN'),    // Full access
    ],
    
    'security' => [
        'public' => [
            'allowedIps' => [],  // Allow all for public
            'enabled' => false,
        ],
        'admin' => [
            'allowedIps' => [
                '127.0.0.1',
                getenv('ADMIN_IP_RANGE'),
            ],
            'enabled' => true,
        ],
    ],
    
    'enableMutations' => false,  // Read-only by default
    
    'disabledTools' => [
        // Disable any dangerous tools in production
    ],
    
    'logging' => [
        'level' => 'warning',  // Only log warnings/errors
        'enabled' => true,
        'rotateSize' => '10MB',
    ],
];
```

---

**End of Analysis**

Total Sections: 11  
Total Pages: ~50  
Total Implementation Time: 8-12 weeks  
Critical Path: Weeks 1-4 (Security + MCP Features)

---

*Generated: January 13, 2026*  
*Version: 1.0*  
*Comparison: rocketpark/mcp-wrapper vs stimmtdigital/craft-mcp v1.1.0*

