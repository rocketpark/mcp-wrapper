# Production Privacy Fix - Remove Staff Emails from Bot

## The Problem

**The bot is exposing internal staff email addresses** from the `formSubmissionNotificationEmail` field on Office Locations.

**Example:** Anaheim office shows:
```
vlanda@jensenhughes.com, ktoomey@jensenhughes.com, dchabot@jensenhughes.com, 
tpeterson@jensenhughes.com, MktgNaWestProposals@jensenhughes.com, jkang@jensenhughes.com, 
imartinez@jensenhughes.com, mike.johnson@jensenhughes.com, pmacknis@jensenhughes.com, 
dave.hall@jensenhughes.com
```

This field is for Freeform notifications and should **NEVER** be public.

---

## ✅ Solution: Exclude Field from MCPSchema

### Step 1: Log into Production Craft CP

1. Go to: `https://jensenhughes3.on-forge.com/admin`
2. Navigate to: **GraphQL → Schemas**
3. Click on **MCPSchema** (the schema your bot uses)

### Step 2: Configure Office Locations Permissions

1. Scroll down to **Sections** → **Office Locations**
2. Click to expand the section
3. Find and **UNCHECK** this field:
   - ❌ `formSubmissionNotificationEmail`

### Step 3: Save and Clear Caches

1. Click **Save** at the top of the page
2. SSH into production and run:
   ```bash
   cd /home/forge/jensenhughes3.on-forge.com
   php craft clear-caches/all
   ```

---

## ✅ Verification

Test that the field is no longer exposed:

```bash
curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"query_officeLocations","arguments":{"slug":["anaheim"],"limit":1}}}' | \
  jq -r '.result.content[0].text' | jq . | grep -i "formSubmissionNotificationEmail"
```

**Expected:** No output (field should not appear at all)

---

## Bot Instructions Status

✅ **Your bot instructions ARE properly configured** with:

```markdown
Privacy & Data Protection Rules

DO NOT share the following information even if retrieved:
❌ Email addresses (except general company contact emails)
❌ Direct phone numbers for individuals
❌ Internal notes or private fields
```

**However:** Bot instructions are a **backup layer only**. The proper solution is to **exclude the field at the GraphQL schema level** so the bot never receives the data in the first place.

### Why GraphQL Exclusion is Better:

1. **Security by Design** - Data never leaves the server
2. **Zero Trust** - Don't rely on bot following instructions
3. **Performance** - Less data transferred = faster responses
4. **Compliance** - Proper data minimization

---

## Other Fields to Review

While you're in the MCPSchema configuration, review these sections for any other sensitive fields:

### Office Locations
- ✅ Keep: `title`, `address`, `officeSummary`, `region`, `googleMapId`, `googleMyBusiness`
- ❌ Exclude: `formSubmissionNotificationEmail` (done), any other internal fields

### Our Team
- Review if personal contact information should be public
- Consider excluding direct emails/phones if they exist

### Services
- Usually safe to expose all service content

---

## Summary

**Action Required:** 
1. Log into Craft CP production
2. GraphQL → Schemas → MCPSchema
3. Office Locations → Uncheck `formSubmissionNotificationEmail`
4. Save + clear caches

**Status:**
- [x] Bot instructions updated (already done - good backup layer)
- [ ] GraphQL schema field excluded (requires CP access)
- [ ] Verification test (after exclusion)

**Priority:** HIGH - Internal contact info is currently exposed to all bot users
