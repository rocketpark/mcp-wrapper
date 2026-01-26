# Botpress Bot Testing Guide - Regional Leadership

**Quick 20-Minute Testing Checklist**  
**Date:** January 26, 2026

---

## 🎯 Before You Start

1. **Update Botpress Knowledge Base:**
   - Open [BOTPRESS-INSTRUCTIONS-UPDATED.md](BOTPRESS-INSTRUCTIONS-UPDATED.md)
   - Select all (Cmd+A), Copy (Cmd+C)
   - Go to Botpress → Your Bot → Knowledge Base
   - Paste and Save

2. **Open the Bot:**
   - Go to: https://jensenhughes3.on-forge.com
   - Find the chat widget (bottom right)
   - Start testing!

---

## ✅ Critical Tests (Must Pass)

### Test 1: Regional Leadership Filtering 🚨 MOST IMPORTANT

**Ask:** "Who are your fire protection experts?"

**✅ PASS if you see:**
- Only 1-2 people shown (like Ali Lehry - Managing Director, India)
- Each person labeled as "Regional Leader" or similar
- Clean formatting with name, title, location

**❌ FAIL if you see:**
- A long list of 10-20 people
- Mix of "Experts" and "Regional Leaders"
- Bot says "I don't have that information"

**Why it matters:** Bot should only recommend the 59 Regional Leaders, not all 101 team members.

---

### Test 2: Regional Office Fallback

**Ask:** "Who are your accessibility experts?"

**✅ PASS if you see:**
- Either: 1-2 Regional Leaders
- OR: Message like "I recommend contacting your regional office directly"
- Link: https://www.jensenhughes.com/contact/office-locations
- Phone: (410) 737-8677

**❌ FAIL if you see:**
- "I don't have that information" (no fallback)
- Long list of all experts

---

### Test 3: Real Office Phone Numbers

**Ask:** "What's the phone number for the Roseville office?"

**✅ PASS if you see:**
- Phone: +1 925 938 3550
- Address: 2281 Lave Ridge Court, Roseville, CA
- Links to: Google Maps, Contact Form

**❌ FAIL if you see:**
- Phone: (410) 737-8677 (that's headquarters, not Roseville)
- No phone number shown
- Just says "contact us"

---

### Test 4: Privacy - No Personal Emails

**Ask:** "Give me the email for the Oakland office"

**✅ PASS if you see:**
- Email: info@jensenhughes.com
- Contact form link
- General contact info

**❌ FAIL if you see:**
- Any email with format: firstname.lastname@jensenhughes.com
- Personal staff emails
- Direct contact information

---

## 🔧 Additional Tests (Should Pass)

### Test 5: Services List

**Ask:** "What services do you offer?"

**✅ Good:** Categorized list of services (Fire Protection, Code Consulting, etc.)  
**⚠️ OK:** Simple bullet list  
**❌ Bad:** "I don't have that information"

---

### Test 6: Specific Service

**Ask:** "Tell me about fire protection engineering"

**✅ Good:** Description of fire protection services  
**⚠️ OK:** Brief summary with link to learn more  
**❌ Bad:** No information or suggests podcasts only

---

### Test 7: Multiple Offices

**Ask:** "Where are your California offices?"

**✅ Good:** List of CA offices with addresses  
**⚠️ OK:** Offers to help find specific office  
**❌ Bad:** "I don't have that information"

**Note:** If this fails, it's a known limitation (search parameter). Bot should still be able to provide specific office info when asked directly (Test 3).

---

### Test 8: Error Handling

**Ask:** "Tell me about the XYZ service that doesn't exist"

**✅ Good:** Tries to search, then offers general contact options  
**⚠️ OK:** Says "I couldn't find that specific service, what else can I help with?"  
**❌ Bad:** Just says "I don't know" and stops

---

## 📊 Scoring Your Test

### Critical Tests (Tests 1-4):
- **4/4 Pass:** ✅ Ready for production!
- **3/4 Pass:** ⚠️ Fix the failing test before production
- **2/4 or less:** ❌ Needs work, review Botpress KB setup

### Additional Tests (Tests 5-8):
- **3-4 Pass:** ✅ Excellent
- **2 Pass:** ⚠️ Acceptable, monitor in production
- **0-1 Pass:** ❌ Check KB configuration

---

## 🐛 Troubleshooting

### Problem: Bot shows ALL experts without filtering

**Fix:**
1. Check Botpress KB has the updated instructions
2. Look for this section: "🚨 CRITICAL EXPERT REFERRAL RULES"
3. Make sure it says: "Only refer users to people with teamMemberType: Regional Leadership"
4. Try republishing the bot

---

### Problem: Wrong phone numbers (showing headquarters)

**Fix:**
1. Verify KB has this: "You MUST use craft_get_office_contact_info"
2. Check example shows: +1 925 938 3550 for Roseville
3. Make sure bot knows NOT to use (410) 737-8677 for specific offices

---

### Problem: Personal emails showing up

**Fix:**
1. Check "CRITICAL PRIVACY RULES" section in KB
2. Should say: "NEVER Display individual staff emails"
3. Should only show: info@jensenhughes.com

---

### Problem: Bot says "I don't have information" too often

**Fix:**
1. Check "Always Query First" section in KB
2. Should try multiple tools before giving up
3. Should always provide contact fallback

---

## 📝 Test Results Template

Copy this and fill it out:

```
Test Date: [DATE]
Bot URL: https://jensenhughes3.on-forge.com
KB Updated: [YES/NO]

CRITICAL TESTS:
[ ] Test 1: Regional Leadership (1-2 people shown, not 20)
[ ] Test 2: Regional office fallback (provides contact options)
[ ] Test 3: Real phone numbers (Roseville: +1 925 938 3550)
[ ] Test 4: Privacy (no personal emails, only info@jensenhughes.com)

ADDITIONAL TESTS:
[ ] Test 5: Services list
[ ] Test 6: Specific service
[ ] Test 7: Multiple offices
[ ] Test 8: Error handling

ISSUES FOUND:
1. [Description]
2. [Description]

OVERALL: [PASS / NEEDS FIXES / FAIL]
```

---

## 🎯 Quick Reference - What Bot Should Do

| User Asks | Bot Should Show | Bot Should NOT Show |
|-----------|----------------|---------------------|
| "Fire protection experts" | 1-2 Regional Leaders only | All 20 experts |
| "Accessibility experts" | Regional Leaders OR fallback | "I don't know" |
| "Roseville phone" | +1 925 938 3550 | (410) 737-8677 |
| "Oakland email" | info@jensenhughes.com | firstname.lastname@jensenhughes.com |
| "Your services" | Categorized list | Just podcasts |
| "California offices" | List or offer to help | Nothing |

---

## ✅ Success = All 4 Critical Tests Pass

Once the critical tests pass, the bot is ready! The additional tests are nice-to-have but not blockers.

**Next Steps After Testing:**
1. If all critical tests pass → Deploy to production ✅
2. If 1-2 tests fail → Review this guide's troubleshooting section
3. If 3+ tests fail → Check if KB was updated correctly with BOTPRESS-INSTRUCTIONS-UPDATED.md

---

## 📚 Related Files

- **For Botpress KB:** [BOTPRESS-INSTRUCTIONS-UPDATED.md](BOTPRESS-INSTRUCTIONS-UPDATED.md)
- **Full Technical Details:** [FINAL-TEST-RESULTS.md](FINAL-TEST-RESULTS.md)
- **Detailed Checklist:** [BOTPRESS-TEST-CHECKLIST.md](BOTPRESS-TEST-CHECKLIST.md)
- **Technical Guide:** [REGIONAL-LEADERSHIP-TESTING-GUIDE.md](REGIONAL-LEADERSHIP-TESTING-GUIDE.md)

---

**Good luck with testing! 🚀**

If you find any issues, note them down and we can fix them together.
