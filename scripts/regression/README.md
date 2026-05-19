# JH Botpress Regression Suite

Automated Playwright suite that exercises the live Jensen Hughes Botpress
webchat embed on staging and checks responses against an expected/banned-substring
test plan. Use it before every prompt or KB change to catch regressions
(forensics, region routing, URL fabrication, privacy refusal, etc.).

## Why

Manual regression testing across 5 regions and a dozen topics is slow and
easy to skip. This script removes the human-cost floor — run it before
publishing any prompt or KB update and you've verified ~21 critical paths
in ~10 minutes.

## Test coverage (21 scenarios)

| Tag | Region coverage | What it asserts |
|-----|----------------|-----------------|
| `forensics` | NA / EU / Pacific / Asia / ME | Correct primary URL (no Scotland), correct email, NOT-available phrasing for unavailable regions |
| `accessibility` | EU / Pacific / Asia / ME | EU not avail; Pacific avail; Asia not avail; ME uses `/middle-east/` |
| `fire-engineering` | EU / Pacific / Asia / ME | Regional URL prefix used; no global slug fabrication |
| `restriction` | EU / Pacific | Security / Emergency Mgmt restrictions per region |
| `office` | NA / EU / ME | Rule 3 office contact format (Oakland, London, Dubai) |
| `bim` | NA | Rule 6 — exact `bimfire` (one word) slug |
| `privacy` | NA | Rule 7 — refuses to share personal email |

## Setup

```bash
npm install -g playwright
playwright install chromium
```

## Run

```bash
# Full suite (~10 min — 21 tests with 28s wait per reply)
node scripts/regression/jh-bot-regression.mjs

# Filter by region
node scripts/regression/jh-bot-regression.mjs --region EU

# Filter by tag
node scripts/regression/jh-bot-regression.mjs --tag forensics

# Verbose (prints last 300 chars of each reply, even on PASS)
node scripts/regression/jh-bot-regression.mjs --verbose
```

Exit code: `0` = all pass, `1` = any fail, `2` = fatal error.

## Adding a test

Append to the `TESTS` array in `jh-bot-regression.mjs`:

```js
{
  id: 'EU-marine-forensics',          // unique snake-case ID
  region: 'EU',                       // NA | EU | Pacific | Asia | ME
  tag: 'forensics',                   // free-form tag for filtering
  q: 'Do you offer marine forensics?', // exact user message
  expect: ['marine-fire-forensics', 'Europe'],  // all must appear (case-insensitive)
  deny:   ['/scotland'],                          // any of these = FAIL
},
```

## Known gotchas

- **Race timing.** The Twig footer's `sendEvent regionContext` payload takes
  ~8–10s to populate `workflow.region` after delivery. We wait 12s after
  setup before the first `sendMessage`. Reducing below 10s causes random
  failures.
- **Webchat user persistence.** Botpress webchat tracks anonymous users via
  cookie. Without wiping cookies between regions, a previous region's
  user.data leaks into the next test. The script clears
  `document.cookie + localStorage + sessionStorage` between region switches.
- **LLM nondeterminism.** Bot has ~95% reliability (AutonomousNode is
  100% LLM-driven, no per-arg Manual mode in Botpress). Expect occasional
  false fails on the partial-match `Asia-forensics` test. Re-run any single
  failure before treating it as a real regression.
- **Staging only.** Hardcoded to `jensenhughes3.on-forge.com` + staging
  HTTP auth. Production Twig embed gate (`serverName` allowlist) blocks
  the bot on prod hostnames anyway. Update `STAGING_URL` / `HTTP_AUTH`
  if either changes.

## Question Bank coverage

This suite covers ~21 of the 230+ questions in the Botpress Bot Question
Bank doc (`dguda-14691` in ClickUp). Highest-priority categories covered:
office contact, forensics, restrictions, BIM, privacy. Open gaps: industries,
insights/case studies, complex multi-part queries, edge cases (jokes,
nonsense input), real-customer scenarios. Add tests as gaps are identified
in the field.

## CI integration (optional)

To run on every push to `feature/mcp-improvements`, add a workflow that:
1. Installs Node 20 + Playwright Chromium
2. Runs `node scripts/regression/jh-bot-regression.mjs`
3. Posts results to a Slack channel

GitHub Actions example pending — would need an Anthropic API key + Botpress
secret access for the embed, plus runner egress allowed to `jensenhughes3.on-forge.com`.
