# JH Bot — Improvements Backlog

**Generated:** 2026-05-20 (after V6.7 ship)
**Format:** Categorized — Safe / Medium / Risky. You pick what ships. Nothing goes live silently.
**Legend:** P=priority (1=now, 2=this week, 3=this month, 4=eventually). E=effort (S=<1h, M=half-day, L=1day+).

---

## SAFE — no behavior change, low blast radius

Pure cleanup. Can ship same day, low review burden.

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| S1 | **Delete dormant user tags schema** | `botpress-integration/integration.definition.ts:138-157` | 3 | S | Twig writes user.data only; SDK drops tag writes. Tags block is dead. Removing requires a bp deploy. |
| S2 | **Delete unused integration actions** (`intelligentSearch`, `answerQuestion`) | `integration.definition.ts:91-130` + handlers in `src/index.ts` if any | 4 | S | Not called by bot. Dead code surface = future confusion. |
| S3 | **Archive V5 prompt mirror** | move `AUTONOMOUS-NODE-INSTRUCTIONS-V5.md` to `docs/archive/` | 3 | S | V6.md is canonical. V5 file lingering invites paste mistakes. |
| S4 | **Move V6-CHANGELOG.md to docs/** | currently in `botpress-integration/V6-CHANGELOG.md` | 4 | S | Cross-link from the V6 instructions file. Docs hub > scattered. |
| S5 | **Resolve _meta_footer.twig duplication** | `Herd/jensenhughes/templates/_meta_footer.twig` vs `templates/_partials/_meta_footer.twig` | 2 | S | Two near-identical files; partial has the better tryRegister loop + idempotency comment. Confirm only one is rendered; delete the loser. |
| S6 | **Hardcoded HTTP basic auth creds in regression** | `scripts/regression/jh-bot-regression.mjs:24` | 1 | S | `JHstaging2026!` in plaintext, checked into git. Move to `STAGING_HTTP_AUTH` env or `.env.local`. |
| S7 | **Reconcile tool count claim** | `JENSEN-HUGHES-IMPLEMENTATION.md:135-203` says 17 tools; V6 prompt Rule 2 lists 14 | 4 | S | Pick the truth (count tools via `/mcp/MCPSchema` tools/list), update both docs. |
| S8 | **Gate verbose request/response logging** | `botpress-integration/src/index.ts:28-65` (`logger.forBot().info` of full JSON-RPC bodies) | 2 | S | Customer questions logged in clear. Wrap behind `process.env.MCP_DEBUG` flag. |
| S9 | **README cross-links** | `mcp-wrapper/README.md`, `JENSEN-HUGHES-IMPLEMENTATION.md` | 4 | S | Neither links to new docs (architecture, improvements, KB strategy). Add an "Architecture" section index. |

---

## MEDIUM — semantic change, isolated blast radius

Worth doing soon. Each fits a half-day. Most need tests + a staging round-trip.

### Bot logic / prompt

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| M1 | **Add multi-turn topic-switch test** | regression script (currently single-turn only) | 2 | M | Rule 11 verified manually per V67-JOHN-UPDATE.md; not in suite. Cookie-clearing pattern complicates this — would need state-preserving session. |
| M2 | **Korean site regression coverage** | `REGIONS` map in regression, new tag `korean` | 3 | M | `jensenHughesKorea` maps to region=asia in Twig + integration, but bot has no Korean response templates. Either add Korean KB or document English-only fallback. |
| M3 | **Topic-routing KB link health check** | `data/botpress-topic-routing.txt` (33 URLs) | 2 | M | Stale URL = Rule 10 breaks. Script: curl HEAD each URL nightly, fail CI if any 404. Doable in 30 lines of bash. |
| M4 | **Always Alive ($5/mo)** | Studio webchat config | 2 | S | Sub-30s response times. Pending Jonathan sign-off per V67-JOHN-UPDATE.md. |
| M5 | **Flow-level "Looking up..." intermediate msg** | Botpress flow editor | 3 | M | Workaround for typing-indicator-only-during-LLM-gen platform limit. Aldiana 11/17 #1 item. |
| M6 | **Hardcoded EU forensics email** (`instructus.uk@jensenhughes.com`) | V6 prompt Rule 5 | 4 | M | If JH legal rotates this address, bot breaks silently. Move to KB or `craft_resolve_regional_url` response so it can change without prompt redeploy. |
| M7 | **Rule 9 careers/hiring tests for Asia/ME** | regression suite | 3 | S | NA/EU/Pacific careers covered, Asia/ME not. |

### Integration / TS

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| M8 | **listEvents fallback — filter by userId** | `src/index.ts:240` (`bpClient.listEvents({})`) | 2 | M | Currently bot-scoped scan = cross-user leak under concurrent regions. If `ctx.user?.id` exists in IntegrationContext, pass to listEvents filter. If not, log clearly so we know the cap. |
| M9 | **MCP transient-error retry** | `src/index.ts:31-65` `makeRequest` | 2 | M | No retry on initial MCP call. 5xx kills the whole reply. Add exponential backoff (max 2 retries, 250/500ms). |
| M10 | **`query_officeLocations` enrichment N+1** | `src/index.ts:468-end` | 3 | M | When user searches offices, integration calls `craft_get_office_contact_info` for each result. 50 offices = 50 round-trips. Add a single `craft_batch_office_contact_info` PHP tool, or cache aggressively. |
| M11 | **JH-specific logic in "generic" integration** | `src/index.ts:303-421` (Regional Leadership filter) | 4 | L | `craftcms-mcp` is supposedly generic but hardcodes JH semantics. Either rename to `craftcms-mcp-jh` or feature-flag the JH path behind config. |
| M12 | **listEvents shape unit tests** | new `tests/integration/region-resolution.test.ts` | 3 | M | Code comment lists 3 payload shapes — none exercised in tests. Add table-driven test with mock payloads. |

### MCP server (PHP)

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| M13 | **`craft_resolve_regional_url` unit tests** | `tests/Unit/` | 2 | M | Heavily used by V6 prompt, zero dedicated test coverage. |
| M14 | **GraphQL schema-leak regression test** | new `tests/test-mcp-schema-scope.php` | 1 | M | Jan 26 incident leaked all 75 tools when DB scope was empty. Fail-safe is now "allow none" but no automated test that catches a regression. |
| M15 | **`formattedText` snapshot test** | exercised in `tests/test-mcp-direct.js` but not asserted | 3 | M | V6 prompt depends on this string shape (Rule 1 Case A). Lock it down. |

### Test infrastructure

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| M16 | **CI hookup for regression suite** | `.github/workflows/jh-regression.yml` | 2 | M | Mentioned in regression README, not built. Trigger on push to `feature/mcp-improvements`, post Slack on fail. |
| M17 | **Re-run-on-fail to fight LLM nondeterminism** | regression script | 3 | S | Currently 1 try; ~5% false-fail. Loop the failure once before reporting. |
| M18 | **Move staging creds to env file** | regression script | 1 | S | (Same as S6 — listed there with priority detail.) |

### Observability

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| M19 | **Bot question analytics** | PostHog or Mixpanel | 3 | M | Zero telemetry on what users actually ask. Need: question text, region, tool calls fired, reply length, latency, success/fallback. Critical for V7 prompt tuning. |
| M20 | **Daily AI-spend digest** | hook into Botpress billing API | 3 | S | $3/day mentioned, no central tracker. Notion AI Spend Ledger pattern (per memory `feedback_wire_shipping_pattern.md`) fits well. |

---

## RISKY — multi-system, hard to rollback, requires gates/sign-off

These are real product decisions. Each needs explicit go.

| # | Item | Where | P | E | Notes |
|---|---|---|---|---|---|
| R1 | **Production hostname rollout** | `_meta_footer.twig:34` add `www.jensenhughes.com` | 1 | M | Bot is staging-only. Adding prod hostname = bot goes live for ~all JH visitors. Needs: John+Jonathan signoff, content lock, support coverage plan, rollback plan (revert PR + force Forge deploy). |
| R2 | **Webchat asset CDN dependency** | `_meta_footer.twig:54` `https://files.bpcontent.cloud/2025/12/17/.../*.js` | 2 | L | Public CDN URL. If Botpress invalidates it (e.g. publishing a new build), embed breaks silently. Mitigations: pin via SRI hash, monitor uptime, document "what to do if it 404s". |
| R3 | **GraphQL token rotation** | `MCP_GQLSCHEMA_TOKEN` in Forge env | 2 | M | When was it last rotated? Procedure: generate new in Craft CP → update Forge env → push to invalidate Craft session cache → confirm bot still works. Document the runbook. |
| R4 | **Botpress PAT rotation** | scripts that use it | 2 | M | Same shape as R3 for KB sync scripts. |
| R5 | **Off AutonomousNode → Flow + Manual mode** | Botpress Studio rearchitect | 4 | L | LLMz is 100% LLM-driven. No per-arg Manual mode = nondeterminism + region race risks documented in `src/index.ts:202-206`. Long-term cure. Trade-off: lose AutonomousNode's flexibility for harder-coded tool-call args. |
| R6 | **Multi-language KB (French/Danish/Dutch/Finnish/Korean)** | Botpress KBs + V6 prompt | 4 | L | Bot is English-only. EU languages route to `region=europe` but the bot replies in English. For multilingual rollout, need: localized KB content, prompt localization, language detection branch in flow. |
| R7 | **Move off shared anonymous user model** | Botpress webchat auth | 4 | L | Webchat tracks anonymous users by cookie. Two visitors on same machine share state. Authenticated webchat needs JH SSO integration. |
| R8 | **Concurrent-traffic per-user state** | needs Manual mode in Botpress or custom user store | 4 | L | listEvents fallback is global-scoped. Real fix: switch to user-scoped event read (R5 dep) or build a per-session region cache outside Botpress. |

---

## Recommended ship order (my call — your veto)

**This week (P1 + P2):**
1. **S6** move staging creds out of git
2. **R1** prod hostname rollout (after John/Jonathan signoff — biggest user-visible win)
3. **M14** GraphQL schema-leak regression test (closes the Jan 26 incident class)
4. **M8** listEvents userId filter (closes the multi-user race)
5. **M9** MCP retry on 5xx
6. **M16** CI hookup (so M14, gap tests, future regressions actually fire)
7. **S5** dedupe Twig footers
8. **M3** topic-routing link health check (one stale URL breaks the bot)
9. **M4** Always Alive (after Jonathan)

**This month (P3):**
- S1, S2, S3, S4 cleanup
- S8 logging gate
- M1, M2 expanded regression coverage
- M5 typing-indicator workaround
- M13, M15 missing PHP tests
- M19, M20 analytics
- R3, R4 secret rotations

**Eventually (P4):**
- R5 off AutonomousNode
- R6 multi-language
- R7, R8 concurrent-user architecture

---

## What I would NOT do

| Anti-pattern | Why |
|---|---|
| Adding a 14th rule to V6 prompt | Already 320 lines + KB doc. One more rule pushes context budget. Use KB for new mappings (worked for topic-routing). |
| Rewriting the integration in a hot patch | v1.0.11 is stable. M8/M9/M10 are surgical adds. Resist refactor temptation. |
| Removing the listEvents fallback | Belt + suspenders. Defense-in-depth. The race documented in code is acceptable until R5. |
| Inline-editing the Studio prompt without re-paste from V6.md | Drift. Source of truth is the .md file. Always paste from there. |
| Adding a "Did this help?" feedback flow before R5 | Botpress Always Alive + flow-level state = same root cause that makes Rule 11 hard. Don't compound. |
