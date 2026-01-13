# Botpress Bot Testing Guide

## ✅ Backend Verification Complete

All MCP server endpoints tested and working:
- ✅ Houston office: Has "Texas" in title/summary → Found by Craft CMS search
- ✅ Austin office: Has "Texas" in title/summary → Found by Craft CMS search  
- ✅ Allen office: Only has "Texas" in region field → Found by integration filtering
- ✅ California office (Roseville): Has "California" in region → Working correctly

**Integration Status**: ✅ Deployed (v1.0.0) with region-based filtering

---

## 🎯 Bot Testing Checklist

### Step 1: Update Bot Instructions

1. Go to **Botpress Studio** → Your Bot → **Instructions** tab
2. **Copy lines 7-147** from [BOTPRESS-BOT-INSTRUCTIONS.md](./BOTPRESS-BOT-INSTRUCTIONS.md)
3. Paste into the Instructions panel (replace existing content)
4. Verify these key sections are present:
   - `limit: 50` for office queries
   - Service page prioritization rules
   - Image handling rules

### Step 2: Verify Integration Configuration

In Botpress Studio → **Integrations** → **Craft CMS via MCP**:
- **MCP Server URL**: `https://jensenhughes3.on-forge.com`
- **Schema Handle**: `jensenhughes`

### Step 3: Publish Bot

Click the **Publish** button in Botpress Studio

### Step 4: Clear Browser Cache

**CRITICAL**: The webchat widget caches aggressively
- Option A: Open site in **incognito/private window**
- Option B: Hard refresh: `Cmd+Shift+R` (Mac) / `Ctrl+Shift+F5` (Windows)

---

## 🧪 Test Scenarios

### Test 1: Texas Offices (All 3)
**What to ask**: "Show me your Texas offices" or "Where are your Texas locations?"

**Expected Result**:
```
Jensen Hughes has 3 offices in Texas:

1. Houston
   - Services: [list of services]
   
2. Austin  
   - Services: [list of services]
   
3. Allen
   - Services: [list of services]
```

**Why this tests matters**: 
- Verifies `limit: 50` is being used
- Verifies region field filtering works
- Previously only returned 2/3 offices

---

### Test 2: California Office
**What to ask**: "Where is your California office?"

**Expected Result**:
```
Jensen Hughes has 1 office in California:

Roseville
- Address: [full address]
- Phone: [phone number]
- Services: [services]
```

**Why this test matters**:
- Verifies region-based search works
- Office name is "Roseville" not "California"

---

### Test 3: Service Page Priority
**What to ask**: "Tell me about code consulting" or "Learn more about fire protection engineering"

**Expected Result**:
- Bot provides service description
- Links to **service page** (NOT CodeCast podcast or articles)
- May offer case studies or expert contacts

**Why this test matters**:
- Jonathan reported podcasts appearing instead of service pages
- Service pages should be Priority 1

---

### Test 4: See All Locations Button
**What to do**: Click the "See all locations" button if it appears in chat

**Expected Result**:
- Displays all office locations
- No blank images in carousel/list
- Each office has proper formatting

**Why this test matters**:
- Jonathan reported blank images appearing
- Tests image handling rules

---

### Test 5: General Service Inquiry  
**What to ask**: "What services do you offer?"

**Expected Result**:
- Lists 5-10 core services
- Brief descriptions for each
- Offers to provide more details

**Why this test matters**:
- Tests general content retrieval
- Verifies bot can list and explain services

---

## 🐛 Troubleshooting

### If Test 1 Fails (Texas offices)
**Only shows 2 offices instead of 3**:
1. Check bot instructions have `limit: 50` in office queries
2. Verify bot was republished after updating instructions
3. Check browser cache was cleared

**Shows no offices**:
1. Verify integration configuration (URL and schema handle)
2. Check Botpress logs in Developer Console (F12)

### If Test 3 Fails (Service pages)
**Bot suggests podcasts instead of service pages**:
1. Check bot instructions have service prioritization rules
2. Verify Priority 1 is service page, not educational content
3. Republish bot after updating instructions

### If Test 4 Fails (Blank images)
**Carousel shows blank/broken images**:
1. Check bot instructions have image handling rule
2. Rule should say: "Only include images if entry has valid image URL"
3. Consider using text list with buttons instead

---

## 📊 Expected Test Results

| Test | Pass Criteria | Current Status |
|------|---------------|----------------|
| Texas Offices | All 3 shown (Houston, Austin, Allen) | ✅ Ready to test |
| California | Roseville office found | ✅ Ready to test |
| Service Pages | Links to service page not podcast | ✅ Ready to test |
| All Locations | No blank images | ✅ Ready to test |
| General Services | Lists 5-10 services | ✅ Ready to test |

---

## 🔄 If You Need to Rebuild/Redeploy

From `/Users/elizabethstein/Projects/mcp-wrapper/botpress-integration`:

```bash
# Build integration
npm run build

# Deploy to Botpress (auto-confirms)
npm run deploy -- --confirm
```

---

## 📝 Notes

- Integration version: **1.0.0**
- Deployed: January 13, 2026
- Key features: Region-based office filtering, service page prioritization, proper image handling
