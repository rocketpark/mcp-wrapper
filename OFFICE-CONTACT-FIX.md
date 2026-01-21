# Office Contact Information Fix

## Problem
Botpress bot was showing headquarters phone number `(410) 737-8677` instead of office-specific phone numbers when users asked "What's the phone number for Roseville?"

## Root Cause
- Office phone numbers are stored in nested Craft CMS entries
- Required 3 sequential API calls:
  1. Get office entry by slug
  2. Get address entry by ID (from office.customFields.address[0].id)
  3. Get contactLinks entry by ID (from office.customFields.contactLinks[0].id)
  4. Parse phone number from HTML: `<a href="tel:+19259383550">+1 925 938 3550</a>`
- Botpress cannot reliably execute multi-step tool call workflows with intermediate state

## Solution
Created new MCP tool `craft_get_office_contact_info` that:
- Accepts office slug as parameter
- Performs all 3 lookups server-side
- Returns formatted contact data in single response

### Tool Response Example
```json
{
  "found": true,
  "contactInfo": {
    "slug": "roseville",
    "title": "Roseville",
    "uri": "contact/office-locations/roseville",
    "phone": "+1 925 938 3550",
    "address": "2281 Lave Ridge Court\nSuite 200, Office 23\nRoseville, CA 95661\nUSA",
    "addressLine1": "2281 Lave Ridge Court",
    "addressLine2": "Suite 200, Office 23",
    "city": "Roseville",
    "state": "CA",
    "zip": "95661",
    "country": "USA",
    "latitude": 38.747431,
    "longitude": -121.247018,
    "googleMapsUrl": "https://www.google.com/maps?q=38.747431,-121.247018",
    "contactFormUrl": "https://www.jensenhughes.com/contact/office-locations/form/roseville",
    "officeDetailsUrl": "https://jensenhughes3.test/contact/office-locations/roseville",
    "officeSummary": "<p>The Jensen Hughes Roseville office provides...</p>"
  }
}
```

## Files Changed

### 1. `/src/tools/EntryTools.php`
Added new tool method `getOfficeContactInfo()`:
- Uses `#[Tool]` attribute for auto-registration
- Wraps execution in `SafeExecution::run()`
- Calls `->all()` on EntryQuery objects for address and contactLinks
- Parses phone number from HTML using regex
- Returns comprehensive contact object

### 2. `/botpress-integration/BOTPRESS-INSTRUCTIONS-PRODUCTION.md`
- Added `craft_get_office_contact_info` to Available Tools section
- Replaced complex 3-step instructions with simple single-call approach
- Updated example showing real Roseville data
- Kept critical warning about NOT using headquarters number

## Testing

### Local Test (Passed ✅)
```bash
curl -X POST "http://jensenhughes.test/mcp/MCPSchema" \
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
**Result:** Returns phone `+1 925 938 3550` and complete address ✅

### Staging Deployment Steps

1. **Deploy to staging3:**
   ```bash
   ssh forge@jensenhughes3.on-forge.com
   cd jensenhughes3.on-forge.com
   git pull origin main  # After merging feature branch
   composer update rocket-park/mcp-wrapper --no-cache
   php craft clear-caches/all
   ```

2. **Verify tool is available:**
   ```bash
   curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}' \
     | jq '.result.tools[] | select(.name == "craft_get_office_contact_info")'
   ```

3. **Test tool on staging:**
   ```bash
   curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
     -H "Content-Type: application/json" \
     -d '{
       "jsonrpc":"2.0",
       "method":"tools/call",
       "params":{
         "name":"craft_get_office_contact_info",
         "arguments":{"slug":"roseville"}
       },
       "id":1
     }' | jq '.result.content[0].text | fromjson'
   ```
   Should return phone `+1 925 938 3550`

4. **Update Botpress instructions:**
   - Go to: https://app.botpress.cloud/agent/
   - Select bot workspace
   - Navigate to Agent Instructions
   - Copy content from `/botpress-integration/BOTPRESS-INSTRUCTIONS-PRODUCTION.md`
   - Paste into Instructions field
   - Click "Update" and "Publish"

5. **Test with Botpress:**
   - Open staging3 site: https://jensenhughes3.on-forge.com
   - Click Botpress chat widget
   - Ask: "What is the phone number for Roseville?"
   - **Expected:** Bot shows `+1 925 938 3550`
   - **Not:** Bot shows `(410) 737-8677` (headquarters)

## Other Offices to Test
- Syracuse: Should show office-specific number
- Oakland: Should show office-specific number
- Anaheim: Should show office-specific number

## Rollback Plan
If issues occur:
1. Revert to commit `b15c95e` (before tool was added)
2. Update composer on staging: `composer update rocket-park/mcp-wrapper --no-cache`
3. Bot will show headquarters number with contact form links (previous behavior)

## Success Criteria
✅ Tool returns real phone numbers (tested locally)
✅ Botpress instructions updated and simplified
✅ Token count reduced (removed complex 3-step workflow)
⏳ Staging deployment pending
⏳ Botpress bot test pending

## Next Steps
1. Merge `feature/mcp-improvements` to `main`
2. Deploy to staging3
3. Update Botpress instructions
4. Test with real user queries
5. If successful, deploy to production
