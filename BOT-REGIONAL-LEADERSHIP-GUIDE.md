# Bot Guide: Regional Leadership Filtering

## Problem
The bot was requesting a `query_regionalLeadership` tool that doesn't exist. "Regional Leadership" is NOT a separate section - it's a category value used to filter team members.

## Solution
Use the existing `query_ourTeam` tool with proper filtering.

## Implementation

### What "Regional Leadership" Actually Is

In Craft CMS:
- `ourTeam` is a SECTION containing all team members (~101 total)
- `teamMemberType` is a FIELD in each team member entry
- `teamMemberType` is a Categories field linking to the `teamMemberTypes` category group
- The `teamMemberTypes` category group contains:
  - "Experts" (~42 members)
  - "Leadership" (~some members)
  - "Regional Leadership" (~59 members) ← **THIS IS WHAT WE FILTER FOR**

### Correct Bot Workflow

**When user asks: "Who are your fire protection experts?"**

```javascript
// STEP 1: Call query_ourTeam
const allTeamMembers = await tools.query_ourTeam({
  search: "fire protection",
  limit: 50
});

// STEP 2: Filter for Regional Leadership ONLY
const regionalLeaders = allTeamMembers.filter(member => {
  // Check if teamMemberType field contains "Regional Leadership"
  const types = member.teamMemberType || [];
  return types.some(type => 
    type.title === "Regional Leadership" || 
    type.slug === "regionalLeadership"
  );
});

// STEP 3: Present results
if (regionalLeaders.length > 0) {
  // Show the Regional Leaders
  return formatTeamMembers(regionalLeaders);
} else {
  // Fallback: Direct to regional offices
  return `I found several team members with expertise in fire protection, but for the best assistance with your specific needs, I recommend contacting your regional office directly.

📞 Find Your Regional Office: https://www.jensenhughes.com/contact/office-locations
Or call (410) 737-8677`;
}
```

### Expected Data Structure

When you call `query_ourTeam`, each result looks like:

```json
{
  "id": "12345",
  "title": "Ali Lehry",
  "slug": "ali-lehry",
  "jobTitle": "Managing Director, India",
  "teamMemberType": [
    {
      "id": "789",
      "title": "Regional Leadership",
      "slug": "regionalLeadership"
    }
  ],
  "officeLocation": [
    {
      "title": "Mumbai",
      "officePhone": "+91 22 1234 5678"
    }
  ],
  "bio": "Ali has over 20 years..."
}
```

### CRITICAL Rules

1. **ALWAYS filter by teamMemberType** - Never show all 101 team members
2. **Only show "Regional Leadership"** - Filter out "Experts" and general "Leadership"
3. **Provide fallback** - If no Regional Leaders match, direct to offices
4. **Never show emails** - Only show office phone numbers, never personal contacts
5. **Check the array** - `teamMemberType` is an array of category objects

### Bot Training Examples

**Example 1: Successful Match**
```
User: "Who are your structural engineering experts?"
Bot: Calls query_ourTeam(search="structural engineering", limit=50)
Bot: Filters for teamMemberType containing "Regional Leadership"
Bot: Finds 2 matches
Bot: "I'd be happy to help! Here are our Regional Leadership members with structural engineering expertise:

**Michael Jung** - Senior Vice President, Asia Operations (Seoul office)
📞 +82 2 1234 5678

**Paul Macken** - Director (London office)
📞 +44 20 1234 5678"
```

**Example 2: No Regional Leaders Found**
```
User: "Who are your accessibility experts?"
Bot: Calls query_ourTeam(search="accessibility", limit=50)
Bot: Filters for teamMemberType containing "Regional Leadership"
Bot: Finds 0 matches
Bot: "I found several team members with expertise in accessibility, but for the best assistance with your specific needs, I recommend contacting your regional office directly.

📞 Find Your Regional Office: https://www.jensenhughes.com/contact/office-locations
Or call (410) 737-8677 to be connected to your regional office."
```

## Why NOT Create a Separate Tool?

You might think: "Why not create `query_regionalLeadership` tool?"

**Answer:** Because "Regional Leadership" is not a separate data source - it's a filter criterion on the existing `ourTeam` section. Creating a separate tool would:
- Duplicate data queries
- Miss the flexibility of searching all team members
- Require maintaining two tools that do essentially the same thing

The correct approach is to use `query_ourTeam` with client-side filtering.

## Testing Checklist

- [ ] Bot filters `teamMemberType` array correctly
- [ ] Bot only shows "Regional Leadership" members (not "Experts")
- [ ] Bot provides fallback when no Regional Leaders match
- [ ] Bot never shows personal email addresses
- [ ] Bot response is concise (2-3 Regional Leaders max)

## Summary

**DO:**
- ✅ Use `query_ourTeam` for all team member queries
- ✅ Filter results by `teamMemberType` = "Regional Leadership"
- ✅ Provide regional office fallback when needed
- ✅ Show office phone numbers

**DON'T:**
- ❌ Request `query_regionalLeadership` tool (doesn't exist)
- ❌ Show all 101 team members without filtering
- ❌ Show personal email addresses
- ❌ Give up if no matches - provide fallback
