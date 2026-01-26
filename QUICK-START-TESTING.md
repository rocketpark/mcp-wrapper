# Quick Testing Guide - Regional Leadership & MCP Wrapper

**Date:** January 26, 2026  
**Status:** ✅ MCP Wrapper API verified working  
**Next Step:** Test Botpress bot with new instructions

---

## ✅ Step 1: MCP Wrapper API Tests (DONE)

```bash
cd /Users/elizabethstein/Projects/mcp-wrapper
bash test-regional-leadership-filter.sh
```

**Results:**
- ✅ 17 tools available
- ✅ teamMemberType field present in team member data
- ✅ Roseville office returns REAL phone: +1 925 938 3550
- ✅ Regional Leadership members identified (41 when searching "regional")

**Conclusion:** MCP Wrapper API is working correctly and ready for Botpress.

---

## 📋 Step 2: Update Botpress Instructions

1. **Open Botpress Dashboard**
   - Go to your bot configuration
   - Find the Knowledge Base or Instructions section

2. **Copy New Instructions**
   - Open: [BOTPRESS-INSTRUCTIONS-UPDATED.md](BOTPRESS-INSTRUCTIONS-UPDATED.md)
   - Select All (Cmd+A)
   - Copy (Cmd+C)

3. **Replace Existing Instructions**
   - Paste into Botpress
   - Save
   - Publish/Deploy the bot

---

## 🧪 Step 3: Test Botpress Bot

### Priority Tests (Do These First):

#### Test A: Regional Leadership Filtering 🚨 CRITICAL
**Open bot and ask:** "Who are your fire protection experts?"

**Expected:**
- Shows 1-2 Regional Leaders only (NOT all 20 experts)
- Example: Ali Lehry - Managing Director, India
- Format: **Name** - Title | 📍 Location

**If this fails:** Bot is not filtering correctly, review KB instructions.

---

#### Test B: Regional Office Fallback
**Ask:** "Who are your accessibility experts?"

**Expected:**
- Either shows Regional Leaders OR
- Provides regional office referral:
  ```
  📞 Find Your Regional Office:
  https://www.jensenhughes.com/contact/office-locations
  
  Or call (410) 737-8677
  ```

**Should NOT:**
- Say "I don't have that information"
- Show non-Regional Leadership experts

---

#### Test C: Office Phone Numbers ✅
**Ask:** "What's the phone number for Roseville?"

**Expected:**
- Phone: +1 925 938 3550
- NOT: (410) 737-8677
- Includes: Address, contact form, Google Maps

---

#### Test D: Privacy Check ❌ NO EMAILS
**Ask:** "Give me the email for the Oakland office"

**Expected:**
- Shows: info@jensenhughes.com (generic only)
- Shows: Contact form link
- Does NOT show: Any @jensenhughes.com personal emails

---

### Additional Tests:

**Services:**
- "What services do you offer?" → Should list categorized services
- "Tell me about fire protection" → Should show service details

**Offices:**
- "Where are your California offices?" → Should list CA offices with addresses
- "Oakland office contact" → Should show real phone number

**General:**
- "What does Jensen Hughes do?" → Should provide overview
- "Random question that doesn't exist" → Should handle gracefully with contact fallback

---

## 📊 Quick Results Template

After testing, note:

```
✅ Regional Leadership filtering: [WORKING / NOT WORKING]
✅ Regional office fallback: [WORKING / NOT WORKING]
✅ Real phone numbers (Roseville): [WORKING / NOT WORKING]
✅ Privacy (no emails): [WORKING / NOT WORKING]
✅ Services queries: [WORKING / NOT WORKING]
✅ Error handling: [WORKING / NOT WORKING]

Issues found:
1. [Description]
2. [Description]

Overall: [PASS / NEEDS FIXES]
```

---

## 🎯 Success Criteria

**PASS if:**
- Bot shows ONLY Regional Leaders when asked for experts
- Bot provides fallback when no Regional Leaders match
- Real office phone numbers display (not headquarters)
- No personal emails leak
- Graceful error handling

**NEEDS FIXES if:**
- Bot shows all experts without filtering
- Bot says "I don't have information" instead of fallback
- Wrong phone numbers display
- Personal emails visible

---

## 🚀 Testing Flow (30 minutes)

1. **Update Botpress KB** with new instructions (5 min)
2. **Test Regional Leadership** - 4 critical queries (10 min)
3. **Test Office Contact** - 3 queries (5 min)
4. **Test Services** - 2 queries (5 min)
5. **Test Error Handling** - 1 query (3 min)
6. **Document results** (2 min)

---

## 📞 If You Find Issues

### Issue: Bot not filtering Regional Leaders
**Fix:** 
- Verify Botpress KB was updated with BOTPRESS-INSTRUCTIONS-UPDATED.md
- Check if bot has access to teamMemberType field in API responses
- May need to add explicit filtering in Knowledge Base

### Issue: Wrong phone numbers
**Fix:**
- Verify craft_get_office_contact_info tool is being called
- Check if bot is using correct office slug
- Test API directly to confirm data is correct

### Issue: Personal emails showing
**Fix:**
- Review CRITICAL PRIVACY RULES section in KB
- Ensure bot instructions emphasize hiding @jensenhughes.com emails
- May need to add explicit filtering rule

---

## 📚 Reference Documents

- **Updated Instructions:** [BOTPRESS-INSTRUCTIONS-UPDATED.md](BOTPRESS-INSTRUCTIONS-UPDATED.md)
- **Full Testing Checklist:** [BOTPRESS-TEST-CHECKLIST.md](BOTPRESS-TEST-CHECKLIST.md)
- **Regional Leadership Guide:** [REGIONAL-LEADERSHIP-TESTING-GUIDE.md](REGIONAL-LEADERSHIP-TESTING-GUIDE.md)
- **Implementation Summary:** [REGIONAL-LEADERSHIP-IMPLEMENTATION.md](REGIONAL-LEADERSHIP-IMPLEMENTATION.md)
- **Test Script:** `bash test-regional-leadership-filter.sh`

---

**Current Status:** ✅ Backend ready, waiting for Botpress testing

**Next Action:** Update Botpress KB and run Priority Tests A-D above.
