# Bot Testing Questions

## Critical Tests (Must Pass)

These test the specific issue we just fixed:

### General Service Queries
- [ ] "What services do you offer?"
- [ ] "Tell me about your services"
- [ ] "What does Jensen Hughes do?"
- [ ] "What can you help me with?"

**Expected:** Bot should return a list of 6-8 services with descriptions, NOT "I couldn't find that information"

---

## Service-Specific Queries

### Fire Protection
- [ ] "Tell me about fire protection engineering"
- [ ] "Do you do fire safety consulting?"
- [ ] "What fire protection services do you offer?"

**Expected:** Service description + link to service page (not podcast)

### Code Consulting
- [ ] "Tell me about code consulting"
- [ ] "Do you help with building code compliance?"
- [ ] "What is code consulting?"

**Expected:** Service description + offer to find experts or case studies

### Risk Assessment
- [ ] "What risk assessment services do you have?"
- [ ] "Tell me about risk consulting"
- [ ] "Do you do security risk assessments?"

**Expected:** Service details with relevant offerings

### Accessibility
- [ ] "Do you offer accessibility consulting?"
- [ ] "Tell me about universal design"
- [ ] "What ADA services do you provide?"

**Expected:** Service information or related offerings

---

## Office Location Queries

### State-Level Queries
- [ ] "Where is your California office?"
- [ ] "Show me your Texas offices"
- [ ] "Do you have an office in New York?"
- [ ] "Where are your offices located?"

**Expected:** List of ALL offices in that state/region (should see 3 Texas offices, 7 California offices, etc.)

### City-Level Queries
- [ ] "Where is your Austin office?"
- [ ] "Do you have an office in Seattle?"
- [ ] "What's your Chicago office address?"

**Expected:** Specific office details (address, phone, services)

---

## Team/Expertise Queries

- [ ] "Who are your fire protection experts?"
- [ ] "Show me team members who specialize in code consulting"
- [ ] "Who should I talk to about security consulting?"
- [ ] "Tell me about your leadership team"

**Expected:** List of team members with relevant expertise, names, titles, locations

---

## Company Information

### About the Company
- [ ] "Tell me about Jensen Hughes"
- [ ] "What is Jensen Hughes?"
- [ ] "How long have you been in business?"
- [ ] "Where are you headquartered?"

**Expected:** Company overview, history, scale (100+ offices, 1900+ employees, etc.)

### Industries
- [ ] "What industries do you serve?"
- [ ] "Do you work with healthcare facilities?"
- [ ] "Tell me about your aviation work"
- [ ] "Do you work with government clients?"

**Expected:** Industry-specific information or general industry overview

### Resources
- [ ] "Do you have case studies?"
- [ ] "Show me recent news"
- [ ] "Do you have a blog?"
- [ ] "What educational resources do you offer?"

**Expected:** Links to resources, articles, case studies

---

## Edge Cases & Error Handling

### Ambiguous Queries
- [ ] "Tell me more" (after no context)
- [ ] "What about that?"
- [ ] "Services"

**Expected:** Bot asks clarifying questions

### Out of Scope
- [ ] "What's the weather?"
- [ ] "Tell me a joke"
- [ ] "How do I reset my password?"

**Expected:** Polite redirect to relevant Jensen Hughes topics

### Pricing/Comparison
- [ ] "How much do your services cost?"
- [ ] "Are you better than [competitor]?"
- [ ] "What's your pricing model?"

**Expected:** Only information from KB, or "I don't have pricing information, contact our team"

### Complex Multi-Part
- [ ] "Do you have a fire protection expert in California who can help with code consulting?"

**Expected:** Bot breaks down query and finds relevant team members or offices

---

## Follow-Up Questions

Test that the bot maintains context:

1. "What services do you offer?"
2. Then: "Tell me more about the first one"
3. Then: "Who are experts in that area?"
4. Then: "Are any of them in Texas?"

**Expected:** Bot should maintain context through the conversation

---

## Response Quality Checks

For each answer, verify:

✅ **Accurate**: Information comes from Craft CMS (not made up)
✅ **Formatted**: Uses bullets, cards, or clear paragraphs (not raw JSON)
✅ **Helpful**: Includes next steps or follow-up suggestions
✅ **Professional**: Tone matches brand voice
✅ **Complete**: Doesn't say "I couldn't find" without trying multiple queries
✅ **Linked**: Provides links to service pages when relevant

---

## Quick Test Sequence

Run these 5 questions in order as a smoke test:

1. "What services do you offer?" → Should list services
2. "Tell me about code consulting" → Should show specific service
3. "Where is your California office?" → Should show ALL CA offices
4. "Who are your fire protection experts?" → Should show team members
5. "Tell me about Jensen Hughes" → Should give company overview

If all 5 pass, the bot is working correctly!

---

## Logging What to Check

When reviewing logs after each test:

1. **Tool Calls**: Did it call `queryContent` or `intelligentSearch`?
2. **Parameters**: Were toolName and limit set correctly?
3. **Results**: Did the query return data or empty array?
4. **Fallback**: If first query failed, did it try alternative approach?
5. **Response**: Did it present results or say "couldn't find"?

**Red Flags:**
- ❌ Says "couldn't find" after only ONE tool call
- ❌ Tool returns data but bot says "no information"
- ❌ Shows raw JSON/API response to user
- ❌ Makes up information not from queries

