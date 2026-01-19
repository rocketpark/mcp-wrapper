# Quick Fix for "What services do you offer?" Issue

## The Problem
When users ask "What services do you offer?", the bot says it can't find information, even though it's calling the query tool successfully.

## The Solution
Add these critical rules to your Botpress Autonomous Node instructions:

### For General Service Questions

When a user asks "What services do you offer?" or similar general questions:

**STEP 1**: Try queryContent WITHOUT a search parameter
```javascript
{
  toolName: "query_services",
  limit: 20
}
```

**STEP 2**: If that returns empty/null, try intelligentSearch
```javascript
{
  query: "services Jensen Hughes offers"
}
```

**STEP 3**: If STILL no results, ask the user to be specific:
```
"I'm having trouble accessing our full services list right now. 
Could you tell me which area interests you? For example:
• Fire Protection Engineering
• Code Consulting  
• Risk Assessment
• Security Consulting
• Accessibility and Universal Design"
```

### Critical Rules to Add

**DO:**
- ✅ Always try queryContent first WITHOUT search parameter for "list all" type questions
- ✅ Check if results are null/empty before responding
- ✅ Use intelligentSearch as a fallback
- ✅ Present results in a friendly, formatted way (bullets or cards)

**DON'T:**
- ❌ NEVER say "I couldn't find information" without trying BOTH queryContent AND intelligentSearch
- ❌ Don't give up after one empty result
- ❌ Don't show raw API data to users

## Copy-Paste Ready Instructions

Add this section to your Autonomous Node instructions under "When Users Ask Questions":

```
### Handling "What services do you offer?"

When user asks for a general list of services:

1. FIRST ATTEMPT:
   Call: queryContent
   Input: { 
     toolName: "query_services",
     limit: 20
   }
   
2. CHECK RESULTS:
   - If results exist: Present 6-8 services with brief descriptions
   - If null/empty: Proceed to step 3
   
3. FALLBACK ATTEMPT:
   Call: intelligentSearch
   Input: { 
     query: "services offered by Jensen Hughes" 
   }
   
4. IF BOTH FAIL:
   Respond: "I'm having trouble accessing our services list. 
   Could you tell me which area interests you? 
   For example: fire protection, code consulting, or risk assessment?"

5. WHEN RESULTS FOUND:
   - Format as bulleted list or cards
   - Include brief description for each service
   - End with: "Would you like to learn more about any of these?"
```

## Testing Checklist

After updating instructions:
- [ ] Test: "What services do you offer?"
- [ ] Test: "Tell me about your services"
- [ ] Test: "What do you do?"
- [ ] Test: "Tell me about code consulting" (specific service)

All should return actual service information, not "I couldn't find that."

## If Still Failing

Check these:
1. Is the MCP integration properly configured with schema handle "jensenhughes"?
2. Does the GraphQL schema in Craft CMS have permissions for the services section?
3. Are there actually service entries in Craft CMS?
4. Check the logs - is queryContent returning an empty array `[]` or an error?

