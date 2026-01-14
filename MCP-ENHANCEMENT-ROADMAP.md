# MCP Wrapper Enhancement Roadmap

## Executive Summary

After deep analysis of `stimmtdigital/craft-mcp` (50+ tools, stdio transport) vs. `rocketpark/mcp-wrapper` (HTTP/SSE, GraphQL-first), this roadmap identifies **critical improvements** while preserving your unique competitive advantages.

**Your Unique Strengths:**
- ✅ HTTP/SSE transport (browser-compatible, firewall-friendly, multi-client)
- ✅ Multi-schema routing (tenant isolation, permission boundaries)
- ✅ Dynamic tool generation (zero maintenance, auto-scales with schema changes)

**Critical Gaps to Address:**
- ❌ No security controls (IP filtering, tool disabling, dangerous operation protection)
- ❌ Single tool type (only GraphQL queries, no mutations, no Craft API access)
- ❌ No MCP Prompts (guided analysis patterns)
- ❌ No MCP Resources (URI-based content access)

---

## Phase 1: Security Hardening (CRITICAL - Week 1)

### 1.1 IP Whitelist System

**Why:** Prevent unauthorized API access, especially for mutations

**Implementation:**

```php
// config/mcp-wrapper.php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
    ],
    
    // NEW: Security controls
    'security' => [
        'allowedIps' => [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',  // Private network
        ],
        'requireAuth' => true,
        'enableDangerousTools' => false,  // Mutations, deletions
    ],
];
```

**Code Location:**
- Add to `ManifestController::actionIndex()` (line ~35)
- Add to `McpController` SSE endpoint

```php
// src/controllers/ManifestController.php
public function beforeAction($action): bool
{
    if (!parent::beforeAction($action)) {
        return false;
    }
    
    // Add IP validation
    $allowedIps = Craft::$app->config->getConfigFromFile('mcp-wrapper')['security']['allowedIps'] ?? [];
    $remoteIp = Craft::$app->request->getUserIP();
    
    if (!$this->validateIpAccess($remoteIp, $allowedIps)) {
        throw new ForbiddenHttpException('IP not whitelisted');
    }
    
    return true;
}

private function validateIpAccess(string $ip, array $allowedIps): bool
{
    foreach ($allowedIps as $allowed) {
        if (str_contains($allowed, '/')) {
            // CIDR notation
            if ($this->ipInRange($ip, $allowed)) {
                return true;
            }
        } elseif ($ip === $allowed) {
            return true;
        }
    }
    return false;
}
```

**Testing:**
```bash
# Should succeed from localhost
curl http://localhost/actions/mcpwrapper/manifest/ai

# Should fail from external IP
curl http://your-site.com/actions/mcpwrapper/manifest/ai
# Expected: 403 Forbidden
```

### 1.2 Disabled Tools Registry

**Why:** Prevent risky operations in production (delete entries, modify users)

```php
// config/mcp-wrapper.php (add to security section)
'disabledTools' => [
    'deleteEntry',
    'saveEntry',
    'deleteUser',
],
```

**Implementation:**
- Add to `ManifestBuilderService::generateManifest()`
- Filter out disabled tools before returning manifest

```php
// src/services/ManifestBuilderService.php (after line ~120)
$tools = $this->generateToolsFromSchema($introspectionResult);

// Filter disabled tools
$security = Craft::$app->config->getConfigFromFile('mcp-wrapper')['security'] ?? [];
$disabledTools = $security['disabledTools'] ?? [];

$tools = array_filter($tools, function($tool) use ($disabledTools) {
    return !in_array($tool['name'], $disabledTools);
});
```

### 1.3 Dangerous Operations Flag

**Why:** Separate read-only queries from mutations

```php
// src/services/ManifestBuilderService.php
private function generateToolsFromSchema($schema): array
{
    $tools = [];
    
    foreach ($schema['queries'] as $query) {
        $tools[] = [
            'name' => $query['name'],
            'description' => $query['description'],
            'inputSchema' => $this->generateInputSchema($query['args']),
            'dangerous' => false,  // NEW: Queries are safe
        ];
    }
    
    // NEW: Include mutations if enabled
    $security = Craft::$app->config->getConfigFromFile('mcp-wrapper')['security'] ?? [];
    if ($security['enableDangerousTools'] ?? false) {
        foreach ($schema['mutations'] as $mutation) {
            $tools[] = [
                'name' => $mutation['name'],
                'description' => '[⚠️ MUTATION] ' . $mutation['description'],
                'inputSchema' => $this->generateInputSchema($mutation['args']),
                'dangerous' => true,  // NEW: Mark mutations
            ];
        }
    }
    
    return $tools;
}
```

**Deliverable:** Secure API access with IP filtering, tool disabling, and mutation controls

---

## Phase 2: Tool Registry Architecture (HIGH - Week 2-3)

### 2.1 Attribute-Based Tool Discovery

**Why:** Enable manual tools alongside auto-generated GraphQL tools

**Pattern from craft-mcp:**

```php
// NEW FILE: src/attributes/Tool.php
<?php

namespace rocketpark\mcpwrapper\attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema = [],
    ) {}
}
```

**Usage:**

```php
// NEW FILE: src/tools/EntryTools.php
<?php

namespace rocketpark\mcpwrapper\tools;

use rocketpark\mcpwrapper\attributes\Tool;
use Craft;

class EntryTools
{
    #[Tool(
        name: 'craft_get_entry_by_id',
        description: 'Get full entry data by ID (bypasses GraphQL permissions)',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Entry ID'],
                'siteId' => ['type' => 'integer', 'description' => 'Site ID (optional)'],
            ],
            'required' => ['id'],
        ]
    )]
    public function getEntryById(int $id, ?int $siteId = null): array
    {
        $entry = Craft::$app->entries->getEntryById($id, $siteId);
        
        if (!$entry) {
            return ['found' => false, 'message' => 'Entry not found'];
        }
        
        return [
            'found' => true,
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'url' => $entry->url,
                'section' => $entry->section->handle,
                'status' => $entry->status,
                'postDate' => $entry->postDate?->format('Y-m-d H:i:s'),
                // Include ALL fields (not limited by GraphQL schema)
                'customFields' => $this->serializeCustomFields($entry),
            ],
        ];
    }
    
    private function serializeCustomFields($entry): array
    {
        $data = [];
        foreach ($entry->getFieldLayout()->getCustomFields() as $field) {
            $handle = $field->handle;
            $value = $entry->$handle;
            $data[$handle] = $this->serializeFieldValue($value);
        }
        return $data;
    }
}
```

### 2.2 Unified Tool Registry

**Why:** Merge auto-generated GraphQL tools with manual tools

```php
// NEW FILE: src/services/ToolRegistryService.php
<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use ReflectionClass;
use ReflectionMethod;
use rocketpark\mcpwrapper\attributes\Tool;

class ToolRegistryService
{
    private array $toolClasses = [];
    
    public function registerToolClass(string $className): void
    {
        $this->toolClasses[] = $className;
    }
    
    public function discoverManualTools(): array
    {
        $tools = [];
        
        foreach ($this->toolClasses as $className) {
            $reflection = new ReflectionClass($className);
            
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Tool::class);
                
                foreach ($attributes as $attribute) {
                    $toolAttr = $attribute->newInstance();
                    $tools[] = [
                        'name' => $toolAttr->name,
                        'description' => $toolAttr->description,
                        'inputSchema' => $toolAttr->inputSchema,
                        'handler' => [
                            'class' => $className,
                            'method' => $method->getName(),
                        ],
                    ];
                }
            }
        }
        
        return $tools;
    }
    
    public function getAllTools(string $schemaHandle): array
    {
        // Get GraphQL auto-generated tools
        $manifestBuilder = Craft::$app->getModule('mcp-wrapper')->get('manifestBuilder');
        $graphqlTools = $manifestBuilder->generateToolsFromGraphQL($schemaHandle);
        
        // Get manual tools
        $manualTools = $this->discoverManualTools();
        
        // Merge (manual tools can override GraphQL tools by name)
        return array_merge($graphqlTools, $manualTools);
    }
}
```

### 2.3 Safe Execution Wrapper

**Why:** Consistent error handling, prevent crashes

```php
// NEW FILE: src/support/SafeExecution.php
<?php

namespace rocketpark\mcpwrapper\support;

use Exception;

class SafeExecution
{
    public static function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }
}
```

**Deliverable:** Extensible tool system supporting both auto-generated GraphQL tools and manual Craft API tools

---

## Phase 3: MCP Prompts (HIGH - Week 4)

### 3.1 What Are MCP Prompts?

**Definition:** Pre-configured analysis templates that guide AI through common tasks.

**Example Use Case:** "Analyze this Jensen Hughes employee's expertise"

**Without Prompts (current):** AI must manually discover tools, formulate queries, combine data
**With Prompts:** AI loads template → fills in parameters → gets guided analysis

### 3.2 Implementation

```php
// NEW FILE: src/prompts/ContentPrompts.php
<?php

namespace rocketpark\mcpwrapper\prompts;

use rocketpark\mcpwrapper\attributes\Prompt;

class ContentPrompts
{
    #[Prompt(
        name: 'analyze_employee_expertise',
        description: 'Deep analysis of an employee\'s expertise and projects',
        arguments: [
            ['name' => 'employeeName', 'description' => 'Full name of employee', 'required' => true],
            ['name' => 'focusArea', 'description' => 'Specific expertise area to analyze', 'required' => false],
        ]
    )]
    public function analyzeEmployeeExpertise(string $employeeName, ?string $focusArea = null): string
    {
        return <<<PROMPT
You are analyzing the expertise and contributions of **{$employeeName}** at Jensen Hughes.

**Your Task:**
1. Find the employee entry using: `searchEntries` with query "{$employeeName}" and section "people"
2. Extract their:
   - Title and department
   - Areas of expertise
   - Related projects (via projectTeamMembers relationship)
   - Published articles/news mentions
3. Analyze their impact:
   - Number of projects involved in
   - Primary expertise areas
   - Thought leadership (publications, presentations)

{$this->getFocusAreaGuidance($focusArea)}

**Output Format:**
```markdown
# {$employeeName} - Expertise Analysis

## Profile
- **Title:** [title]
- **Department:** [department]
- **Core Expertise:** [list expertise areas]

## Project Involvement
[List projects with role/contribution]

## Thought Leadership
[Publications, presentations, articles]

## Impact Summary
[2-3 sentences on their unique value]
```

**Available Tools:**
- `searchEntries` - Find employee by name
- `getEntryById` - Get full employee details
- `getRelatedEntries` - Find related projects
PROMPT;
    }
    
    private function getFocusAreaGuidance(?string $focusArea): string
    {
        if (!$focusArea) {
            return '';
        }
        
        return <<<GUIDANCE

**Focus Area Requested:** {$focusArea}
- Prioritize projects/content related to {$focusArea}
- Highlight specific contributions in this area
- Compare their expertise vs. other team members in {$focusArea}
GUIDANCE;
    }
}
```

### 3.3 Manifest Integration

Add prompts to manifest alongside tools:

```php
// src/services/ManifestBuilderService.php
public function generateManifest(string $schemaHandle): array
{
    // Existing tool generation...
    $tools = $this->getAllTools($schemaHandle);
    
    // NEW: Add prompts
    $prompts = $this->discoverPrompts();
    
    return [
        'capabilities' => [
            'tools' => $tools,
            'prompts' => $prompts,  // NEW
            'resources' => [],  // Phase 4
        ],
    ];
}

private function discoverPrompts(): array
{
    $promptRegistry = Craft::$app->getModule('mcp-wrapper')->get('promptRegistry');
    return $promptRegistry->getAllPrompts();
}
```

**Deliverable:** Guided analysis templates for common Jensen Hughes queries

---

## Phase 4: MCP Resources (MEDIUM - Week 5)

### 4.1 What Are MCP Resources?

**Definition:** URI-based content access (like REST endpoints but for MCP)

**Example URIs:**
- `craft://entries/news/latest` → Latest 10 news articles
- `craft://entries/people/{slug}` → Employee profile
- `craft://sections/projects` → All project sections

**Why:** Faster than GraphQL queries, cacheable, standardized access patterns

### 4.2 Implementation

```php
// NEW FILE: src/resources/EntryResources.php
<?php

namespace rocketpark\mcpwrapper\resources;

use rocketpark\mcpwrapper\attributes\ResourceTemplate;
use Craft;

class EntryResources
{
    #[ResourceTemplate(
        uriTemplate: 'craft://entries/{section}/{slug}',
        name: 'Entry by section and slug',
        description: 'Get entry content directly by section handle and slug',
        mimeType: 'application/json'
    )]
    public function getEntryBySlug(string $section, string $slug): array
    {
        $entry = Craft::$app->entries->getEntryBySlug($slug, [
            'section' => $section,
        ]);
        
        if (!$entry) {
            return ['found' => false];
        }
        
        return [
            'found' => true,
            'entry' => $this->serializeEntry($entry),
        ];
    }
    
    #[ResourceTemplate(
        uriTemplate: 'craft://entries/{section}/latest?limit={limit}',
        name: 'Latest entries in section',
        description: 'Get most recent entries from a section',
        mimeType: 'application/json'
    )]
    public function getLatestEntries(string $section, int $limit = 10): array
    {
        $entries = Craft::$app->entries->getEntries([
            'section' => $section,
            'orderBy' => 'postDate DESC',
            'limit' => $limit,
        ]);
        
        return [
            'count' => count($entries),
            'entries' => array_map([$this, 'serializeEntry'], $entries),
        ];
    }
}
```

### 4.3 Resource Controller

```php
// NEW FILE: src/controllers/ResourceController.php
<?php

namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class ResourceController extends Controller
{
    protected array|int|bool $allowAnonymous = ['read'];
    
    public function actionRead(): Response
    {
        $uri = Craft::$app->request->getRequiredQueryParam('uri');
        
        // Parse URI: craft://entries/news/latest
        if (!str_starts_with($uri, 'craft://')) {
            throw new BadRequestHttpException('Invalid resource URI');
        }
        
        $path = substr($uri, 8); // Remove "craft://"
        
        $resourceRegistry = Craft::$app->getModule('mcp-wrapper')->get('resourceRegistry');
        $result = $resourceRegistry->resolveResource($path);
        
        return $this->asJson($result);
    }
}
```

**Deliverable:** URI-based content access for common patterns

---

## Phase 5: Developer Experience (LOW - Week 6+)

### 5.1 Installation Wizard

**Why:** Simplify setup for non-technical users

```php
// src/controllers/InstallController.php
public function actionIndex(): Response
{
    $config = [
        'hasConfig' => file_exists(Craft::$app->getConfig()->getConfigFilePath('mcp-wrapper')),
        'graphqlSchemas' => $this->getAvailableSchemas(),
        'currentToken' => getenv('GQL_AI_TOKEN'),
        'detectedProjects' => $this->detectProjectType(),
    ];
    
    return $this->renderTemplate('mcp-wrapper/install', $config);
}

private function detectProjectType(): string
{
    // Detect Botpress, Airia, generic
    if (file_exists(Craft::getAlias('@root/botpress-integration'))) {
        return 'botpress';
    }
    return 'generic';
}
```

### 5.2 Health Check Endpoint

```php
// Add to McpController
public function actionHealth(): Response
{
    return $this->asJson([
        'status' => 'ok',
        'version' => '1.0.0',
        'transport' => 'http',
        'capabilities' => ['tools', 'prompts', 'resources'],
        'schemas' => array_keys($this->getConfiguredSchemas()),
    ]);
}
```

### 5.3 Debug Logging

```php
// config/mcp-wrapper.php
'debug' => [
    'logRequests' => getenv('CRAFT_ENVIRONMENT') === 'dev',
    'logPath' => '@storage/logs/mcp-wrapper.log',
],
```

**Deliverable:** Easier onboarding, troubleshooting, monitoring

---

## Implementation Priorities

### Critical (Do First)
1. **IP Whitelisting** - Prevent unauthorized access
2. **Disabled Tools Registry** - Control what tools are exposed
3. **Dangerous Operations Flag** - Separate read/write operations

### High (Do Soon)
4. **Tool Registry Architecture** - Enable manual tools
5. **Entry/Asset Direct Access Tools** - Bypass GraphQL limitations
6. **MCP Prompts** - Guided analysis for common queries

### Medium (Nice to Have)
7. **MCP Resources** - URI-based content access
8. **Response Helpers** - Standardized output format
9. **Safe Execution** - Consistent error handling

### Low (Future)
10. **Installation Wizard** - Easier setup
11. **Health Check Endpoint** - Monitoring
12. **Debug Logging** - Troubleshooting

---

## Competitive Positioning

### What You Should NOT Copy

**❌ stdio Transport**
- Craft-mcp's approach: Requires SSH access, single-client, CLI-only
- Your advantage: HTTP works with firewalls, browsers, multi-client
- **Action:** Keep HTTP, ignore stdio entirely

**❌ 50+ Hand-Coded Tools**
- Craft-mcp's approach: Manually maintain tools for every Craft feature
- Your advantage: Auto-generate from GraphQL schema
- **Action:** Keep auto-generation, add selective manual tools only where GraphQL can't help

### What You SHOULD Adopt

**✅ Security Model**
- Their approach: IP filtering, disabled tools, dangerous flag
- Why: Your HTTP transport is more exposed than stdio
- **Action:** Implement Phase 1 immediately

**✅ Hybrid Tool Strategy**
- Their approach: Hand-coded tools for precision + GraphQL for flexibility
- Why: Some Craft features (drafts, revisions, matrix blocks) need direct API access
- **Action:** Implement tool registry (Phase 2)

**✅ MCP Prompts**
- Their approach: Pre-built analysis templates
- Why: Makes AI interactions more reliable and consistent
- **Action:** Build 5-10 prompts for Jensen Hughes use cases (Phase 3)

---

## Success Metrics

### Week 1 (Security)
- [ ] IP whitelist blocks unauthorized IPs
- [ ] Disabled tools don't appear in manifest
- [ ] Dangerous tools flagged in UI

### Week 3 (Tools)
- [ ] Manual tool `craft_get_entry_by_id` works
- [ ] GraphQL tools still auto-generate
- [ ] Both appear in same manifest

### Week 4 (Prompts)
- [ ] `analyze_employee_expertise` prompt loads in AI client
- [ ] Prompt provides structured analysis
- [ ] 5+ Jensen Hughes-specific prompts created

### Week 6 (Resources)
- [ ] `craft://entries/news/latest` returns JSON
- [ ] Resource access faster than GraphQL
- [ ] 10+ resource templates available

---

## Code Examples Repository

Create `/examples` directory:

```
examples/
  security/
    ip-whitelist-config.php
    disabled-tools-config.php
  tools/
    entry-tools-complete.php
    asset-tools.php
    user-tools.php
  prompts/
    employee-analysis.php
    project-overview.php
    news-summary.php
  resources/
    entry-resources.php
    asset-resources.php
```

---

## Questions for Decision

1. **Security Priority:** Should dangerous tools (mutations) be disabled by default?
   - **Recommendation:** Yes, require explicit `enableDangerousTools: true` in config

2. **Tool Registry:** Auto-discover tools from `src/tools/` or manually register?
   - **Recommendation:** Auto-discover like Craft-mcp (scan directory for `#[Tool]` attributes)

3. **Prompts:** Jensen Hughes-specific or generic Craft CMS?
   - **Recommendation:** Start with JH-specific, extract generic later

4. **Resources:** Implement full spec or minimal subset?
   - **Recommendation:** Minimal subset (entries, assets only)

5. **Breaking Changes:** Phase 1 security could break existing Botpress integration
   - **Recommendation:** Add `security.enabled: false` fallback for backward compatibility

---

## Next Steps

1. **Review this roadmap** with team
2. **Prioritize phases** based on business needs
3. **Create GitHub issues** for each phase
4. **Start with Phase 1** (security) immediately
5. **Test against Botpress** after each phase

---

## Appendix: File Structure After Enhancement

```
src/
  McpWrapper.php
  
  controllers/
    ManifestController.php        # Add IP validation
    McpController.php              # Add IP validation + health endpoint
    ResourceController.php         # NEW: URI-based access
    InstallController.php          # NEW: Setup wizard
  
  services/
    ManifestBuilderService.php     # Enhanced: merge GraphQL + manual tools
    ToolRegistryService.php        # NEW: Discover #[Tool] attributes
    PromptRegistryService.php      # NEW: Discover #[Prompt] attributes
    ResourceRegistryService.php    # NEW: Resolve craft:// URIs
  
  tools/                           # NEW: Manual tool classes
    EntryTools.php
    AssetTools.php
    UserTools.php
  
  prompts/                         # NEW: Prompt templates
    ContentPrompts.php
    AnalysisPrompts.php
  
  resources/                       # NEW: Resource providers
    EntryResources.php
    AssetResources.php
  
  attributes/                      # NEW: Attribute classes
    Tool.php
    Prompt.php
    ResourceTemplate.php
  
  support/                         # NEW: Helpers
    SafeExecution.php
    Response.php
    IpValidator.php
  
  templates/
    install.twig                   # NEW: Setup wizard
    utility.twig                   # Existing
```

---

## Questions?

Reach out with:
- Implementation questions
- Priority changes
- Architecture feedback
- Testing strategies
