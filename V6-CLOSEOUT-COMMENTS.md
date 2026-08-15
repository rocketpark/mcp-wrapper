# V6.2 Closeout — Draft Ticket Comments

These post to ClickUp AFTER full regression passes. Don't post until regression confirms.

---

## Comment for 868jpbvkj (Chatbot Feedback 05/17 — Aldiana)

Aldiana — caveat all of these were live-tested today on staging3.on-forge.com against the bot's current V6.2 prompt. Per-issue status:

**1. "While the bot is searching, you can't tell it's searching (3 dots…)"**
Not fixed in V6.2. Botpress webchat shows typing dots only during the LLM token-generation phase, not during MCP/Craft tool calls — and most of the latency lives in the tool calls. This is a Botpress platform limit, not a prompt fix. Workaround would be a flow-level "Looking up…" intermediate message. Logged for follow-up.

**2. "If I move to a different topic, the bot combines the two unless I explicitly tell it not to."**
Fixed. V6 Rule 11 (Topic Isolation) explicitly tells the bot each new question is a fresh topic unless the user bridges with "and also", "what about", etc. Verified on staging.

**3. "I asked for experts in LNG — it didn't suggest the service page as an option, just gave me other ways to refine my search."**
Fixed. V6 Rule 10 + the new KB topic-routing doc map LNG → Process Safety. Live test on Combustible Dust (same fallback pattern) returns: "I couldn't find a specific expert directory for combustible dust. The closest service area is Process Safety. Here's the page: https://www.jensenhughes.com/services/process-safety."

**4. "I asked for experts in Combustible Dust and it told me it couldn't find any, which is not correct; we have several on the site. Our regular search finds 16."**
Partially fixed. Bot now routes to Process Safety service page (above). But the underlying `query_ourTeam` tool still doesn't surface those 16 experts because their bios use varied phrasing ("dust hazard analysis", "DHA", "explosion protection") and the tool's search doesn't match across those. The graceful fallback is now in place; the deeper search-fidelity fix would be a tool-level enhancement (e.g., expert tagging or full-text search) — separate work item.

**5. "I asked about services for combustible dust and it gave me a decent summary but did not direct me to the service page to learn more."**
Fixed. V6 Rule 7 enforces minimum-one-link on every substantive answer + Rule 1 Case A (yes/no questions) leads with conversational answer + ONE primary link.

**6. "I asked about large-scale fire testing and it provided info on 2 labs but missed our newest one in Utah."**
Content gap, not bot logic. The KB topic-routing doc notes this — pending Sarah Pichardo / Kayleigh / Jens for current Utah SMS lab content on the labs page + LSFT page. Bot can't link content that isn't published.

**7. "Tried to search for LSFT specifically and it wanted a lot more information and gave a weird \\n \\n result."**
Could not reproduce on V6.2. Live test: "LSFT usually refers to Large-Scale Fire Testing. At Jensen Hughes, that work is typically covered under our Fire Testing services… Here's the best place to start: Fire Testing: https://www.jensenhughes.com/services/fire-testing." Clean answer, no `\n \n` artifact. Likely a pre-V5 LLM raw-emit bug now mitigated. Reopening if it returns.

**8. "Got an incorrect answer when I asked about Lithium Ion and BESS, that service lives under Emerging Hazards."**
Fixed. V6 Rule 10 routing table covers BESS / Lithium Ion → Emerging Hazards. Live test: "I couldn't find a specific expert directory for BESS (Battery Energy Storage Systems). The closest service area is Emerging Hazards. Here's the page: https://www.jensenhughes.com/services/emerging-hazards."

**9. "Specific delivered zero results when asking who our experts are in BESS (there are several) and the clarifying questions are completely wrong."**
Same as #4 — Rule 10 fallback now routes BESS expert query to the Emerging Hazards service page rather than asking for clarification. The underlying expert directory still doesn't surface BESS-tagged people; that's a tool-level enhancement.

**10. "In general the bot keeps asking me for more info and not actually directing me anywhere specific. Zero links to any pages or experts."**
Fixed. V6 Rule 7 explicitly bans clarifying-question barrages for clear service questions + enforces minimum-one-link per substantive answer. V6 Rule 10 makes the expert-search fallback always emit a service-page URL + email rather than asking the user to refine. Live tests today across Combustible Dust, BESS, LSFT, and BIM all returned a primary link.

**Bonus side-bug spotted + fixed in this same pass:**
Office disambiguation used to leak internal slugs ("Sydney - Bowman (slug: sydney-australia)"). V6 Rule 3 + a PHP fix (titles-only in disambig message) now returns clean "Sydney / Sydney - Bowman" titles.

Marking the remaining items (typing indicator, deep expert-search fidelity, Utah LSFT content) as separate follow-ups so this ticket can close.

---

## Comment for 868hv4tuy (Europe teams feedback — Aldiana)

Status update on each Europe item flagged:

**1. Forensics linking to general contact form vs. Forensics contact form.**
Fixed in V5 + V6. EU forensics now routes to https://www.jensenhughes.com/europe/services/forensic-investigation + the dedicated forensics email instructus.uk@jensenhughes.com (not info@). Verified live.

**2. Same answer / same form repeating across multiple questions.**
Fixed. V6 Rule 11 enforces topic isolation — new questions get fresh answers. Rule 7 + Rule 1 Case A cut the repetitive "contact our European offices for local support" boilerplate that was being appended to every answer.

**3. "In Europe, do you offer accessibility services?" — bot says yes, but JH doesn't offer it in Europe (only one Ireland specialist starting).**
Fixed. V6 Rule 4 NOT-available list for Europe includes Accessibility + Universal Design. Bot now responds: "Accessibility + Universal Design is not currently available in Europe. For more info, contact info@jensenhughes.com."

**4. "Do you offer security and emergency management in Europe?" — bot says yes, but JH doesn't offer it in Europe.**
Fixed. V6 Rule 4 NOT-available list for Europe includes both Security Risk + Public Safety AND Emergency Management + Response. Same response pattern as accessibility.

**5. "Do you offer BIM services?" — bot links Fire Engineering + Systems Design page; should link the BIMfire article.**
Fixed. V6 Rule 6 explicitly hardcodes the BIMfire insights URL (https://www.jensenhughes.com/insights/incorporating-bimfire-into-jensen-hughes-fire-safety-design) and bans linking the Fire Engineering services page for BIM queries. Verified live.

All five items addressed. Closing.

---

## Update for parent task 868hcqjz6 (Jensen Hughes Bot Project)

V6.2 deployed to staging today. Key changes:
- V6 prompt with Rules 0-12 (added Rule 10 topic-routing, Rule 11 topic isolation, Rule 12 pre-send checklist).
- Three rounds of patches (V6.0 → V6.1 → V6.2) to fix a `${prefix}` template-literal leak — root cause was the prompt's own preamble/changelog containing the literal token, which the LLM was regurgitating into URLs. Final fix: moved changelog out of pasted text + inlined 5 complete copy-paste response templates per region in Rule 10 fallback.
- New KB document: botpress-topic-routing.txt (5.5 kB) uploaded to Services KB. Maps acronym/topic queries (BESS, LSFT, LNG, PBD, Combustible Dust, etc.) to the correct JH service pages.
- PHP fixes pending deploy (uncommitted on feature/mcp-improvements): office-disambig titles-only message (EntryTools.php), single-result formattedText slim (McpServerService.php).
- Regression suite extended from 25 to 33 scenarios with explicit template-leak detection in deny lists.

Source of truth files:
- mcp-wrapper/botpress-integration/AUTONOMOUS-NODE-INSTRUCTIONS-V6.md (prompt body)
- mcp-wrapper/botpress-integration/V6-CHANGELOG.md (history)
- mcp-wrapper/data/botpress-topic-routing.txt (KB doc)
- mcp-wrapper/scripts/regression/jh-bot-regression.mjs (33-scenario regression)

Verified live (V6.2 spot-check):
- Combustible Dust experts (NA) → Process Safety routing, no leak.
- BESS experts (NA) → Emerging Hazards routing, acronym expanded, no leak.
- LSFT (NA) → Fire Testing routing, conversational + ONE link.
- Sydney office disambig (Pacific) → clean titles, no slug leak.

Open items:
- Typing indicator during MCP tool calls (Botpress platform limit; would need flow-level "Looking up…" intermediate message).
- Utah LSFT lab content (pending Sarah Pichardo).
- Always Alive toggle ($5/mo) deferred pending Jonathan.
- PHP commits + composer/Forge deploy of EntryTools + McpServerService fixes.
- Full 33-scenario regression run results.