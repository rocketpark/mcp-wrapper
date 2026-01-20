# Jensen Hughes MCP Wrapper - Required Fixes

## Priority 1: Critical Bot Instruction Updates

### Fix Regional Office Search

**Location:** Botpress Autonomous Node Instructions (line ~210)

**Current (BROKEN):**
```
STEP 1: Try queryContent  
Input: {
  toolName: "query_officeLocations",
  search: "[State/City name]",  ❌ This doesn't work
  limit: 100
}
```

**Replace With:**
```
STEP 1: Try queryContent (Get ALL offices, then filter)
Input: {
  toolName: "query_officeLocations",
  limit: 100  // Get all offices
  // DON'T use search parameter - it only matches title/slug
}

STEP 2: Filter results by region field
// After receiving entries, check each entry's 'region' array
// Look for the state/city name in entry.region[].title
```

**Example Implementation:**
```javascript
// Get all offices
const response = await queryContent({
  toolName: "query_officeLocations",
  limit: 100
});

// Filter by region (client-side)
const californiaOffices = response.entries.filter(office => 
  office.region?.some(r => r.title.includes("California"))
);
```

---

## Priority 2: Services Content Issue

### Problem
`query_servicesBrowse` returns empty results. Bot will fail on service queries.

### Investigation Steps
1. **Check Craft CP:**
   - Go to Entries → Services Browse
   - Verify entries exist and are published (status: "live")

2. **Check GraphQL Schema Permissions:**
   - Go to GraphQL → Schemas → MCPSchema
   - Verify "Services Browse" section is checked/enabled
   - Verify all required fields are accessible

3. **Test Query Directly:**
```bash
curl -s "https://jensenhughes3.on-forge.com/api" \
  -H "Authorization: Bearer bWf8izIzpJvE8xiTKZ5ZHm3rJnhWq3PP" \
  -d '{"query": "{ servicesBrowseEntries(limit: 5) { title slug } }"}' | jq .
```

### If No Entries Exist
You'll need to either:
- Populate the Services Browse section with entries, OR
- Update bot instructions to query a different section, OR
- Remove service queries from bot capabilities

---

## Priority 3: Configuration Cleanup

### Delete Duplicate Config File
```bash
cd /Users/elizabethstein/Herd/jensenhughes
rm config/mcp-wrapper.php  # Keep mcpwrapper.php only
```

### Verify Active Config
File: `/config/mcpwrapper.php`
```php
<?php
return [
    'schemas' => [
        'jensenhughes' => getenv('MCP_GQLSCHEMA_TOKEN'),  // Not used by bot
        'MCPSchema'    => getenv('MCP_GQLSCHEMA_TOKEN'),  // ← Bot uses this
    ],
    
    'security' => [
        'enableDangerousTools' => false,  // ✅ Correct for production
        'disabledTools' => [],
        'ipWhitelist' => [],  // ✅ Empty = allow all (needed for Botpress)
    ],
];
```

---

## Priority 4: Bot Instructions Enhancement

### Add Fallback for Failed Service Queries

**Location:** After service query workflow

**Add This:**
```markdown
### If query_servicesBrowse Returns Empty

DO NOT say "I don't have that information"

INSTEAD:
1. Try craft_search_entries with search="[service keyword]"
2. If still empty: "I'm currently updating our services database. 
   Meanwhile, I can help you with:
   • Finding office locations
   • Connecting with team experts
   • General company information
   
   Or visit our main services page at [URL]"
```

---

## Testing Checklist

After implementing fixes, test these scenarios:

### Regional Search
- [ ] "Where is your California office?" → Should find Roseville
- [ ] "Show me Texas offices" → Should find Allen, Austin, Houston
- [ ] "European offices" → Should use query_officeLocationsBrowseEurope

### Services
- [ ] "What services do you offer?" → Should return services list
- [ ] "Tell me about fire protection" → Should find fire protection service
- [ ] If empty: Bot should use fallback response

### Team
- [ ] "Who are your fire experts?" → Should filter team by expertise
- [ ] Returns team members with names, locations, expertise

### System
- [ ] craft_search_entries works for general searches
- [ ] craft_get_entry_by_id returns full entry data
- [ ] No sensitive emails displayed (privacy check)

---

## Monitoring Recommendations

### 1. Enable MCP Logging
Add to `/config/app.php`:
```php
'components' => [
    'log' => [
        'targets' => [
            [
                'class' => craft\log\MonologTarget::class,
                'name' => 'mcp-wrapper',
                'categories' => ['mcp-wrapper'],
                'level' => 'info',
                'logContext' => true,
            ],
        ],
    ],
],
```

### 2. Monitor Bot Queries
Watch for these patterns in logs:
- Empty result sets (potential content issues)
- Failed queries (GraphQL errors)
- Slow response times (performance issues)

### 3. Performance Optimization
If queries are slow:
- Enable Redis caching for manifests
- Consider GraphQL query result caching
- Monitor database query performance

---

## Additional Optimizations (Optional)

### 1. Add Query Aliases
For common searches, add helper tools:

**File:** `/src/tools/EntryTools.php`
```php
#[Tool(
    name: 'craft_search_offices_by_region',
    description: 'Find offices by state/region name',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'region' => ['type' => 'string', 'description' => 'State or region name'],
            'limit' => ['type' => 'integer', 'default' => 100],
        ],
        'required' => ['region'],
    ],
    dangerous: false,
)]
public function searchOfficesByRegion(string $region, int $limit = 100): array
{
    // Get all offices
    $entries = Entry::find()
        ->section('officeLocations')
        ->limit($limit)
        ->all();
    
    // Filter by region
    $filtered = array_filter($entries, function($entry) use ($region) {
        $regions = $entry->region->all();
        foreach ($regions as $r) {
            if (stripos($r->title, $region) !== false) {
                return true;
            }
        }
        return false;
    });
    
    return Response::list('offices', $filtered);
}
```

### 2. Add Content Health Check Tool
```php
#[Tool(
    name: 'craft_check_content_health',
    description: 'Check if key sections have published content',
    dangerous: false,
)]
public function checkContentHealth(): array
{
    $sections = ['officeLocations', 'servicesBrowse', 'ourTeam'];
    $health = [];
    
    foreach ($sections as $sectionHandle) {
        $count = Entry::find()
            ->section($sectionHandle)
            ->status('live')
            ->count();
        
        $health[$sectionHandle] = [
            'count' => $count,
            'healthy' => $count > 0,
        ];
    }
    
    return Response::success($health);
}
```

---

## Summary

**Status:** MCP Wrapper is technically working, but has critical operational issues

**Must Fix:**
1. ✅ Update bot instructions for regional search (use all + filter)
2. ✅ Investigate/populate servicesBrowse content
3. ✅ Remove duplicate config file

**Should Fix:**
4. ✅ Add service query fallback in bot instructions
5. ✅ Enable logging for monitoring

**Optional:**
6. ⚪ Add custom regional search tool
7. ⚪ Add content health check tool

**Timeline:** Items 1-3 should be fixed immediately before production use.
