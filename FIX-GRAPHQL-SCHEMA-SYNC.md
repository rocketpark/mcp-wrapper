# Fix: GraphQL Schema Out of Sync

## Problem

**Root Cause:** Production database GraphQL schema permissions are out of sync with project config YAML.

- **Project Config** (`config/project/graphql/schemas/f36b9985-ec33-427c-b40d-76701113f9a6.yaml`): Has `sections.89d0fe82-174c-4405-83bd-4244c33ad1e3:read` (Services)
- **Production Database**: MCPSchema only has `servicesBrowseEntries` query, NOT `servicesEntries`
- **Result**: Botpress bot instructions expect `query_services` but MCP wrapper returns `query_servicesBrowse`

## Verification

Check what entry types are actually available in production GraphQL:

```bash
curl -s "https://jensenhughes3.on-forge.com/api" \
  -H "Authorization: Bearer bWf8izIzpJvE8xiTKZ5ZHm3rJnhWq3PP" \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { types { name } } }"}' | \
  jq -r '.data.__schema.types[] | select(.name | contains("services")) | .name'
```

**Expected:**
- `services_Entry` (✅ this would mean Services is enabled)
- `servicesBrowse_Entry`

**Actual (WRONG):**
- `servicesBrowse_Entry` only
- `servicesBrowseEurope_Entry`
- NO `services_Entry` ❌

## Solution

### Option 1: Force Rebuild Project Config (RECOMMENDED)

SSH into production and force rebuild:

```bash
cd /home/forge/jensenhughes3.on-forge.com

# Backup current config
./craft db/backup

# Force rebuild project config from YAML files
./craft project-config/rebuild

# Apply the rebuilt config
./craft project-config/apply

# Clear all caches
./craft clear-caches/all

# Verify Services is now available
curl -s "http://localhost/api" \
  -H "Authorization: Bearer bWf8izIzpJvE8xiTKZ5ZHm3rJnhWq3PP" \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { types { name } } }"}' | \
  jq -r '.data.__schema.types[] | select(.name == "services_Entry")'
```

### Option 2: Manual CP Fix

1. Log into Craft CP on production: `https://jensenhughes3.on-forge.com/admin`
2. Go to **GraphQL → Schemas → MCPSchema**
3. **UNCHECK** "Services Browse" section
4. **CHECK** "Services" section  
5. Save
6. Run in terminal:
   ```bash
   ./craft clear-caches/all
   ./craft project-config/apply
   ```

### Option 3: Update Bot Instructions (WORKAROUND)

If Services Browse is the section you actually want, update bot instructions to use `query_servicesBrowse` instead of `query_services`.

## MCP Wrapper Bug

**Secondary Issue:** The MCP wrapper has a bug in how it determines which sections are available in a GraphQL schema.

**File:** `src/services/McpServerService.php` line 435

**Bug:**
```php
// WRONG: Checks if entry TYPE is available
$typeName = $entryType->handle . '_Entry';
if (in_array($typeName, $availableTypes, true)) {
    $accessibleSections[] = $section;
}
```

This checks whether an **entry type** is available, not whether the **section** is accessible. If two sections share the same entry type handle, both will be included even if only one section is enabled in the GraphQL schema.

**Fix:** Should check for section-specific query fields instead:

```php
// CORRECT: Check if section query field exists
$queryFieldName = $section->handle . 'Entries';
$queryFields = $this->getQueryFieldNames($token);
if (in_array($queryFieldName, $queryFields, true)) {
    $accessibleSections[] = $section;
}
```

## After Fix - Verification

1. Check MCP tools list:
   ```bash
   curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | \
     jq -r '.result.tools[] | select(.name | contains("service")) | .name'
   ```

   **Should show:** `query_services` (NOT `query_servicesBrowse`)

2. Test Services query:
   ```bash
   curl -s "https://jensenhughes3.on-forge.com/api" \
     -H "Authorization: Bearer bWf8izIzpJvE8xiTKZ5ZHm3rJnhWq3PP" \
     -H "Content-Type: application/json" \
     -d '{"query":"{ servicesEntries { id title } }"}' | jq .
   ```

   **Should return:** Services entries, not an error

## Status

- [x] Root cause identified: Database out of sync with project config
- [x] Verified via GraphQL introspection
- [x] MCP wrapper bug identified
- [ ] Requires SSH access to production to fix
- [ ] Alternative: Manual CP fix by user
