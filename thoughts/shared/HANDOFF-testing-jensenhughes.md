# Handoff: Jensen Hughes MCP Testing

**Created:** 2026-02-02
**From Session:** Comprehensive codebase testing
**Context Usage:** 87% at handoff

## What Was Completed

### Unit Tests - ALL PASS (106/106)
```bash
./tests/run-all-tests.sh
```
- IP Validator IPv4: 9 tests
- IP Validator IPv6 CIDR: 32 tests
- RequestTimeoutException: 10 tests
- Tool Registry: 3 tests
- Argument Mapping: 3 tests
- GraphQL Sanitization: 49 tests

### Code Review - COMPLETE
- All PHP files syntax validated (no errors)
- Security measures verified (IP allowlist, rate limiting, GraphQL sanitization)
- MCP protocol 2025-11-25 compliance confirmed
- Server version 2.1.0

### Demo MCP Testing - PASS
Tested via `mcp__craft-cms__` tools on demo instance:
- Basic queries, search, ID/slug filtering
- OrderBy, pagination, date filtering
- RelatedTo filtering, nested relations
- Empty result handling

## What Still Needs Testing

### Jensen Hughes Specific Tests (staging3.jensenhughes.com)

**1. Run the endpoint test script:**
```bash
cd /Users/elizabethstein/Projects/mcp-wrapper
./test-mcp-endpoint.sh https://staging3.jensenhughes.com MCPSchema
```

**2. Run Regional Leadership filter test:**
```bash
./test-regional-leadership-filter.sh
```

**3. Manual tests to verify:**

a) **Regional Leadership Count** - MUST be 59, not 101:
```bash
curl -X POST "https://staging3.jensenhughes.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "query_ourTeam",
      "arguments": {"limit": 100}
    },
    "id": 1
  }' | jq '.result.content[0].text | fromjson | .entries | length'
```
Expected: 59 (Regional Leaders only)

b) **Office Phone Numbers** - Must return REAL numbers:
```bash
curl -X POST "https://staging3.jensenhughes.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "craft_get_office_contact_info",
      "arguments": {"slug": "roseville"}
    },
    "id": 1
  }' | jq '.result.content[0].text | fromjson'
```
Expected: `"phone": "+1 925 938 3550"` (NOT null or placeholder)

c) **Privacy Check** - No personal emails exposed:
```bash
curl -X POST "https://staging3.jensenhughes.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "query_ourTeam",
      "arguments": {"limit": 5}
    },
    "id": 1
  }' | jq '.result.content[0].text' | grep -i "@jensenhughes.com"
```
Expected: No matches (emails should NOT be in response)

d) **Health endpoint:**
```bash
curl "https://staging3.jensenhughes.com/mcp/health?detailed=1" | jq
```

e) **Metrics endpoint:**
```bash
curl "https://staging3.jensenhughes.com/mcp/metrics"
```

## Key Files

- **Test scripts:** `test-mcp-endpoint.sh`, `test-regional-leadership-filter.sh`
- **Continuity ledger:** `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md`
- **Critical tool:** `src/tools/EntryTools.php` (craft_get_office_contact_info at line 358)
- **MCP Server:** `src/services/McpServerService.php`

## Jensen Hughes Location

The user mentioned Jensen Hughes is also in the "herd" folder - check if there's a separate project there that might have additional context or configuration.

## Success Criteria

1. `test-mcp-endpoint.sh` returns "ALL TESTS PASSED"
2. Regional Leadership count = 59
3. Office phone numbers are real (e.g., Roseville: +1 925 938 3550)
4. No @jensenhughes.com emails in API responses
5. Health endpoint returns "healthy"
6. Metrics endpoint returns Prometheus-format data

## Notes

- The MCP tools in Claude Code (`mcp__craft-cms__`, `mcp__servicecurator__`) connect to a DEMO instance, not Jensen Hughes
- Must use curl or the shell scripts to test the actual production endpoint
- IP allowlist may need your IP added if testing from new location
