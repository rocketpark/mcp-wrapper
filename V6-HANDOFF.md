# V6 Handoff — JH Botpress (2026-05-19)

Caveman prepared everything. Liz does the Studio paste + KB upload OK / final approvals.

## What's ready

### 1. V6 prompt (canonical mirror)
File: `botpress-integration/AUTONOMOUS-NODE-INSTRUCTIONS-V6.md`

Changes vs V5.2:
- **Rule 0 URL CONSTRUCTION** — hardcoded literal URLs per region. Banned `${prefix}` + HTML-encoded variants. Fixes the `${prefix}` leak live-reproduced today.
- **Rule 1 Case A/B split** — yes/no questions get conversational lead + ONE link (not full list dump). List dump only on "list all / what services" asks.
- **Rule 3 slug-leak ban** — bot must show office titles only, never internal slugs like "sydney-castlereagh-street".
- **Rule 10 topic-routing table** — BESS/Lithium Ion → Lithium-Ion Risk Consulting page. Combustible Dust → Process Safety. LSFT → Fire Testing. LNG → Process Safety. Hardcoded URLs, no placeholders.
- **Rule 12 NEW pre-send checklist** — bot self-checks for template syntax, slug leaks, one-link-per-topic, region-match, did-this-answer follow-up before emitting.

### 2. KB supplement
File: `data/botpress-topic-routing.txt` (5.4K)
Copy also at `/Users/elizabethstein/botpress-topic-routing.txt` (Playwright upload path)

Adds topic→service routing for: BESS / Battery Energy Storage / Lithium Ion, Combustible Dust / DHA, LSFT / Large-Scale Fire Testing, LNG / Cryogenics, UL 9540A, PBD, AHJ, Smoke Control, Egress, Sprinkler Design, Fire Alarm, BIMfire, Wildfire / WUI, Cybersecurity, Mass Notification, Threat Assessment.

Plus content-gap notes for Combustible Dust experts (bio-search doesn't index acronym variants), BESS service routing, LSFT Utah lab status (pending Sarah Pichardo).

### 3. PHP code changes (uncommitted, working tree)
- `src/tools/EntryTools.php:702` — office disambig message now uses **titles** not slugs in user-facing message (suggestions[] still includes slug for LLM re-prompt).
- `src/services/McpServerService.php:586` — `formattedText` for single-result emits inline link (no bullet) so LLM can splice into conversational reply per Rule 1 Case A.

### 4. Regression suite extension
File: `scripts/regression/jh-bot-regression.mjs`

8 new V6 scenarios appended (33 total):
- Combustible Dust experts (NA) — Rule 10 fallback + Process Safety routing
- BESS experts (NA) — Lithium-Ion Risk Consulting routing
- Lithium ion services (NA) — direct routing
- LSFT info (NA) — Large-Scale Fire Testing + fire-testing page
- LNG experts (NA) — Process Safety routing
- Sydney disambig (Pacific) — slug-leak check (NEW deny list catches "slug:" / internal slugs)
- Single-service-no-list-dump (NA) — Rule 1 Case A conformance
- EU BIM (Europe) — BIMfire article not Fire Engineering

ALL V6 scenarios include `${` `{prefix` `$&#123;` template-leak detection in deny lists.

## What you do (4 steps, ~15 min total)

### Step 1: Paste V6 into Studio AutonomousNode (5 min)
1. Open `mcp-wrapper/botpress-integration/AUTONOMOUS-NODE-INSTRUCTIONS-V6.md`
2. Copy from line 11 (`# RULE 0 — REGION...`) through end of Rule 12 / IDENTITY block
3. Open Studio: https://studio.botpress.cloud/208ffbe5-a209-4a10-a52c-d79de4577f45
4. Navigate to AutonomousNode → Instructions field → paste over existing V5
5. **Save** (top right of node panel)
6. **Publish bot** (top right of Studio — separate button — REQUIRED, save alone won't push to runtime)

### Step 2: Upload topic-routing KB (5 min)
1. Open: https://app.botpress.cloud/workspaces/wkspace_01KCPQEH096HZE66R7G994M5SR/bots/208ffbe5-a209-4a10-a52c-d79de4577f45/knowledge-bases/kb_01KH6JEBHYXFE3W42Q49S44K2S (Services KB)
2. Click "Add Knowledge Source" → "Document" tile
3. Upload file: `/Users/elizabethstein/botpress-topic-routing.txt`
4. Click "1 Upload"
5. Wait ~30s, refresh page, confirm "Indexed" status on new doc

OR: I can do this via Playwright if you greenlight.

### Step 3: Studio config polish (5 min)
**Message Placeholder** (lower friction for users):
- Webchat → Bot Identity → Message Placeholder field
- Change "Type your message..." → "Ask about services, offices, or experts..."
- Save

**(Optional) Hide duplicate header description** — there's no actual bug, but Bot Description renders in BOTH the header strip AND the welcome marquee, so the welcome text appears in two places. If John specifically wants only one:
- Webchat → Bot Appearance → Styles (Custom CSS textarea)
- Add: `.bpHeaderContentDescription { display: none; }`
- Save

**Always Alive** — leave OFF until you ask Jonathan.

### Step 4: Greenlight my deploy + test (caveman runs after)
After steps 1-3 are done, tell me. Caveman then:
- Commits PHP + regression + V6 mirror + KB file changes (split into 2-3 logical commits)
- Bumps composer.lock on Herd/jensenhughes staging3 to pull new mcp-wrapper version (per memory pattern from 2026-05-14 deploy)
- Pushes to trigger Forge auto-deploy
- Runs full 33-scenario regression
- Reports pass/fail
- Drafts ticket closeout comments for 868jpbvkj (Aldiana 11/17) + 868hv4tuy (Europe)
- Updates Notion Project Registry + auto-memory

## What's NOT being changed

- **Always Alive** — deferred per your call until you ask Jonathan ($5/mo decision)
- **Sarah Pichardo / Utah LSFT lab content** — noted in topic-routing KB as pending; not looping her in yet (your call when to)
- **Welcome bubble dup** — caveman investigated; NOT a bug, Botpress UX (header description + marquee both pull from Bot Description). Optional CSS fix above.
- **Footer "Need to speak with someone? Visit our contact page"** — plain text by Botpress v3 design; no link support. Could replace with shorter text but no real fix possible without custom CSS injection.
- **`\n \n` literal artifact** — couldn't reproduce. Likely pre-V5 bug now mitigated. Reopen if Aldiana re-surfaces.

## Aldiana 11/17 ticket items — coverage by V6

| Aldiana issue | V6 coverage |
|---|---|
| No typing indicator | Platform limit. NOT fixed. Would need flow-level "Looking up…" intermediate msg. |
| Bot combines topics across questions | Rule 11 (already in V5.2) + Rule 12 checklist. Should already be working. |
| LNG experts: no service-page suggestion | Rule 10 + topic-routing KB → Process Safety. |
| Combustible Dust experts: said zero (wrong, 16 on site) | Rule 10 + KB → Process Safety. Underlying expert-search bug noted (bios don't index "combustible dust" reliably). |
| Combustible Dust services: ok summary, no service-page link | Rule 10 + Rule 7 ONE-link minimum. |
| LSFT specific search: weird `\n \n` | Likely already fixed in V5 (couldn't reproduce). |
| Lithium Ion / BESS: not pointing to Emerging Hazards | Rule 10 routes to Lithium-Ion Risk Consulting page directly. |
| BESS experts: zero, wrong clarifying questions | Rule 10 + Rule 7 bans clarifying-question barrage. |
| Bot keeps asking for more info, zero links | Rule 7 minimum-one-link + Rule 10 fallback + Rule 12 checklist. |
| Large-scale fire testing missed Utah lab | KB content gap — pending Sarah's update. NOT fixed by V6 (content issue, not bot logic). |

## John meeting feedback — coverage by V6

| John ask | V6 coverage |
|---|---|
| Awareness bot is responding (3 dots) | Platform limit — see typing indicator note above. |
| Faster responses | V6 Rule 1 Case A skips list dumps on yes/no questions → fewer LLM tokens per response. ToolCacheService caches repeats. Always Alive deferred. |
| Bot responding with too many links | Rule 1 Case A + Rule 7 ONE-link rule. |
| Greeting message | Bot Description already serves as welcome bubble (header + marquee). If John wanted something different, ask him for spec. |
| Last bottom message not working | Identified: Footer plain text "Need to speak with someone? Visit our contact page" — Botpress v3 doesn't render footer as link. Working as Botpress designed; needs spec clarification from John. |
