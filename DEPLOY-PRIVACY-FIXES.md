# Deploy MCP Wrapper Privacy Fixes

## What's Fixed

**Problem:** Bot was exposing internal staff emails from `formSubmissionNotificationEmail` field on Office Locations

**Solution:** Code-level filtering to exclude sensitive fields from all MCP responses

---

## Deployment Steps

### 1. Update MCP Wrapper Plugin in jensenhughes Repo

```bash
cd /home/forge/jensenhughes3.on-forge.com

# Update mcp-wrapper to latest
composer update rocketpark/mcp-wrapper

# Apply any project config changes
php craft project-config/apply

# Clear all caches
php craft clear-caches/all
```

### 2. Verify Fix is Working

Test that sensitive field is no longer exposed:

```bash
curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"slug":["anaheim"],"limit":1}}}' | \
  jq -r '.result.content[0].text' | jq '.entries[0]' | grep -i "formSubmissionNotificationEmail"
```

**Expected:** No output (field should not appear)

**If field still appears:** Check logs at `storage/logs/web-*.log` for "Excluding sensitive field" messages to verify new code is running

---

## What Changed in MCP Wrapper

**Branch:** `feature/mcp-improvements`  
**Commits:**
- `30c5244`: Add Matrix field support for addresses
- `1e57486`: Exclude sensitive fields from MCP responses

**Files Modified:**
- `src/services/McpServerService.php`:
  - Added sensitive fields filtering in `getFieldsListForQuery()` 
  - Fields excluded: `formSubmissionNotificationEmail`, `formSubmissionNotificationEmail2`, `internalNotes`, `internalComments`, `adminNotes`
  - Added `filterSensitiveFields()` method as backup runtime filter
  - Improved Matrix field handling for address fields

---

## Testing Checklist

After deployment, test these scenarios:

### ✅ Privacy - Sensitive Fields Excluded
```bash
# Test Anaheim office doesn't expose staff emails
curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"slug":["anaheim"],"limit":1}}}' | \
  jq '.result.content[0].text' | jq '.entries[0]' | grep -v formSubmissionNotificationEmail
```

**Should return:** Office data WITHOUT `formSubmissionNotificationEmail` field

### ✅ Functionality - Office Queries Still Work
```bash
# Test office location query returns expected data
curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"search":"California","limit":10}}}' | \
  jq '.result.content[0].text' | jq '.entries | length'
```

**Should return:** Number of California offices (7)

### ✅ Matrix Fields - Addresses Work
```bash
# Test that address field is included (after Matrix field support added)
curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"slug":["anaheim"],"limit":1}}}' | \
  jq '.result.content[0].text' | jq '.entries[0].address'
```

**Should return:** Address data (currently might be empty/null until Matrix subfields are properly configured)

---

## Rollback Plan

If issues arise:

```bash
cd /home/forge/jensenhughes3.on-forge.com

# Rollback to previous version
composer require rocketpark/mcp-wrapper:1.0.0  # or whatever previous version was

php craft clear-caches/all
```

---

## Bot Instructions Status

✅ **Bot instructions already include privacy rules** (good defense-in-depth):

```markdown
DO NOT share the following information even if retrieved:
❌ Email addresses (except general company contact emails)
```

This code fix ensures data never reaches the bot in the first place (primary defense), while bot instructions serve as backup layer.

---

## Next Steps After Deployment

1. Monitor bot conversations to ensure no sensitive data leaks
2. Review other sections (Our Team, Services) for additional sensitive fields
3. Consider adding config option for sensitive fields list instead of hardcoding
4. Document field privacy guidelines for content editors

---

## Summary

**Priority:** HIGH - Fixes exposure of internal staff emails  
**Risk:** LOW - Only excludes specific sensitive fields, doesn't affect functionality  
**Testing:** Required after deployment to verify field exclusion  
**Rollback:** Simple via Composer downgrade if needed
