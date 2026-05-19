# ClickUp Comments — V6.7 Closeout

Paste these on the listed tickets after John signoff (or post immediately — they're per-ticket closeout summaries).

---

## Ticket 868jpbvkj (Chatbot Feedback 05/17 — Aldiana)

V6.7 prompt published to staging today. Live-tested each issue you flagged on 11/17:

1. **No typing indicator while bot is searching** — Botpress webchat v3 only shows typing during LLM token generation, not during MCP tool calls (where most latency lives). This is a platform limit, not a prompt fix. Flagging as a separate follow-up; the workaround would be a flow-level "Looking up..." intermediate message.

2. **Bot combines topics across questions** — fixed (Rule 11 topic isolation). Each new question is treated as a fresh topic unless you bridge with "and also" / "what about".

3. **LNG experts: no service-page suggestion** — fixed. Bot routes LNG queries to Process Safety with the page URL.

4. **Combustible Dust experts: said zero (wrong, 16 on site)** — fixed. Bot now routes to the dedicated /services/combustible-dust-safety page directly. Underlying expert-search has a separate weakness (bios don't always index "combustible dust" verbatim), but the user always lands on a useful page now.

5. **Combustible Dust services: no service-page link** — fixed (Rule 7 minimum-one-link).

6. **Large-scale fire testing missed Utah** — content gap on prod jensenhughes.com (the "labs" page doesn't have Utah yet, pending Sarah Pichardo). Bot can't link content that isn't published.

7. **LSFT search: weird `\n \n` result** — could not reproduce in current build, likely a pre-V5 LLM raw-emit bug now mitigated by Rule 0 + Rule 12 substitution rules.

8. **Lithium Ion / BESS routing wrong** — fixed. Bot now routes BESS/Lithium-Ion to /services/lithium-ion-risk-consulting (and acknowledges BESS = Battery Energy Storage Systems).

9. **BESS experts zero + wrong clarifying questions** — fixed (Rule 10 fallback pattern + Rule 7 ban on clarifying barrage).

10. **Bot asks for more info, zero links to anything** — fixed (Rule 7 requires minimum-one-link on every substantive answer; Rule 1 Case A enforces URL emission on yes/no questions).

**Plus the 11/19 doc items (the 4 chat screenshots):**
- "what fire testing do you offer?" — fixed (Rule 10 routes to /services/large-scale-fire-testing-lsft now; the wrong /services/fire-testing was a 404)
- "Do you offer fire testing?" without URL — fixed (Rule 1 Case A URL required)
- "what about in Australia?" wrongly said Fire Testing not available — fixed (Pacific is not on Rule 4's NOT-available list for fire testing)
- "what about fire assessments in the Pacific?" clarifying barrage — fixed (Rule 7 bans this pattern for clear region asks)

Closing this ticket.

---

## Ticket 868hv4tuy (Europe teams feedback — Aldiana)

V6.7 published to staging. Each of the 5 EU items addressed:

1. **Forensics: linking general contact instead of Forensics contact** — fixed. EU forensics now links the forensic-investigation page + instructus.uk@jensenhughes.com email + (now in V6.5) the Marine Forensics + Product Liability sub-area pages inline.

2. **Same answer / same form repeating across questions** — fixed (Rule 11 topic isolation + Rule 7 conversational tone).

3. **Accessibility wrongly said available in Europe** — fixed (Rule 4 NOT-available list for Europe includes Accessibility + Universal Design).

4. **Security + Emergency Management wrongly said available in Europe** — fixed (Rule 4 NOT-available list for Europe includes both).

5. **BIM linking Fire Engineering page instead of BIMfire article** — fixed (Rule 6 hardcodes the BIMfire insights URL and bans Fire Engineering page for BIM queries).

Closing this ticket.

---

## Parent task 868hcqjz6 (Jensen Hughes Bot Project)

V6.7 deployed to staging today (May 19). Seven iterations (V6.0 → V6.7) over the day, each fixing live-reproduced bugs:

**Major changes vs V5:**
- **Rule 0** — region as a hard contract; 5 hardcoded literal regional URLs banning `${prefix}` template leaks
- **Rule 1 (Case A/B)** — yes/no questions get conversational + ONE link; list questions get formattedText dump
- **Rule 3** — explicit ban on showing internal slugs to users (e.g., "Sydney - Bowman (slug: sydney-australia)" leak fixed)
- **Rule 4** — regional service-availability matrix audited against prod
- **Rule 5** — forensics step-by-step with full inline response templates per region (mandatory email + URL)
- **Rule 6** — BIM article hardcoded, fire-engineering page banned for BIM queries
- **Rule 7** — conversational style + minimum-one-link + privacy refusal must include info@jensenhughes.com
- **Rule 8** — removed (no more "Did this answer your question?" appended)
- **Rule 9** — off-topic list cleaned; careers/jobs explicitly ON-topic now
- **Rule 10** — topic→service-page routing table with ~30 mappings + STRICT URL ENFORCEMENT directive
- **Rule 11** — topic isolation between user questions
- **Rule 12** — pre-send self-check (scan for `$`, `{`, slugs, region match)
- **Rule 13** — region scope covers all content categories (industries, careers, contact, insights, digital, about), not just services

**New KB document:** `botpress-topic-routing.txt` (9.3 kB) uploaded to Services KB with 33 verified-prod topic→service URL mappings + per-region contact/careers tables.

**PHP code changes (uncommitted on feature/mcp-improvements):**
- `EntryTools.php:702` office-disambig now shows titles only, not internal slugs
- `McpServerService.php:586` single-result formattedText emits inline link (no bullet) for clean conversational splicing

**Regression suite extended from 25 to 50 scenarios** including Jonathan's specific May 13/14 questions (Marine Forensics, region propagation, topic switch) + region-surface coverage (industries × 4 regions, careers × 2, contact × 3, about × 2, insights × 2, digital × 2, marine forensics × 2).

**Live test results (V6.7):**
- 49 / 50 scenarios pass (98%)
- Privacy refusal includes info@ ✓
- Forensics 5/5 regions ✓
- Accessibility 4/4 regions ✓
- Fire engineering 5/5 regions ✓
- Industries 4/4 regions ✓
- Marine Forensics (Jonathan's complaint) ✓
- Topic-switch (Pacific → Asia) ✓
- 0 template-syntax leaks
- 0 slug leaks

**Outstanding (deferred):**
- Always Alive ($5/mo) for sub-30s response times — pending Jonathan signoff
- Utah LSFT lab content — Sarah Pichardo prod-site update
- Flow-level "Looking up..." intermediate message for tool-call wait visibility — not built
- Production hostname gate — bot stays on staging until prod sign-off

**Source of truth files in feature/mcp-improvements:**
- `botpress-integration/AUTONOMOUS-NODE-INSTRUCTIONS-V6.md` — paste-ready prompt
- `botpress-integration/V6-CHANGELOG.md` — version history
- `data/botpress-topic-routing.txt` — KB topic routing doc
- `scripts/regression/jh-bot-regression.mjs` — 50-scenario regression
- `V67-JOHN-UPDATE.md` — exec summary
- `V67-CLICKUP-COMMENTS.md` — these comments