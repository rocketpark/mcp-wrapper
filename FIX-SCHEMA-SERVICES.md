# Fix: MCPSchema Using Wrong Services Section

## Problem
MCPSchema is currently exposing `servicesBrowse` instead of `services`.

**Evidence:**
- MCP tools list shows: `query_servicesBrowse` and `query_servicesBrowseEurope`
- GraphQL query for `servicesEntries` returns error: "Did you mean servicesBrowseEntries?"
- Bot instructions expect `query_services` but it doesn't exist

## Root Cause
MCPSchema GraphQL permissions have `servicesBrowse` checked instead of `services`.

---

## Fix Steps (In Craft CP)

### 1. Go to GraphQL Schema Settings
```
Craft CP → GraphQL → Schemas → MCPSchema
```

### 2. Update Section Permissions

**Find:** Services Browse section  
**Action:** ✅ **UNCHECK** this section

**Find:** Services section  
**Action:** ✅ **CHECK** this section

### 3. Save Schema

### 4. Clear Caches
Either:
- Run: `php craft clear-caches/all`
- Or in CP: Utilities → Clear Caches → GraphQL caches

### 5. Force Rebuild MCP Manifest
Visit:
```
https://jensenhughes3.on-forge.com/actions/mcp-wrapper/manifest/MCPSchema?force=1
```

---

## Verification

After making changes, run these tests:

### Test 1: Check Available Tools
```bash
curl -s "https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=MCPSchema" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  jq -r '.result.tools[] | select(.name | startswith("query_")) | .name' | grep service
```

**Expected:** Should show `query_services` (NOT `query_servicesBrowse`)

### Test 2: Query Services
```bash
curl -s "https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=MCPSchema" \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0",
    "method":"tools/call",
    "params":{
      "name":"query_services",
      "arguments":{"limit":5}
    },
    "id":2
  }' | jq '.result.content[0].text'
```

**Expected:** Should return services data

### Test 3: Verify GraphQL Directly
```bash
curl -s "https://jensenhughes3.on-forge.com/api" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer bWf8izIzpJvE8xiTKZ5ZHm3rJnhWq3PP" \
  -d '{"query": "{ servicesEntries(limit: 3) { id title slug } }"}' | jq .
```

**Expected:** Should return services entries (no errors)

---

## Update Bot Instructions After Fix

Once fixed, bot instructions should reference:
- ❌ OLD: `query_servicesBrowse` 
- ✅ NEW: `query_services`

File: `botpress-integration/AUTONOMOUS-NODE-INSTRUCTIONS-V4-WITH-PRIVACY.md`

Search for all instances of `servicesBrowse` and replace with `services`.

---

## Why This Matters

The **Public Schema** likely has:
- ✅ servicesBrowse (for public website)
- ❌ services (admin/internal only)

The **MCPSchema** should have:
- ✅ services (for AI bot)
- ❌ servicesBrowse (not needed)

This separation ensures:
1. Bot gets the right content structure
2. Public website uses different presentation
3. Permissions are properly scoped
