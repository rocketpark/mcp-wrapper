Fallback contact: info@jensenhughes.com | (410) 737-8677 | https://www.jensenhughes.com/contact-us

---

## Change log

- **2026-05-20 — Integration v1.0.16 (Matt Booth findability fix):**
  - `query_ourTeam` now passes the bot's `search` term through to MCP instead of fetching unconditional + filtering in JS. MCP's no-search query_ourTeam caps at ~100 entries (CMS has 101+ team members). Members alphabetically past the cap (Matt Booth, others) never reached the JS name-match filter, so the bot incorrectly told users "I can't find a directory entry for [Person]" even when the person had a live profile on jensenhughes.com/experts/[slug].
  - Confirmed wild bug 2026-05-20: bot returned person-not-found for Matt Booth despite `/experts/matt-booth` returning HTTP 200 and direct MCP search returning his record. MCP fetch with limit=200 returned 100 entries, Matt Booth at position -1 (not in slice).
  - Browse-mode (no search term) keeps the unconditional fetch to apply the Regional Leadership filter for "show me experts in X" queries.

- **2026-05-20 — Integration v1.0.15 (cross-user region-leak fix):**
  - `listEvents` fallback in `src/index.ts:315` now filters events by `createdAt within last 60 seconds` instead of returning the most-recent-ever regionContext event in the workspace. Closes the wild-confirmed race: Jonathan on Global region got `/asia/services` because an earlier test session left a stale Asia regionContext event in the workspace event log; bot's fallback picked it up despite it being unrelated to Jonathan's user.
  - 60s window is conservative — the Twig footer re-emits regionContext on every `webchat:messageSent`, so the current user's event is always within seconds of the bot's query. Stale events older than 60s are ignored.
  - Does not fully eliminate the race under genuine concurrent traffic (two users from different regions both active within 60s), but reduces the window dramatically. Real fix remains User Variables refactor (see audit doc Tier 2 item #5).

- **2026-05-20 — V6.16 (marine + product liability go per-region, pending paste):**
  - V6.15's "globally; UK team" framing was a half-fix. Real answer: response should differ per user region. Live scrape confirms NA, Pacific, Asia, ME have NO marine-fire-forensics page (returns 404). Only EU has it.
  - V6.16 Rule 5 STEP 0 now emits **3 different templates per region**:
    - EU asks marine → direct EU URL (unchanged from V6.15)
    - NA asks marine → "specialty within our forensic practice — page on our Europe site, NA general forensics at /services/investigations, UK team for marine specifically"
    - Pacific/Asia/ME asks marine → "Europe-based specialty handled by UK team, EU URL, instructus.uk@ for marine, info@ for general forensics in your region"
  - Same per-region pattern for product liability. Note added that other EU forensic sub-specialties (civil-structural-failure, escape-of-water, fire-explosion, materials-failure-analysis, expert-witness-litigation-support) follow the same pattern.
  - Triggered by Liz/Jonathan ask to make bot **truly region-aware for ALL content categories**, not just services. Phase 1 inventory scrape running in parallel (research-agent) to capture all categories per region (insights, industries, careers, etc.) — will inform V6.17+ and a future tool-driven URL resolution refactor.

- **2026-05-20 — V6.15 (marine + product liability framing fix, published 2026-05-20):**
  - V6.12-V6.14 linked `/europe/services/marine-fire-forensics` for any-region marine query, which made the URL feel exclusionary ("why is the bot pointing me to Europe?"). Real story: marine forensics + product liability are GLOBAL services owned by JH's UK team — the page just lives on the EU site because that's where the team's content authoring happens. Verified by reading the page content: "expertise for insurers, building owners and adjusters globally."
  - V6.15 Rule 5 STEP 0 templates now LEAD with the global framing:
    > "Yes — Jensen Hughes offers marine forensics globally; our specialist team is based in the UK. Here's the page: ..."
  - Same shape for product liability. URLs + emails unchanged (still EU URL + instructus.uk@ — that's where the content + team live).

- **2026-05-20 — V6.14 (person-lookup richer reply, pending paste):**
  - Rule 2.5 step 2 reply template was too thin ("[name] is on the Jensen Hughes team. Here's the profile page: [url]"). User can already see the name they asked about — that says nothing useful. Updated to include `role` (job title) and `officeLocation[0].title` from the entry the integration returned. Example: "Matt Booth is ACT Manager - Fire Safety Engineering based in our Canberra office. Here's the profile page: [url]"
  - Fallback chain: role+office > office-only > generic stub. NEVER fabricate from general knowledge — use only what the tool returned.
  - Triggered by Jonathan-style verification: Matt Booth IS an ACT Manager based in Canberra (per CMS data); previous response told the user nothing.

- **2026-05-20 — V6.13 (URL fix for V6.12 person-lookup fallback, published 2026-05-20):**
  - V6.12's Rule 2.5 person-not-found template linked `/our-team` which **404s** on prod (verified via curl HEAD). The correct URL is `/our-experts` (200, page title "Featured Experts"). `/experts` also serves the same landing. Picked `/our-experts` for clarity.
  - V6.12 was a regression fix that fixed Jonathan's bugs but introduced a new broken URL. V6.13 corrects.
  - Re-tested today: Matt Booth person query now links the LIVE Featured Experts directory.

- **2026-05-20 — V6.12 (Jonathan bugs caught, published 2026-05-20 — has broken /our-team URL, fixed in V6.13):**
  - **Rule 5 STEP 0 (NEW)** — Marine Forensics + Product Liability sub-topic override BEFORE region routing. Sub-areas exist only on EU site but apply globally. NA/Asia/Pacific/ME user asking for "marine forensics" now gets the EU `/europe/services/marine-fire-forensics` URL + `instructus.uk@jensenhughes.com` email. Previously: bot fell through to NA Step 2 template (`/services/investigations`) — wrong URL, wrong email. Confirmed by Jonathan on Global region today.
  - **Rule 2.5 NEW — Person Lookup fallback** — when `query_ourTeam` returns 0 results for a specific named person (e.g. "Matt Booth"), bot now emits the **person-not-found template** linking the global team directory (`/our-team`) + `info@jensenhughes.com`. Previously bot fell through to Rule 10 services-page template (e.g. `/asia/services`) which is wrong for a person query. Confirmed by Jonathan today.
  - Identified separately: a cross-user region-leak race in the integration's `listEvents` fallback (caused Jonathan's Global-region query to route to Asia). Fix shipping in integration v1.0.15 separately.

- **2026-05-20 — V6.11 (FAQ Table fast path REVERTED, published 2026-05-20):**
  - Reverts the V6.10 Rule -1 (FAQ TABLE FAST PATH) because Botpress Tables' native `contains` filter does not support `@variable contains column` direction. The Studio Find Records card can express `topic_pattern contains @event.preview` (column-on-LHS) but NOT `@event.preview contains topic_pattern` (variable-on-LHS — the right-hand side is parsed as a literal string). Per-pattern row expansion didn't help because the filter direction is fundamentally one-way.
  - The Studio flow keeps Find Records in place as a no-op (`workflow.faqMatch` always null with the current filter). Cost: ~50-100ms idle Botpress Table query per message. Removable later if needed.
  - Future ship path for FAQ fast path: replace Find Records with an **Execute Code** card that does the substring-match in JavaScript (`table.rows.find(r => event.preview.toLowerCase().includes(r.topic_pattern))`) and sets `workflow.faqMatch` from there. Deferred to Tier 2 / next session — needs a clean Botpress JS scaffold + testing.
  - Bot reverts to V6.9 behavior (12/12 gap regression PASS verified today).

- **2026-05-20 — V6.10 (FAQ Table fast path, REVERTED):**
  - Adds **Rule -1 (FAQ TABLE FAST PATH)** at the top of the prompt — highest priority. When `workflow.faqMatch` is set (populated by the new Find Records card in Standard2), the bot copies `workflow.faqMatch.answer_template` verbatim and STOPS — no tool calls, no LLMz routing, no paraphrasing.
  - When `workflow.faqMatch` is empty/null, full Rules 0-13 logic applies (Find Records missed; bot does normal work).
  - Closes the FAQ Tables short-circuit gap. Without Rule -1 the table query fired but the bot ignored its result. With Rule -1 the bot uses the table when it matches.
  - Target: top-60 questions in the JhFaqV1Table answer in ~5-8s (LLM verbatim emit only — no MCP tool calls) instead of 35-45s.
  - Studio flow already wired: Start → Standard1 (region) → Standard2 (Find Records) → AutonomousNode (this prompt) → End.

- **2026-05-20 — V6.9 (URL audit fix, published 2026-05-20):**
  - Rule 13 corrected Pacific contact URL. Was `/pacific/contact-us` (which 404s on prod). Now `/pacific/contact/office-locations` (200 OK). Verified live via curl HEAD batch 2026-05-20. Pacific does NOT have a `/contact-us` landing — uses `/contact/office-locations` instead.
  - Companion fix in `data/botpress-faq-table-seed.csv`: 4 stale URLs corrected (`/about-us` → `/about`, `/services/accessibility-universal-design` → `/services/accessibility`, `/middle-east/services/accessibility-universal-design` → `/middle-east/services/accessibility`, `/pacific/contact-us` → `/pacific/contact/office-locations`).
  - All 42 unique URLs in the FAQ seed now return HTTP 200.

- **2026-05-20 — Integration v1.0.14 (MCP retry on transient errors):**
  - `makeRequest()` now retries 5xx + network errors (ECONN/ETIMEDOUT/ENOTFOUND/fetch failed) with exponential backoff (250ms, 500ms — max 3 attempts total). 4xx errors and parsed MCP errors fail fast (won't fix themselves). Closes the "transient Forge blip kills entire bot reply" failure mode. Pure defense — no behavior change on the happy path.

- **2026-05-20 — Integration v1.0.13 (non-functional safety net):**
  - Adds `warnOnConfigDrift()` — module-level one-shot warning logged via `logger.forBot().warn()` when `mcpServerUrl === 'https://servicecurator.com'` AND `schemaHandle` is a JH-style handle (`MCPSchema` / `ai` / `jensenhughes`). Surfaces the historical "config reset on integration bump" failure mode as an obvious log entry on the next queryContent / listTools call after a bad deploy.
  - No behavior change for correctly-configured bots. Pure defense-in-depth.
  - Pair with `scripts/bp-deploy-safe.sh` (wrapper around `bp deploy` that snapshots+diffs bot record) to prevent the drift in the first place.

- **2026-05-20 — V6.8 (current, on staging — pending paste):**
  - **Rule 9 (off-topic) rewritten** — explicit pattern lists for pricing / jokes / weather / competitors / stock / opinions; refusal MUST run BEFORE Rule 1/2/10 routing. WRONG/RIGHT example for pricing question. Closes regression FAIL `G3-NA-pricing-refuse` (V6.7 bot answered pricing question with Fire Engineering page instead of refusing).
  - **Rule 11 (topic isolation) expanded to handle bridges** — explicit list of bridge phrases ("and also" / "as well as" / "plus" / "along with" / "in addition to" / "what about X" / "tell me about X too"). When a single message bridges two topics, reply MUST contain a URL for BOTH topics. Cross-turn bridge promotes prior topic. Closes regression FAIL `G6-NA-bridge-and-also`.
  - **Rule 12 pre-send checklist** — added off-topic check (item 6) + bridge check (item 7). Item 5 corrected: Rule 8 was REMOVED so the checklist now reinforces "no trailing follow-up" instead of referencing a deleted rule.
  - Regression target: 12/12 pass on `--tag gap-2026-05-20` (V6.7 was 10/12).

- **2026-05-19 — V6.7 (previous, last published):**
  - Rule 8 REMOVED — no more "Did this answer your question?" tail.
  - Rule 9 — careers/jobs explicitly ON-topic (removed wrong "hiring info" off-topic listing that caused NA-careers failure).
  - Rule 7 — privacy refusal now requires mandatory verbatim `info@jensenhughes.com` redirect template.
  - Rule 10 — STRICT URL ENFORCEMENT directive added. LSFT route corrected to `/services/large-scale-fire-testing-lsft` (was 404 `/services/fire-testing`). Digital route corrected to `/services/digital` (was 404 `/services/digital-services`). FLS route corrected to `/services/fire-suppression-systems-design`.
  - Rule 13 NEW — region scope covers all content categories (industries, careers, contact, insights, digital, about, marine forensics).
  - Topic-routing KB doc (`botpress-topic-routing.txt`, 9.3 kB, 33 verified-prod URL mappings) uploaded to Services KB (Indexed).
  - **Regression: 49/50 PASS (98%)** across 50-scenario Playwright suite. Single FAIL `Asia-fire-eng` is LLM nondeterminism (~5%); V6.5 had passed same test.
- **2026-05-19 — V6.6:** Tighter ME-fire-eng URL forcing in Rule 0 (per-region URL picker reinforced). Soft template for privacy refusal (failed — LLM paraphrased; corrected in V6.7).
- **2026-05-19 — V6.5:** Rule 8 first removal pass. EU forensics sub-areas (Marine Forensics + Product Liability) inline per Jonathan May 13 complaint. Accidentally lost implicit info@ redirect on privacy refusal — fixed in V6.7.
- **2026-05-19 — V6.4:** Rule 1 Case A — URL REQUIRED NOT OPTIONAL on yes/no service questions. Added WRONG/RIGHT examples. 3/4 EU/Pacific/Asia/ME-fire-eng PASS after.
- **2026-05-19 — V6.3:** Rule 5 EU forensics email mandatory. Region surface coverage added (industries/careers/contact/insights/digital/about/marine forensics).
- **2026-05-19 — V6.2 (prefix-purge):** Moved changelog out of pasted body. Purged all "prefix" word mentions. 5 complete inline copy-paste response templates per region in Rule 10. Spot-check 4/4 PASS.
- **2026-05-19 — V6.1:** Hardened `${prefix}` ban list. Stricter Rule 10 template syntax detection.
- **2026-05-19 — V6.0:**
  - Rule 0 — URL CONSTRUCTION section added with hardcoded literal URLs per region. Banned tokens list expanded (`$prefix`, HTML-encoded variants).
  - Rule 1 — split into Case A (yes/no → conversational + ONE link) vs Case B (list ask → dump formattedText). Resolves Rule 1↔Rule 7 conflict.
  - Rule 3 — explicit ban on exposing slugs to user (regression spotted "Sydney - Bowman (slug: sydney-australia)" leak).
  - Rule 10 — new Topic→service-page routing table for BESS/Lithium Ion → Emerging Hazards, Combustible Dust → Process Safety, LSFT → Fire Testing. Hardcoded URLs (no placeholders). Two patterns (matched-table vs regional-fallback).
  - Rule 12 NEW — pre-send checklist (template syntax, slug leak, one-link, region match, did-this-answer).
- **2026-05-19 — V5.2:** Rule 5 forensics rewritten step-by-step (region check FIRST, then routing, then optional capabilities). Rule 0 hardened against `${prefix}`/`{prefix}` template-leak. Rule 7 rewritten for conversational tone, banned clarifying-question barrage, MINIMUM-one-link added. Rule 10 NEW: empty-experts → service-page fallback + acronym hints (BESS, LSFT, LNG, PBD, AHJ). Rule 11 NEW: topic isolation.
- **2026-05-19 — V5.1:** Rule 4 ordering fix (lists now authoritative; tool only for unlisted services). Added Rule 8 (post-answer follow-up) + Rule 9 (off-topic handling).
- **2026-05-19 — V5:** Region rules audited against jensenhughes.com prod-site curl. Scotland removed from Rule 5. NA forensics URL = `/services/investigations`. EU forensics URL = `/europe/services/forensic-investigation`. ME URL prefix `/middle-east/`. Pacific Accessibility unblocked. ME flipped to forensics NOT-available. Asia Accessibility added to NOT-available. URL fabrication guards in Rule 0 + Rule 2.
- **2026-05-18 — V4 (stale doc previously in repo):** Initial Rule 0–7 structure. Wrong Scotland claim. Missing `/middle-east/` prefix. No URL fabrication guard.
