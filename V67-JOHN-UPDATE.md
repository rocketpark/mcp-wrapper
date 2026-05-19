# JH Bot Update for John (2026-05-19)

## TL;DR

Bot prompt rewritten today (V5 → V6.7 over 7 iterations) addressing your May 13/14 complaints + Aldiana's 11/17 + 11/19 feedback. Live verified on staging: 49/50 regression scenarios pass (98%), all your specific complaints fixed, no template leaks, no slug leaks, no "Did this answer your question?" tail, and bot now sounds human + always emits a URL.

## What's fixed (your specific items)

**1. Marine Forensics in Europe (May 13 screenshot — "becomes a blocker"):**
Bot now answers yes + links the marine-fire-forensics page directly + instructus.uk@ email. No more clarifying barrage.

**2. EU Forensic services list incompleteness (May 13 screenshot — "I feel like I'm getting a complete list, so maybe I walk away not realizing they offer marine forensic services"):**
Bot now lists Marine Forensics + Product Liability Investigations sub-areas inline on a generic EU forensic services question.

**3. Region propagation (May 14 screenshot — bot answered with "North America industries" when user asked about Pacific):**
Twig footer race fix + Botpress integration v1.0.10 + V6 prompt's region-aware queryContent contract. Bot now correctly returns Pacific/Asia/EU/ME industries when asked from those regional pages.

**4. Wrong-hostname URLs (May 14 — bot was emitting staging URL `jensenhughes3.on-forge.com`):**
EntryTools.php normalization + Rule 0's hardcoded URL list. Bot now emits `www.jensenhughes.com` URLs.

**5. Topic-switch confusion (May 14 — user changed region from Pacific to Asia and bot stayed on Pacific):**
Rule 11 topic isolation. Live verified today: ask Pacific question, then switch to Asia, bot correctly switches.

## What's also fixed (you mentioned in our meeting)

**6. "Did this answer your question?" appended to every reply:**
Rule 8 fully removed. Bot stops after the URL.

**7. Bot responses too bot-like / too many URLs / generic:**
Rule 1 + Rule 7 rewritten. Bot leads with conversational 1-2 sentence answer + ONE primary link. No more list dumps for yes/no questions.

**8. Greeting message:**
Bot Description in Studio is the welcome — visible on chat open as the welcome card. No action needed.

**9. "Last bottom message" (footer plain text — "Need to speak with someone? Visit our contact page"):**
Identified — Botpress webchat v3 platform limit (footer doesn't render as a clickable link). Can change the footer text or accept. Your call.

## What I'm still flagging

**10. Typing indicator during tool calls:**
Botpress webchat shows typing dots only during LLM token generation, not during MCP/Craft tool calls (where most latency lives). Platform limitation. Could be addressed via a flow-level "Looking up…" intermediate bot message if you want. Not yet built.

**11. Response speed:**
Each question runs ~35–45 s end-to-end. Two factors: (a) Always Alive is disabled (cold start adds a few seconds — $5/mo to enable, your call), (b) MCP tool calls to Craft GraphQL. Tool cache is wired but only helps on repeat-arg queries. Real speed win for users would need Always Alive on.

**12. Utah LSFT lab content gap:**
Aldiana flagged the "labs page missing Utah location." That's a content gap on prod jensenhughes.com — the bot can only link content that exists. Sarah Pichardo's update to /services/in-house-laboratories-testing would resolve.

## Aldiana 11/17 + 11/19 fixes

All 9 items from her 11/17 ticket + 4 items from her 11/19 doc addressed:
- Combustible Dust → dedicated `/services/combustible-dust-safety` page
- BESS / Lithium Ion → `/services/lithium-ion-risk-consulting`
- LSFT → `/services/large-scale-fire-testing-lsft` (was wrong slug `/services/fire-testing` → 404)
- LNG → Process Safety
- Topic mixing across questions → Rule 11 fix
- Bot dumping lists without context → Rule 7 + Rule 1 Case A
- Bot refusing hiring questions → Rule 9 careers ON-topic fix
- Privacy refusal now includes info@jensenhughes.com redirect

## Numbers

- 49 of 50 automated regression scenarios pass on V6.7 (98%)
- 5 / 5 regions tested for forensics, accessibility, fire engineering, services, industries, contact, careers, about
- 0 template-syntax leaks (`${prefix}` etc — was real bug in V5)
- 0 internal-slug leaks ("Sydney - Bowman (slug: sydney-australia)" — was real bug)
- Bot prompt + 1 new KB file (topic-routing, 9.3 kB) shipped today
- Bot AI Spend today: ~$3 (we're well under the $30 monthly budget)

## What I'd like a green light on

1. Always Alive ($5/mo) — kills cold-start latency for users. Recommend.
2. Loop Sarah Pichardo on the Utah LSFT content gap (it's a real one, just not a bot bug).
3. Production deploy gate — bot is staging-only. Ready to discuss prod rollout cadence (currently no prod hostname in the Twig footer allowlist).

Bot is staying on staging until your sign-off.