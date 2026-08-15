# Studio Workflow Change — Force Tool Call for Office Contact

## Why this exists

We tried 4 layered fixes for "phone for X office" queries:

1. Implemented `craft_get_office_contact_info` MCP tool (was phantom)
2. Added fuzzy slug matching ("oakland" → "oakland-san-leandro")
3. Removed Office Locations from AutonomousNode KB pins
4. Enriched `query_officeLocations` responses with phone/address at the integration layer

The bot still answers "I couldn't find a published phone number..." for casual phrasings. Botpress's AutonomousNode LLM is non-deterministic on which `toolName` to pass to `Query Craft CMS Content`, and prompt edits can suggest but not force a specific choice.

The deterministic fix is to detect office contact intent **before** the LLM runs and pre-fetch the data, then either bypass the LLM entirely for that intent or feed the data to the LLM as guaranteed context.

## The change (pick one)

### Option A — Pre-fetch and inject (simplest, low blast radius)

Extend the existing `Execute code` card in `Standard1` (currently sets `conversation.region`). After the existing region line, add intent detection + tool call. The LLM still runs but `conversation.officeData` is pre-populated so it can quote phone/address verbatim.

Open `Standard1` → `Execute code` card → append:

```javascript
// Existing code (do not change):
// const data = workflow.userData?.userData || {}
// conversation.region = data.region || 'americas'

// New: pre-resolve office contact data so the LLM cannot fall back to
// "phone not listed" even if it picks the wrong toolName.
const userText = (event.payload?.text || '').toLowerCase()
const wantsContact = /\b(phone|number|tel|contact|address|call|reach)\b/.test(userText)
const mentionsOffice = /\boffice\b/.test(userText)

if (wantsContact && mentionsOffice) {
  // Pull a city / office hint from the user message.
  // Add new cities here as JH grows.
  const cityRegex = /\b(oakland|roseville|san\s*leandro|mumbai|london|sydney|tokyo|paris|berlin|dubai|abu\s*dhabi|doha|riyadh|hong\s*kong|shanghai|seoul|chicago|boston|columbia|denver|austin|los\s*angeles|san\s*francisco|new\s*york|melbourne|brisbane|auckland|toronto|montreal|fredericton)\b/
  const cityMatch = userText.match(cityRegex)

  if (cityMatch) {
    const slug = cityMatch[1].replace(/\s+/g, '-').toLowerCase()
    try {
      const result = await actions.jensenhughescraftcmsmcp.queryContent({
        toolName: 'craft_get_office_contact_info',
        slug,
      })
      const text = result?.content?.[0]?.text
      if (text) {
        const parsed = JSON.parse(text)
        if (parsed?.found && parsed.office) {
          conversation.officeData = parsed.office
        } else if (Array.isArray(parsed?.suggestions) && parsed.suggestions.length) {
          conversation.officeSuggestions = parsed.suggestions
        }
      }
    } catch (e) {
      // tool unreachable — fall through to AutonomousNode default path
    }
  }
}
```

Then **add one rule** at the very top of the AutonomousNode `## Priority` section (right after the existing PRIORITY OVERRIDE for office contact queries):

```
**If `conversation.officeData` is set, respond verbatim with**:
"{{conversation.officeData.title}} office:
- Phone: {{conversation.officeData.phone}}
- Address: {{conversation.officeData.address}}
- Office page: {{conversation.officeData.url}}"
Do not call any tool first. Use these values directly. If `conversation.officeData.phone` is null, say "Phone is not published — use the office page or info@jensenhughes.com."

**If `conversation.officeSuggestions` is set**, list each as a single line:
"{{title}} — /contact/office-locations/{{slug}}"
Ask the user which one they meant. Do not call a tool.
```

Action name verification: the integration appears in Botpress logs as `jensenhughescraftcmsmcp.queryContent`. If the call shape `actions.jensenhughescraftcmsmcp.queryContent(...)` doesn't resolve, try `actions['jensenhughes/craftcms-mcp'].queryContent(...)`. The Botpress Studio code editor will autocomplete the correct accessor once the integration is recognised.

### Option B — Branch the workflow (most deterministic, more work)

Build a new flow node `OfficeContactHandler` parallel to `AutonomousNode`. Modify `Standard1`'s transitions: if `conversation.officeData` is set, transition to `OfficeContactHandler` (which sends a templated message + transitions to `End`); else transition to `AutonomousNode`. The LLM never sees office contact questions.

This is cleaner long-term but needs visual flow editing in Studio. Defer to a future session unless A proves insufficient.

## Test plan after applying

Reset emulator conversation (Ctrl+Enter). Test each:

| Input | Expected |
|---|---|
| "What's the phone number for the Oakland office?" | "Oakland - San Leandro office: Phone: +1 510-775-1919, Address: 14719 Catalina Street, Office page: …" |
| "Contact for Mumbai office" | Mumbai office, Phone +91 9322401781, address |
| "I'm browsing from the Pacific site. Phone for Sydney office?" | Suggestions list (Sydney has 2 entries) |
| "What services do you offer?" | Falls through to AutonomousNode as before — no regression |
| "I'm browsing from the europe site. Do you offer accessibility?" | "Not currently available in Europe" — no regression |

Publish + verify live on staging3.

## Cleanup if it works

Once Option A is verified, the toolName table's lengthy `craft_get_office_contact_info` row and the standalone `PRIORITY OVERRIDE` paragraph can be shortened — the heavy lifting now happens in code, not prompt. Delete those after the test plan above passes for 3 separate cities.
