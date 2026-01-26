# Regional Leadership Filtering - Testing Guide

## 🎯 Objective
Ensure the Botpress bot only refers users to team members with **teamMemberType: "Regional Leadership"** and directs to regional offices when no Regional Leaders are found.

---

## 📊 Current Data

From our testing:
- **Total team members in system:** ~101
- **Regional Leadership members:** Only 3-5
- **Regular Experts:** ~98 (these should NOT be shown to users)

**Known Regional Leaders:**
1. Paul Macken - Director | 📍 London
2. Michael Jung - Senior Vice President, Asia Operations | 📍 Seoul
3. Ali Lehry - Managing Director, India | 📍 Mumbai
4. Steven Halliday - General Manager, Testing | 📍 Melbourne
5. Bart Sette - Managing Director, Belgium | 📍 Ghent

---

## 🧪 Test Cases

### Test 1: Search That SHOULD Find Regional Leaders

**User Query:** "Who are your fire protection experts?"

**Expected Bot Behavior:**
1. Calls `query_ourTeam` with search="fire protection"
2. Filters results to ONLY show Regional Leadership
3. Should find Ali Lehry (Regional Leadership + fire protection)
4. Should NOT show other non-Regional Leadership experts

**What to Check:**
- ✅ Does bot show ONLY Regional Leaders?
- ✅ Are non-Regional Leadership experts hidden?
- ❌ Does bot show all 20 experts without filtering?

---

### Test 2: Search That MIGHT NOT Find Regional Leaders

**User Query:** "Who are your accessibility experts?"

**Expected Bot Behavior:**
1. Calls `query_ourTeam` with search="accessibility"
2. Filters results for Regional Leadership
3. May find 0-1 Regional Leaders
4. **If NO Regional Leaders found**, should respond with:

```
I found several team members with expertise in accessibility, but for the best 
assistance with your specific needs, I recommend contacting your regional office directly.

📞 **Find Your Regional Office:**
https://www.jensenhughes.com/contact/office-locations

Or call (410) 737-8677 to be connected to your regional office.

Our regional teams can connect you with the right specialists for your project.
```

**What to Check:**
- ✅ Does bot properly handle zero Regional Leaders?
- ✅ Does bot provide regional office fallback?
- ❌ Does bot say "I can't find anyone"?
- ❌ Does bot show non-Regional Leadership experts?

---

### Test 3: General Expert Search

**User Query:** "Show me your technical experts"

**Expected Bot Behavior:**
1. Calls `query_ourTeam` with search="technical"
2. Filters for Regional Leadership ONLY
3. Shows 0-5 results (depending on matches)
4. If zero matches, provides regional office referral

**What to Check:**
- ✅ Only Regional Leaders displayed
- ✅ Fallback provided if needed
- ❌ Long list of all experts shown

---

### Test 4: Regional Leadership Direct Search

**User Query:** "Who are your regional leaders?"

**Expected Bot Behavior:**
1. Calls `query_ourTeam` with search="regional" or "leadership"
2. Should find 3-5 Regional Leadership members
3. Shows them with profiles

**What to Check:**
- ✅ Shows Paul Macken, Michael Jung, Ali Lehry, etc.
- ✅ All have "Regional Leadership" designation
- ❌ Mixes in non-Regional Leadership members

---

## 🔍 How to Verify Filtering in Botpress

### Method 1: Direct Testing
1. Go to Botpress testing panel
2. Ask: "Who are your fire protection experts?"
3. Check the bot's response
4. Manually verify each person shown has Regional Leadership designation

### Method 2: Check Tool Calls
1. Open Botpress debugger/logs
2. Look for `query_ourTeam` tool calls
3. Check the raw results returned
4. Verify bot is filtering the results, not showing all entries

### Method 3: Compare with Our Test Data
Run our test script to see expected results:
```bash
cd /Users/elizabethstein/Projects/mcp-wrapper
bash test-regional-leadership-filter.sh
```

Compare bot responses to script output.

---

## 🚨 Common Issues to Watch For

### ❌ **Issue 1: Bot Shows ALL Experts**
**Problem:** Bot displays all 20 experts from query_ourTeam without filtering

**Fix:** Update bot instructions to emphasize filtering:
- Add to KB: "ALWAYS filter query_ourTeam results by teamMemberType"
- Add to conversational node: Check teamMemberType before displaying

**Test:** Search for "fire protection" - should show 1-2 people, not 20

---

### ❌ **Issue 2: Bot Says "I Don't Have Information"**
**Problem:** When no Regional Leaders match, bot gives up

**Fix:** Add fallback workflow:
- IF Regional Leaders found → Show them
- IF zero Regional Leaders → Show regional office contact info

**Test:** Search for "accessibility" - should provide contact fallback

---

### ❌ **Issue 3: Bot Doesn't Understand teamMemberType**
**Problem:** Bot doesn't know how to filter by teamMemberType field

**Fix:** Add examples to bot training:
```
Example result structure:
{
  "title": "Ali Lehry",
  "teamMemberType": [{"title": "Regional Leadership"}],
  "role": "Managing Director"
}

Filter rule: Only show if teamMemberType contains "Regional Leadership"
```

---

## ✅ Success Criteria

The bot implementation is successful when:

1. **Only Regional Leaders shown** when users ask for experts (not all 98 experts)
2. **Fallback provided** when no Regional Leaders match the search
3. **No personal emails** or direct phone numbers shown for team members
4. **Regional office contact** provided as alternative when needed
5. **Consistent behavior** across all expert-related queries

---

## 📝 Testing Checklist

Test these queries in Botpress and mark your results:

- [ ] "Who are your fire protection experts?" → Should show 1-2 Regional Leaders
- [ ] "Show me accessibility experts" → Should provide regional office fallback
- [ ] "Who can help with code consulting?" → Filter to Regional Leaders only
- [ ] "I need a structural engineering expert" → Filter or fallback appropriately
- [ ] "Who are your regional leaders?" → Show 3-5 Regional Leadership members
- [ ] "Connect me with a security expert" → Filter to Regional Leaders or provide contact
- [ ] "Show me your team" → If listing all, should distinguish Regional Leaders

---

## 📞 Regional Office Fallback Template

When NO Regional Leaders match, bot should use this response:

```markdown
I found several team members with expertise in [TOPIC], but for the best 
assistance with your specific needs, I recommend contacting your regional 
office directly.

📞 **Find Your Regional Office:**
https://www.jensenhughes.com/contact/office-locations

Or call (410) 737-8677 to be connected to your regional office.

Our regional teams can connect you with the right specialists for your project.

**Can I help you find information about our services or office locations instead?**
```

---

## 🔧 Implementation Notes for Botpress

### In Knowledge Base:
Add this as a highlighted fact:
```
CRITICAL: When showing team members, ONLY show those with teamMemberType = "Regional Leadership"
All other team members should not be displayed to users.
If no Regional Leaders match, direct users to regional office contact.
```

### In Conversational Node:
Add filtering logic:
```
1. Call query_ourTeam
2. Filter results where teamMemberType includes "Regional Leadership"
3. If filtered results > 0: Display them
4. If filtered results = 0: Show regional office contact fallback
```

---

## 📊 Expected Results Summary

| Search Query | Total Results | Regional Leaders | Bot Should Show |
|-------------|---------------|------------------|-----------------|
| "fire protection" | 20 | 1-2 | 1-2 Regional Leaders |
| "accessibility" | 20 | 0-1 | Fallback to regional office |
| "regional leaders" | 5 | 5 | All 5 Regional Leaders |
| "structural engineering" | 15 | 0-2 | Filter or fallback |

---

## 🎯 Next Steps

1. **Update Botpress KB** with the new filtering instructions
2. **Test all queries** in the checklist above
3. **Document any issues** found during testing
4. **Verify fallback** works when no Regional Leaders match
5. **Check privacy** - no personal emails should be shown

---

**Remember:** The goal is to protect users from receiving incomplete information and ensure they're connected with the RIGHT level of contact (Regional Leaders or regional offices), not just any team member.
