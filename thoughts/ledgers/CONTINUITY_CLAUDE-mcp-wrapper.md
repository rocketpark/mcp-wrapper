# Session: mcp-wrapper
Updated: 2026-02-18T19:51:32.908Z

## Goal
Maintain and improve a production Craft CMS plugin that exposes content to AI assistants (particularly Botpress) via the Model Context Protocol (MCP). Success = stable plugin operation on Jensen Hughes production site with proper Regional Leadership filtering, accurate phone numbers, and privacy protection.

## Constraints
- **Tech Stack:** Craft CMS 5.0+, PHP 8.2+, TypeScript/Node (Botpress integration)
- **Framework:** Craft CMS plugin architecture with Yii2 base
- **Client:** Jensen Hughes (staging3.jensenhughes.com) - PRODUCTION CLIENT
- **Test Commands:**
  - `./test-mcp-endpoint.sh [URL] [SCHEMA]`
  - `./test-regional-leadership-filter.sh`
- **Build/Deploy:** Via Composer, deployed to Laravel Forge
- **Critical Requirements:**
  - Regional Leadership filtering (59 members, NOT all 101 team members)
  - Real office phone numbers (e.g., Roseville: +1 925 938 3550)
  - Privacy protection (NO personal @jensenhughes.com emails)
  - Botpress Knowledge Base integration

## Key Decisions

### Architecture
- **MCP Protocol Compliance:** Implements spec **2025-11-25** (JSON-RPC 2.0) - UPDATED
- **Dual Transport:** Modern JSON-RPC endpoint + Legacy SSE endpoint
- **Tool Generation:** Hybrid approach
  - Dynamic tools from GraphQL schema introspection (query_news, query_topics, etc.)
  - Manual tools via #[Tool] attributes (craft_get_office_contact_info, etc.)
- **Security:** IP allowlisting with CIDR support, dangerous tools disabled by default
- **Caching:** Redis/file-based for GraphQL schema introspection

### Critical Implementation Details
- **Regional Leadership:** teamMemberType field contains "Regional Leadership" - bot MUST filter to only show these 59 members
- **Office Phones:** Custom tool `craft_get_office_contact_info` bypasses GraphQL to return real numbers
- **Privacy:** contactEmails field not exposed in API responses
- **Error Handling:** Graceful fallbacks to regional office contact when no matches found

### Botpress Integration
- Deployed as private integration to client workspace
- 5 actions: listTools, queryContent, getEntry, intelligentSearch, answerQuestion
- Knowledge Base contains filtering instructions
- Must use craft_get_office_contact_info for office queries (NOT query_officeLocations search)

## State
- Done:
  - [x] Core MCP plugin development
  - [x] Regional Leadership filtering implementation (59 members identified)
  - [x] Real phone number tool (craft_get_office_contact_info)
  - [x] Privacy protection (email field removal)
  - [x] Botpress integration deployment
  - [x] Comprehensive testing (76% success rate, 13/17 tests passed)
  - [x] Production deployment to staging3.jensenhughes.com
  - [x] Documentation cleanup and test guides
  - [x] Security hardening (commit d635684):
    - Error message sanitization
    - SSE endpoint timeout protection
    - Input validation hardening
    - Rate limiting (100 req/min)
  - [x] MCP Best Practices Improvements (2026-01-27):
    - Updated to MCP protocol version 2025-11-25
    - Added health check endpoint (/mcp/health)
    - Added metrics endpoint (/mcp/metrics) - Prometheus compatible
    - Added IPv6 CIDR support to IpValidator
    - Added request timeout handling with configurable limits
    - Server version bumped to 2.1.0
  - [x] Unit Tests (2026-01-27):
    - IPv6 CIDR validation tests (32 tests) - tests/test-ip-validator-ipv6.php
    - RequestTimeoutException tests (10 tests) - tests/test-request-timeout-exception.php
    - Test runner script - tests/run-all-tests.sh
    - Updated test-mcp-endpoint.sh for v2.1.0 protocol/version
    - All 57 unit tests passing
  - [x] Security Testing (2026-02-02):
    - GraphQL sanitization tests (49 tests) - tests/test-graphql-sanitization.php
    - GraphQLSanitizer utility class - src/support/GraphQLSanitizer.php
    - Log injection fix (explicit space in whitelist regex)
    - All 106 unit tests now passing
  - [x] Documentation Cleanup (2026-02-02):
    - Deleted .history/ folder
    - Deleted redundant MD files (QUICK-START-TESTING, BOT-FIXES-ACTION-PLAN, BOTPRESS-TEST-CHECKLIST, CODEBASE-REVIEW-AND-IMPROVEMENTS, MCP-WRAPPER-OVERVIEW-AND-UPDATES)
    - Moved BOT-COMPREHENSIVE-TEST-QUESTIONS.md to tests/
  - [x] Codebase Review & Unit Testing (2026-02-02):
    - All 106 unit tests passing (run-all-tests.sh)
    - All PHP files syntax validated (no errors)
    - Code review of all security measures complete
    - Demo MCP instance tested via mcp__craft-cms__ tools (queries, filters, pagination work)
  - [x] Comprehensive Botpress Bot Testing (2026-02-09):
    - Tested 25+ live bot queries via Playwright
    - Pass rate: ~97% (18.5/19 tests)
    - Regional Leadership: PASS - correctly directs to website, no individual names listed
    - International Offices: PASS - KB working for London, Sydney, Mumbai, Seoul, Dubai, Europe
    - US Offices: PASS - Roseville, California offices all return real phones
    - Privacy: PASS - emails redirect to info@jensenhughes.com
    - Services/Industries: PASS - comprehensive responses
    - Results documented in BOTPRESS-COMPREHENSIVE-TEST-RESULTS-2026-02-09.md
- Now: [→] Botpress KB ready for production monitoring
- Next:
  - [x] Create services sync script (sync-services-to-botpress.js)
  - [x] Create industries sync script (sync-industries-to-botpress.js)
  - [x] Create services KB content (data/botpress-services.txt) - uploaded to Botpress
  - [x] Create industries KB content (data/botpress-industries.txt) - ready for upload
  - [ ] Upload industries KB to Botpress Studio (manual - user task)
  - [ ] Test services sync with DRY_RUN=true
  - [ ] Add services sync to Forge scheduler (monthly: 0 2 1 * *)
  - [ ] Monitor first 10-20 production conversations
- Future: Consider implementing MCP best practice improvements (tool annotations, error codes, OAuth)

## Working Set

### Key Files - Plugin Core
- `src/McpWrapper.php` - Main plugin class, service registration, routing
- `src/controllers/McpController.php` - JSON-RPC endpoint, IP validation, SSE support
- `src/services/McpServerService.php` - MCP protocol implementation (initialize, tools/list, tools/call)
- `src/services/ManifestBuilderService.php` - GraphQL schema introspection
- `src/services/ToolRegistryService.php` - Tool registration and discovery
- `src/tools/EntryTools.php` - craft_get_office_contact_info (CRITICAL for phone numbers)
- `src/tools/SystemTools.php` - System admin tools

### Key Files - Botpress Integration
- `botpress-integration/integration.definition.ts` - Integration schema and action definitions
- `botpress-integration/src/index.ts` - Implementation logic
- `botpress-integration/package.json` - Dependencies (@botpress/sdk, node-fetch)

### Testing
- `test-mcp-endpoint.sh` - Full endpoint testing suite (updated for v2.1.0)
- `test-regional-leadership-filter.sh` - Specific Regional Leadership filtering tests
- `tests/run-all-tests.sh` - Unit test runner (57 tests total)
- `tests/test-ip-validator.php` - IPv4 validation tests (9 tests)
- `tests/test-ip-validator-ipv6.php` - IPv6 CIDR validation tests (32 tests)
- `tests/test-request-timeout-exception.php` - Timeout exception tests (10 tests)
- `tests/test-tool-registry.php` - Tool discovery tests (3 tests)
- `tests/test-argument-mapping.php` - Argument mapping tests (3 tests)
- `FINAL-TEST-RESULTS.md` - Comprehensive test results (76% pass rate)

### Documentation (Cleaned 2026-02-02)
- `README.md` - Main plugin documentation (updated for MCP 2025-11-25)
- `CHANGELOG.md` - Version history
- `LICENSE.md` - MIT license
- `REGIONAL-LEADERSHIP-TESTING-GUIDE.md` - Critical filtering requirements
- `BOTPRESS-INSTRUCTIONS-CORRECTED.md` - Botpress Knowledge Base content
- `FINAL-TEST-RESULTS.md` - Production readiness assessment
- `tests/BOT-COMPREHENSIVE-TEST-QUESTIONS.md` - 165 test scenarios (moved from root)

### Configuration
- `.env` - MCP_GQLSCHEMA_TOKEN for GraphQL access
- `config/mcpwrapper.php` - Schema mapping, security settings, requestTimeout
- Plugin routes registered in McpWrapper::init()
  - `/mcp/{schemaHandle}` - Modern JSON-RPC endpoint
  - `/mcp/health` - Health check endpoint (NEW)
  - `/mcp/metrics` - Prometheus metrics endpoint (NEW)
  - `/mcp/manifest/{schemaHandle}` - Legacy manifest endpoint
  - `/actions/mcp-wrapper/mcp/sse/{schemaHandle}` - Legacy SSE transport

## Open Questions
None - system is production-ready with documented limitations.

Known limitations (with workarounds):
1. GraphQL search parameter only matches titles (not content) - Use craft_search_entries for broad searches
2. State-based office search doesn't work - Use craft_get_office_contact_info with slug instead
3. System info returns null for Craft version - Diagnostic only, no bot impact

### Future Improvements (from best practices research 2026-02-02)
**Craft CMS:**
- Add `declare(strict_types=1)` to PHP files
- Create Settings model with typed properties
- Add PHPStan with craftcms/phpstan for static analysis
- Consider Craft Pest for testing

**MCP Protocol:**
- Add tool annotations: `title`, `outputSchema`, `destructiveHint`, `idempotentHint`
- Use `-32002` error code for resource not found
- Add `$schema` field to input schemas (JSON Schema 2020-12)
- Consider Streamable HTTP transport (SSE deprecated)
- Plan for OAuth 2.1 support for enterprise deployments

## Codebase Summary

### Architecture
```
MCP Client (Botpress, Claude, etc.)
    ↓ JSON-RPC 2.0
McpController (/mcp/{schema})
    ↓
McpServerService (handles initialize, tools/list, tools/call)
    ↓
ManifestBuilderService (GraphQL introspection)
    ↓
ToolRegistryService (dynamic + manual tools)
    ↓
Craft CMS GraphQL API + Direct Content Access
```

### Component Breakdown

**Controllers (3):**
- McpController: Main JSON-RPC endpoint, IP validation, SSE support
- ManifestController: Legacy manifest endpoint
- UtilityController: CP utility interface

**Services (5):**
- McpServerService: Protocol implementation, request routing
- ManifestBuilderService: GraphQL schema introspection and caching
- ToolRegistryService: Tool registration, discovery, execution
- PromptRegistryService: MCP prompts (schema_explorer, content_health, query_builder)
- ResourceRegistryService: MCP resources (schema, sections, entries, volumes)

**Tools (2 classes, ~19 total tools):**
- EntryTools: Content access tools
  - craft_get_entry_by_id - Direct entry access
  - craft_search_entries - Full-text search
  - craft_get_entry_by_slug - Slug-based lookup
  - craft_get_office_contact_info - **CRITICAL** for real phone numbers
- SystemTools: Admin/diagnostic tools (7 tools)
  - craft_get_system_info, craft_list_plugins, craft_get_queue_status, etc.
- Dynamic Tools: Auto-generated from GraphQL (10-20 tools)
  - query_news, query_topics, query_officeLocations, query_ourTeam, query_services, etc.

**Support Classes:**
- IpValidator: CIDR-aware IP allowlist validation (IPv4 + IPv6)
- RateLimiter: IP-based rate limiting with cache backend
- RequestTimeoutException: Custom exception for timeout handling
- Response: MCP response formatting
- SafeExecution: Error boundary wrapper for tools

### Critical Data Points
- **Team Members:** 101 total, 59 Regional Leaders
- **Offices:** 97 worldwide
- **Services:** 20 listed
- **Test Results:** 76% pass rate (13/17 tests), production ready
- **Performance:** <1s average response time
- **Security:** IP allowlisting, dangerous tools disabled, email privacy protected

### Botpress Integration
- **Deployment:** Private integration to Jensen Hughes workspace
- **Actions:** 5 (listTools, queryContent, getEntry, intelligentSearch, answerQuestion)
- **Critical Instructions:** Filter to Regional Leadership, use craft_get_office_contact_info for phones
- **Endpoint:** https://servicecurator.com or staging3.jensenhughes.com
- **Schema Handles:** ai, frontend, internal (configured in Craft)

### Entry Points
- Main endpoint: `/mcp/{schemaHandle}` (routes to McpController::actionIndex)
- Plugin initialization: `src/McpWrapper.php::init()`
- Tool registration: McpWrapper::registerToolClasses()
- Test suite: `./test-mcp-endpoint.sh`

### Tech Stack Details
- **PHP:** 8.2+ with Craft CMS 5.0+ plugin architecture
- **Composer:** Package: rocket-park/mcp-wrapper
- **Dependencies:** GuzzleHttp for HTTP requests, Yii2 framework base
- **TypeScript:** Botpress integration uses @botpress/sdk, node-fetch
- **Deployment:** Via Composer to Laravel Forge
- **Caching:** Redis or file-based (Craft's cache component)
- **Logging:** Custom log file at @storage/logs/mcpwrapper.log

### Production Status
**✅ PRODUCTION READY**
- All critical features working
- Regional Leadership filtering verified (59 members)
- Real phone numbers confirmed (Roseville, Oakland, Mumbai)
- Privacy protection active (no email leaks)
- Comprehensive testing completed
- Known limitations documented with workarounds
- Botpress Knowledge Base updated with correct instructions

**Next Deployment Steps:**
1. Monitor first 10-20 conversations
2. Verify Regional Leadership filtering in practice
3. Confirm real phone numbers displaying
4. Validate no privacy leaks in production

## Agent Reports

### onboard (2026-01-27T14:19:23.924Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:19:07.338Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:16:21.217Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:15:20.987Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:11:09.897Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:09:22.582Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:08:36.208Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

### onboard (2026-01-27T14:06:15.953Z)
- Task: 
- Summary: 
- Output: `.claude/cache/agents/onboard/latest-output.md`

