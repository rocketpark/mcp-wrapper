# JH Bot — Botpress 2026 Capabilities Audit

**Generated:** 2026-05-20
**Audience:** Liz (technical), Jonathan (product owner)
**Scope:** Compare current JH deployment (V6.8 prompt + craftcms-mcp v1.0.14 + Webchat v3.6 + 3 KBs) against Botpress Cloud 2026 features and best-practice patterns. Tied to Jonathan's specific Feb-May 2026 feedback.

This is a recommendation doc, not an action plan. Each item: what it is, why JH cares, effort, source. You pick what ships.

---

## TIER 1 — ship this week (Jonathan-visible impact)

### 1. **Toggle Always Alive ON** — likely free now

**What:** Botpress's cold-start eliminator. May 14 2026 pricing reset reportedly bundles Always Alive into every paid plan at no extra cost. Auto-conversation packs (100 conv) refill on overage.

**Why JH cares:** Kills cold-start latency, a chunk of Jonathan's 35-45s complaint. The $5/mo decision was likely already moot.

**Effort:** Low. Toggle in workspace settings, zero code.

**Source:** https://botpress.com/blog/pricing-update-may-2026

**Verify before flipping:** the May 14 pricing change applies to "workspaces created after May 14th." JH workspace is older — confirm Always Alive is genuinely free for your workspace on the billing page before flipping. If still $5/mo, flip anyway per V6-HANDOFF #1 recommendation.

---

### 2. **Migrate Playwright regression to Botpress ADK Evals + LLM-as-Judge**

**What:** Botpress's first-party eval framework (`@botpress/adk` v2026). Native `llm_judge` assertions with configurable threshold, supports `openai:gpt-4o` or `anthropic:claude-sonnet-4-6` as judge, `final_response_match_v2` semantic matching. Install: `npx skills add botpress/skills --skill adk-evals`. Typed `Eval` class replaces older `defineEval`.

**Why JH cares:** Direct fix for the ~5% nondeterminism that surfaced today's G3/G6 failures. LLM judge tolerates wording variation (current Playwright string-match flagged a perfectly fine bot reply on G8 because `expectAny` was too narrow). LLM-judge vs human reviewer agreement: ~85% per industry research — higher than human-human agreement.

**Effort:** Medium. Port 62 scenarios to ADK eval files. Keep Playwright for webchat embed/UI checks only.

**Source:** https://github.com/botpress/adk/releases · https://github.com/botpress/skills · https://www.confident-ai.com/blog/why-llm-as-a-judge-is-the-best-llm-evaluation-method

---

### 3. **Add HITL Plugin for escalation path**

**What:** Hand-off-to-human via Botpress's HITL Plugin (the older HITL Agent primitive is deprecated). User clicks a button (or LLM detects frustration) → conversation routes to a "Human needs to take this" queue in the Botpress dashboard → real Jensen Hughes person can pick it up. Drop-in plugin, no custom code.

**Why JH cares:** Today the bot dead-ends with a generic refusal when LLMz can't answer. Jonathan's "conversational quality" goal benefits enormously from "let me get you to a person" instead of "I can't help with that." Particularly true for forensic inquiries that legitimately need a human follow-up.

**Effort:** Low for the plugin install + a Rule 14 addition to the prompt ("if user is frustrated, mentions complaint, or asks for human, trigger HITL"). Medium if you wire it to a Slack/Teams channel for actual JH staff.

**Source:** https://botpress.com/en/integrations/hitl

---

## TIER 2 — ship this month (architecture wins)

### 4. **Hybrid: AutonomousNode + Workflow Nodes for deterministic paths**

**What:** Documented best practice — AutonomousNode handles open-ended conversation, but **Transition cards with keyword conditions** route deterministic paths (privacy refusals, region detection, MCP tool routing with strict args) to Workflow Nodes that bypass the LLM entirely.

**Why JH cares:** Two wins simultaneously.
1. **Determinism.** Rules currently in the 320-line V6.8 prompt (verbatim `info@jensenhughes.com`, one-link-per-response, region match) become unbreakable when routed through Workflow Nodes. The 5% flake disappears for those paths.
2. **Latency visibility.** Workflow cards can emit custom events. Wrapping the MCP tool call in a Workflow Node lets you emit a "Looking up..." typing-indicator hint to the user (the platform's typing dots only show during LLM token generation, NOT during 1-3s MCP/Craft calls — addresses Jonathan's #1 complaint).

**Effort:** Medium-High. Refactor: privacy refusal lifted out of Rule 7 → Workflow Node. MCP routing → Workflow with pre-emit "searching" event. Keep AutonomousNode for genuinely open-ended dialogue.

**Source:** https://botpress.com/docs/studio/concepts/nodes/autonomous-node · https://botpress.com/academy-lesson/managing-ai-responses

---

### 5. **Replace `listEvents` fallback with User Variables**

**What:** Botpress User Variables persist across conversations + are scoped per individual user. Set `user.region` on first `regionContext` event; read everywhere instead of the current global `bpClient.listEvents` scan.

**Why JH cares:** Closes the cross-user leak risk in `src/index.ts:240` documented at lines 202-206 ("most recent event may not belong to the calling user — accepted tradeoff until we move off AutonomousNode"). User Variables ARE the move. Also simpler: kills 30 lines of fallback code.

**Effort:** Low. Add a `regionContextEvent` event handler in integration that sets `user.region`. Replace listEvents reads with `user.region` reads. Ship as craftcms-mcp v1.0.15.

**Source:** https://botpress.com/docs/tutorial/basics/storing-information/scoping-variables

---

### 6. **Use Botpress Analytics tab for AI Spend + question patterns**

**What:** Native dashboard shows per-LLM cost, token consumption, slowest/most-expensive LLMs, conversation volume, message-per-session, returning users.

**Why JH cares:** Solves the "no visibility into user questions" gap (no PostHog/Mixpanel needed) + the "no central AI Spend tracker" gap. Free with the plan. Identifies which 50 questions to next promote to a Tables-backed fast path (see #7).

**Effort:** Low. Open the tab. Optional: weekly export to Liz's Notion **AI Spend Ledger** so JH bot shows alongside CC AI spend.

**Source:** https://botpress.com/docs/learn/get-started/dashboard/bot/analytics

---

### 7. **FAQ Tables + Find Records pre-step before KB hits**

**What:** Move the ~50 highest-frequency known-answer questions (office addresses, basic service descriptions, contact info, careers URLs) to a Botpress **Table**. Add a **Find Records** card as the first card in AutonomousNode — if match, return verbatim; otherwise fall through to KB / MCP.

**Why JH cares:** KBs are the biggest AI Spend driver. Likely cuts the $3/day spend by 20-40% **and** drops latency on those 50 questions from 35s to ~2s (no LLM/MCP roundtrip). Direct win on Jonathan's top two priorities at once.

**Effort:** Medium. Populate Table from existing regression suite questions + Analytics data (after #6 has 2 weeks of data).

**Source:** https://www.botpress.com/docs/learn/guides/advanced/tips-to-optimize-ai-cost

---

## TIER 3 — worth knowing, not urgent

### 8. **Webchat v3.5 / v3.6 React hooks (`bindConversation`, `bindUser`)**

`bindUser` could replace the Twig `sendEvent` regionContext mechanism with a typed user-identity bind from JH's logged-in session — cleaner, eliminates event race. Worth a 2-hour spike when you next touch the embed. **Verify** what's actually in JH's pinned v3.6 vs current npm `@botpress/webchat` 4.5.0.

Source: https://botpress.com/docs/webchat/webchat-components/get-started

---

### 9. **Cron-scheduled Workflows**

Workflow cards accept cron expressions. Not urgent, but useful for: nightly KB freshness checks, weekly "popular unanswered questions" → Slack/email to Jonathan, periodic warm-up pings if Always Alive ever fails.

Source: https://botpress.com/docs/learn/reference/cards/fixed-schedule

---

### 10. **Conversation API for outbound / proactive messages**

`POST /v1/chat/messages` sends to ongoing chats programmatically. Future-proofing for email/SMS follow-up after a conversation, or proactive nudges. Not relevant today.

Source: https://botpress.com/docs/adk/conversations/setup

---

## Anti-patterns currently present in the JH build

1. **100% LLM-driven 320-line prompt with 13 rules.** Botpress docs + community consensus: "keep AutonomousNode focused on one conceptual task." Rules that are deterministic (privacy refusal verbatim, one-link, region routing) belong in Workflow Nodes. The size of the prompt **is** the source of the 5% flake.
2. **Global-scoped `listEvents` fallback.** Botpress has typed scoping primitives (User/Workflow/Session/Bot Variables); falling back to a workspace-wide event query is a misuse. Won't survive concurrent traffic.
3. **Brittle string-match Playwright regression on LLM output.** LLM judge is Botpress-native + better tolerated. String matching against generative output produces noise (e.g. G8 test had to widen `expectAny` today because bot's perfectly fine reply didn't match a narrow keyword list).
4. **No HITL escape hatch.** Every production-grade Botpress deployment has an escalation path; the docs treat HITL as a baseline feature.
5. **Default `servicecurator.com` in `integration.definition.ts`.** Now mitigated by `scripts/bp-deploy-safe.sh` + v1.0.13 soft warning, but worth noting if integration is used by future tenants — they each need to set their own default in a fork.

---

## Quick-win order for Jonathan-visible impact

| Order | Item | Pain point closed | Effort |
|---|---|---|---|
| Day 1 | Toggle Always Alive ON | Cold-start latency | S |
| Week 1 | FAQ Tables + Find Records pre-step | Latency AND cost on top-50 questions | M |
| Week 1 | HITL Plugin install | Bot dead-ends | S+M |
| Week 2 | Move privacy + region rules to Workflow Nodes | Regression flake on deterministic rules | M-H |
| Week 2 | Replace listEvents with User Variables (v1.0.15) | Cross-user leak risk | S |
| Week 3 | Adopt ADK evals + LLM judge | Durable regression confidence | M |
| Ongoing | Open Analytics tab, identify next 50 FAQ Table candidates | AI Spend control + user-question insight | S |

---

## Items still to verify (research agent flagged)

- **Always Alive workspace eligibility.** Excerpt says "applies only to workspaces created after May 14th, 2026" — needs verification whether JH's existing workspace gets auto-migrated.
- **Webchat v4 vs v3.6.** npm shows 4.5.0 but JH embed is v3.6 CDN — unclear if there's a v3→v4 migration story or these are different distribution channels. Worth a Botpress Discord ask.
- **HITL Plugin pricing.** Unclear if it counts against conversation quota or is a flat add-on.

---

## Cross-reference: items closed by today's V6.8 + v1.0.14 ship

- V6.8 Rule 9 strengthened (pricing refusal) — addresses Jonathan's "more conversational, fewer paragraphs" lean
- V6.8 Rule 11 bridge handling — fixes "and also" dropping second clause
- v1.0.12 office search no longer over-matches "India" against `Middle East + India` region label — addresses Jonathan's regional accuracy goal
- v1.0.13 config-drift soft warning — addresses the silent-reset failure mode he hasn't seen yet but would have
- v1.0.14 MCP transient-error retry — closes "I couldn't reach our knowledge base" silent failures
- M17 regression retry-on-fail — kills the false-fail noise that wastes Liz's time

These are tactical wins. The Tier 1-2 audit items above are the strategic ones.
