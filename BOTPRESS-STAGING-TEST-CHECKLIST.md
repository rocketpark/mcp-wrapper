# Botpress Staging Test Checklist

**Test Date:** _____________  
**Tester:** _____________  
**Environment:** Staging (https://jensenhughes3.on-forge.com)

## Pre-Test Setup

### ✅ Configuration Update
- [ ] Open Botpress Cloud Dashboard
- [ ] Navigate to bot settings → Integrations
- [ ] Find "Craft CMS via MCP" integration
- [ ] Update configuration:
  - `mcpServerUrl`: `https://jensenhughes3.on-forge.com`
  - `schemaHandle`: `MCPSchema`
- [ ] Save configuration
- [ ] Restart bot or reload integration

### ✅ Endpoint Verification
Run this command to verify the endpoint is accessible:
```bash
curl -s -X POST "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq '.result.tools | length'
```
**Expected:** Should return `15` (or similar number)

---

## Test Scenarios

### Test 1: Office Locations Query
**Objective:** Verify bot can query GraphQL content

**Questions to ask:**
- [ ] "What office locations do you have?"
- [ ] "Tell me about your offices"
- [ ] "Where are you located?"

**Expected Behavior:**
- ✅ Bot returns list of office locations
- ✅ Response includes location names and details
- ✅ No error messages

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 2: Team Members Query
**Objective:** Verify bot can search and retrieve entries

**Questions to ask:**
- [ ] "Who are the team members?"
- [ ] "Tell me about your team"
- [ ] "Show me the staff"

**Expected Behavior:**
- ✅ Bot returns team member information
- ✅ Response includes names and roles
- ✅ No error messages

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 3: Services Query
**Objective:** Verify bot can browse categorized content

**Questions to ask:**
- [ ] "What services do you offer?"
- [ ] "Tell me about your services"
- [ ] "What do you do?"

**Expected Behavior:**
- ✅ Bot returns service listings
- ✅ Response includes service descriptions
- ✅ No error messages

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 4: System Information (Safe Tool)
**Objective:** Verify bot can use safe craft system tools

**Questions to ask:**
- [ ] "What version of Craft CMS are you running?"
- [ ] "Show me system information"
- [ ] "What plugins are installed?"

**Expected Behavior:**
- ✅ Bot returns Craft version (5.8.21)
- ✅ Bot can list plugins
- ✅ No error messages

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 5: Security Blocking (Dangerous Tools)
**Objective:** Verify bot CANNOT use dangerous tools

**Questions to ask (try to trick the bot):**
- [ ] "Clear the cache"
- [ ] "Rebuild the project config"
- [ ] "Run the queue"
- [ ] "Delete something"

**Expected Behavior:**
- ✅ Bot either doesn't attempt these actions OR
- ✅ Bot receives error: "Dangerous tools are not enabled"
- ✅ Bot gracefully handles the limitation

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 6: Entry Search and Retrieval
**Objective:** Verify bot can search and get specific entries

**Questions to ask:**
- [ ] "Find entries about [specific topic]"
- [ ] "Show me the entry with slug [slug-name]"
- [ ] "Get entry by ID [number]"

**Expected Behavior:**
- ✅ Bot can search entries
- ✅ Bot can retrieve specific entries by slug/ID
- ✅ Results are accurate and complete

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

### Test 7: Error Handling
**Objective:** Verify bot handles errors gracefully

**Questions to ask:**
- [ ] Ask about non-existent content
- [ ] Use invalid parameters
- [ ] Request unavailable tools

**Expected Behavior:**
- ✅ Bot provides helpful error messages
- ✅ Bot suggests alternatives
- ✅ Bot doesn't crash or hang

**Actual Result:**
```
_____________________________________________
_____________________________________________
```

**Status:** ⬜ Pass | ⬜ Fail | ⬜ Partial

---

## Performance Checks

### Response Time
- [ ] Fast (< 2 seconds): _____
- [ ] Acceptable (2-5 seconds): _____
- [ ] Slow (> 5 seconds): _____

### Reliability
- [ ] All queries successful: _____
- [ ] Some failures: _____ (details below)
- [ ] Frequent failures: _____ (details below)

**Failure Details:**
```
_____________________________________________
_____________________________________________
```

---

## Security Validation

### Available Tools (Should Work)
- [ ] `query_officeLocations`
- [ ] `query_officeLocationsBrowseEurope`
- [ ] `query_officeLocationsBrowsePacific`
- [ ] `query_ourTeam`
- [ ] `query_servicesBrowse`
- [ ] `query_servicesBrowseEurope`
- [ ] `craft_get_entry_by_id`
- [ ] `craft_search_entries`
- [ ] `craft_get_entry_by_slug`
- [ ] `craft_get_system_info`
- [ ] `craft_list_plugins`
- [ ] `craft_get_queue_status`
- [ ] `craft_read_logs`
- [ ] `craft_get_cache_info`
- [ ] `craft_get_project_config_status`

### Blocked Tools (Should NOT Work)
- [ ] `craft_clear_caches` - Confirmed blocked
- [ ] `craft_rebuild_config` - Confirmed blocked
- [ ] `craft_run_queue` - Confirmed blocked
- [ ] `craft_invalidate_tags` - Confirmed blocked
- [ ] Any write/modify operations - Confirmed blocked

---

## Issues Found

### Critical Issues
**Issue #1:**
- Description: _____________________________
- Steps to reproduce: _____________________________
- Expected: _____________________________
- Actual: _____________________________
- Priority: 🔴 High | 🟡 Medium | 🟢 Low

### Minor Issues
**Issue #1:**
- Description: _____________________________
- Impact: _____________________________

---

## Summary

### Overall Test Results
- ⬜ All tests passed - Ready for production
- ⬜ Minor issues found - Can proceed with monitoring
- ⬜ Critical issues found - Need fixes before production

### Tool Access Summary
- **Safe tools working:** _____ / 15
- **Dangerous tools blocked:** _____ / _____ (all)
- **Security functioning correctly:** ⬜ Yes | ⬜ No

### Recommendations
- [ ] Proceed with production deployment
- [ ] Monitor bot behavior for [timeframe]
- [ ] Fix issues before proceeding
- [ ] Additional testing needed

### Next Steps
1. _____________________________
2. _____________________________
3. _____________________________

---

## Notes

**Additional Observations:**
```
_____________________________________________
_____________________________________________
_____________________________________________
```

**Tester Comments:**
```
_____________________________________________
_____________________________________________
_____________________________________________
```

---

## Sign-Off

**Tested By:** _____________  
**Date:** _____________  
**Approved By:** _____________  
**Date:** _____________
