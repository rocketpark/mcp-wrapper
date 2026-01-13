# Comprehensive Testing Results - January 13, 2026

## Summary
Ran extensive tests on all key MCP server queries. Found **4 critical issues** that need fixing.

---

## ✅ WORKING CORRECTLY

### 1. Texas Office Search (Partial)
**Query:** `query_officeLocations` with `search: "Texas"`
- **Status:** Returns 2 of 3 Texas offices
- **Found:** Houston, Austin
- **Missing:** Allen

### 2. Roseville (California) Office Search  
**Query:** `query_officeLocations` with `search: "Roseville"`
- **Status:** ✅ Works perfectly
- **Found:** 1 office with correct region hierarchy: `['North America', 'United States', 'West', 'California']`

### 3. Office Locations by Limit  
**Query:** `query_officeLocations` with `limit: 100`
- **Status:** ✅ Returns all 97 offices
- **Texas offices found:** Allen, Austin, Houston (all 3)

---

## ❌ CRITICAL ISSUES FOUND

### Issue #1: Allen Office Missing from "Texas" Search
**Problem:** When searching for "Texas", Allen doesn't appear even though it has Texas in region[] field

**Test Results:**
```
Search: "Texas" → Returns: Houston, Austin (2 offices)
Expected: Houston, Austin, Allen (3 offices)
```

**Root Cause:** The search filter in the integration is checking:
- `title` field - "Allen" doesn't contain "Texas" ❌
- `slug` field - "allen" doesn't contain "Texas" ❌  
- `officeSummary` field - Summary doesn't mention "Texas" ❌
- `region[].title` field - Allen HAS Texas in regions ✅

**Why it's inconsistent:** Houston and Austin likely have "Texas" in their office summary or slug, but Allen doesn't.

**Fix Required:** 
1. Verify Allen office actually has Texas in the region[] field
2. If yes, the search logic is working correctly by region matching
3. If no, need to add Texas to Allen's region[] field in Craft CMS

---

### Issue #2: State Name Search Doesn't Work
**Problem:** Searching by state name returns 0 results

**Test Results:**
```
Search: "California" → Returns: 0 offices
Search: "Roseville" → Returns: 1 office (Roseville, California)
```

**Root Cause:** The search is matching against `title`, `slug`, `officeSummary`, and `region[].title` fields. When user searches "California", they expect offices in California region to be found.

**Fix Required:** The integration's search logic should prioritize region matching or make it more flexible.

---

### Issue #3: Services Queries Fail
**Problem:** Service queries return errors or 0 results

**Test Results:**
```
query_services with search: "code" → GraphQL Error: "services_service_Entry" type not registered  
query_servicesBrowse with search: "code" → Returns: 0 services
query_servicesBrowse with no search → Returns: 0 services
```

**Root Cause:** 
1. The `query_services` tool is trying to query a GraphQL type that doesn't exist in the schema
2. The `query_servicesBrowse` might be querying an empty section or wrong content type

**Impact:** 
- User asks "tell me about code consulting" → Bot can't find service pages
- This was a reported issue: podcasts appearing instead of service pages

**Fix Required:**
1. Check what GraphQL types exist for services in the schema  
2. Update integration to query correct service types
3. Test that service searches return actual service pages not podcasts

---

### Issue #4: Podcast Queries Fail
**Problem:** Podcast queries throw GraphQL errors

**Test Results:**
```
query_podcasts with search: "code" → GraphQL Error
```

**Root Cause:** Similar to services issue - likely querying non-existent or unregistered GraphQL types

**Impact:** While podcasts shouldn't be prioritized for service queries, they should still work when explicitly requested

**Fix Required:**
1. Check podcast GraphQL types in schema
2. Fix podcast query type naming

---

## Next Steps

### Immediate Actions Required

1. **Fix Allen Office Region Data** (if missing)
   - Go to Craft CMS → Office Locations → Allen
   - Verify "Texas" is in the Regions field
   - If not, add it

2. **Fix Service Queries** (CRITICAL)
   - Check GraphQL schema for actual service types: `servicesBrowse_servicePage_Entry` or similar
   - Update integration src/index.ts to use correct type
   - This is breaking a core use case

3. **Investigate State Search Logic**
   - Consider if "California" search should find all CA offices
   - May need to enhance search to be more semantic

4. **Fix Podcast Queries**
   - Update to correct GraphQL type names

### Testing Priority

1. **HIGH:** Fix and test service queries - this is user-facing and broken
2. **HIGH:** Verify Allen office has Texas region 
3. **MEDIUM:** Improve state-level office searches
4. **LOW:** Fix podcast queries (less critical)

---

## Test Commands Used

```bash
# Test Texas offices
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"search":"Texas","limit":10}}}'

# Test California search
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"search":"California","limit":10}}}'

# Test services
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_servicesBrowse","arguments":{"search":"code","limit":10}}}'

# List all available tools
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

---

## Conclusion

**Limit 100 fix:** ✅ Successfully resolved - all 3 Texas offices now accessible via unlimited query

**New issues discovered:** 4 critical problems that need fixes:
1. Allen missing from "Texas" search (data or search logic issue)
2. State-level searches don't work (California example)  
3. Service queries completely broken (GraphQL type errors)
4. Podcast queries failing

**Recommendation:** Do NOT deploy to production bot until service queries are fixed. This is a critical use case.
