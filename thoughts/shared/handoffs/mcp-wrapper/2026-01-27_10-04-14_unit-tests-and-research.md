---
date: 2026-01-27T15:04:14Z
session_name: mcp-wrapper
researcher: Claude
git_commit: 15ee9dc80a45d929dbefdecc6de011b44d096e37
branch: feature/mcp-improvements
repository: mcp-wrapper
topic: "MCP Wrapper Unit Tests and Best Practices Research"
tags: [mcp, testing, security, botpress, craft-cms]
status: complete
last_updated: 2026-01-27
last_updated_by: Claude
type: implementation_strategy
root_span_id:
turn_span_id:
---

# Handoff: Unit Tests Complete + MCP Best Practices Research

## Task(s)

| Task | Status |
|------|--------|
| Add unit tests for v2.1.0 features | ✅ Complete |
| Research MCP best practices | ✅ Complete |
| Compare against official craft-mcp plugin | ✅ Complete |
| Test Botpress integration | 🔄 Next |

### Current Phase
Unit tests added, research complete. Ready to test Botpress integration.

## Critical References
- `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md` - Main continuity ledger
- `BOTPRESS-INSTRUCTIONS-CORRECTED.md` - Production Botpress Knowledge Base instructions
- `.claude/cache/agents/research-agent/latest-output.md` - Full MCP research report

## Recent changes

```
tests/test-ip-validator-ipv6.php:1-200 - NEW: 32 IPv6 CIDR validation tests
tests/test-request-timeout-exception.php:1-80 - NEW: 10 timeout exception tests
tests/run-all-tests.sh:1-50 - NEW: Test runner script
.gitignore:37-43 - Updated: Root-only patterns for test scripts
test-mcp-endpoint.sh:45,59,168-169 - Updated: Protocol v2025-11-25, server v2.1.0
```

## Learnings

### MCP Security Gaps (From Research)
1. **OAuth 2.1 Required** - Per MCP 2025-11-25 spec for remote HTTP servers
2. **Tool Execution Limits Missing** - Need max 5 concurrent, 60/min per user
3. **SSE Transport Deprecated** - Should migrate to Streamable HTTP

### Comparison with Official craft-mcp Plugin
| Aspect | craft-mcp | mcp-wrapper |
|--------|-----------|-------------|
| Tools | 50 (15 tool files) | ~19 (2 tool files + dynamic) |
| Transport | STDIO (local) | HTTP/SSE (remote) |
| Use Case | AI coding assistants | Botpress/remote AI |
| Dynamic GraphQL | No | Yes (unique strength) |

### Test Infrastructure
- No PHPUnit - uses custom PHP scripts with manual assertions
- Pattern: `tests/test-*.php` with exit codes
- .gitignore had `test-*.php` which needed `/test-*.php` (root-only)

## Post-Mortem

### What Worked
- Research agent efficiently gathered MCP best practices from multiple sources
- IPv6 CIDR testing was straightforward using existing pattern
- ccpi CLI installed for future plugin management

### What Failed
- Tried: MCP Inspector CLI for HTTP endpoint → Requires STDIO, not HTTP transport
- Tried: Direct curl to staging3.jensenhughes.com → IP restricted or not responding
- Tried: @haakco/mcp-testing-framework → Not published to npm yet

### Key Decisions
- Decision: Follow existing test pattern (custom PHP) instead of adding PHPUnit
  - Alternatives: PHPUnit, Codeception
  - Reason: Consistency with existing tests, no framework setup needed

- Decision: Root-only .gitignore patterns (`/test-*.php`) instead of negation
  - Alternatives: `!tests/test-*.php` negation
  - Reason: Simpler, more explicit

## Artifacts

### New Files Created
- `tests/test-ip-validator-ipv6.php` - 32 IPv6 CIDR validation tests
- `tests/test-request-timeout-exception.php` - 10 timeout exception tests
- `tests/run-all-tests.sh` - Test runner (57 total tests)

### Files Modified
- `.gitignore` - Root-only patterns
- `test-mcp-endpoint.sh` - Updated for v2.1.0
- `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md` - Updated state

### Research Output
- `.claude/cache/agents/research-agent/latest-output.md` - Full research report with:
  - Security checklist (OWASP, SlowMist)
  - Feature comparison vs top MCP servers
  - Testing patterns from production servers
  - Observability recommendations

## Action Items & Next Steps

### Immediate (This Session or Next)
1. **Test Botpress Integration** - Use MCP tools to verify:
   - `craft_get_office_contact_info` returns real phone numbers
   - `query_ourTeam` with Regional Leadership filter works
   - Privacy protection (no emails exposed)

### High Priority (Security)
2. **Add Tool Execution Limits** - Prevent runaway agent loops
   - Max 5 concurrent per user
   - Max 60 per minute per user

3. **Implement OAuth 2.1** - Required per MCP 2025-11-25 spec

### Medium Priority (Features)
4. **Migrate SSE to Streamable HTTP** - SSE is deprecated
5. **Add OpenTelemetry Tracing** - For distributed debugging
6. **Enhanced Metrics** - Request duration histograms, error counters

### When Ready to Merge
7. Merge `feature/mcp-improvements` → `craft-5`
8. Deploy to production
9. Monitor first 10-20 conversations

## Other Notes

### MCP Servers Configured in This Environment
Two MCP servers available for testing:
- `mcp__servicecurator__query_news` / `query_topics`
- `mcp__craft-cms__query_news` / `query_topics`

These connect to servicecurator.com (running v1.0.0, not v2.1.0 yet).

### Installed Tools
- `ccpi` CLI installed globally for plugin management
- Marketplace not yet added (needs `/plugin marketplace add jeremylongshore/claude-code-plugins`)

### Key URLs
- Production: https://servicecurator.com or staging3.jensenhughes.com
- craft-mcp plugin: https://github.com/stimmtdigital/craft-mcp
- MCP Inspector: `npx @modelcontextprotocol/inspector`

### Branch Status
```
feature/mcp-improvements: 3 commits ahead of origin
  - 15ee9dc test: Add unit tests for IPv6 CIDR and RequestTimeoutException
  - 00e2dbd docs: Update continuity ledger with MCP improvements
  - 6f51e48 feat(mcp): Update to MCP 2025-11-25 spec with observability endpoints
```
