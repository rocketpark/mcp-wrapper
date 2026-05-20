# FAQ Tables — Studio Setup

**Goal:** Move the ~60 highest-frequency JH bot answers from LLM-driven AutonomousNode generation to a deterministic **Botpress Table** lookup. Result: top questions answered in ~2s instead of 35-45s, 20-40% AI Spend reduction.

**Seed data:** `data/botpress-faq-table-seed.csv` (60 rows, region-scoped).

**Audience:** Liz performing Studio clicks.

---

## Why this works

Bot currently routes EVERY question through AutonomousNode + LLMz + KB + MCP. Most of that latency + cost is fixed. For the top ~60 questions (forensics, fire eng, accessibility, regional contact, off-topic refusals), the answer is **known and static** — no reason to spend LLM tokens generating it.

Add a **Find Records** card as the first step in AutonomousNode's flow:
- If user question matches a row's `topic_pattern` (regex or keyword) → return `answer_template` verbatim, splice region URL from row
- If no match → fall through to current LLMz path (no behavior change for novel questions)

The bot still feels conversational because the answer templates ARE conversational (V6.8 phrasing baked in). User can't tell the difference except things are faster.

---

## Step 1 — Create the Table in Studio

1. Open Studio: https://studio.botpress.cloud/208ffbe5-a209-4a10-a52c-d79de4577f45
2. Left sidebar → **Tables** → **+ New Table**
3. Name: `jh_faq_v1`
4. Description: "Region-scoped fast-path Q&A. First Find Records card in AutonomousNode flow."
5. **Columns** — add each in this order:
   - `topic_pattern` (text, indexed)
   - `region` (text, indexed) — values: `na` / `europe` / `pacific` / `asia` / `middle_east` / `global`
   - `answer_template` (text, long)
   - `url` (text)
   - `priority` (number) — 1=hot, 2=warm, 3=cold (controls match precedence)
   - `notes` (text, long)
6. Save schema.

## Step 2 — Import the seed CSV

1. Tables → `jh_faq_v1` → **⋯ menu** → **Import CSV**
2. Upload `/Volumes/LizsDisk/mcp-wrapper/data/botpress-faq-table-seed.csv`
3. Map columns 1-to-1 (the CSV headers match the Table schema exactly).
4. Confirm import. Should show 60 rows imported.
5. Spot-check 3 rows: forensics EU, fire engineering ME, BIM global. Verify the template + URL look right.

## Step 3 — Wire Find Records card in AutonomousNode flow

In Studio's AutonomousNode flow editor:

1. Open the flow that contains the AutonomousNode.
2. **Before** the AutonomousNode card, add a **Find Records** card:
   - Source: `jh_faq_v1`
   - Filter:
     ```
     (topic_pattern matches user.lastMessage)
     AND (region == workflow.region OR region == 'global')
     ```
   - Order by: `priority` ASC
   - Limit: 1
   - Output variable: `workflow.faqMatch`
3. **After** Find Records, add a **Branch** card:
   - If `workflow.faqMatch` is set → emit `workflow.faqMatch.answer_template` as bot message → END
   - If `workflow.faqMatch` is empty → continue to AutonomousNode (current behavior)

The Branch ensures novel questions still hit LLMz. Only the matched ~60 high-traffic questions short-circuit.

## Step 4 — Save + Publish

1. Save the flow.
2. **Publish bot** (top right of Studio).
3. Open webchat on staging, test a known-FAQ question (e.g. "Do you offer forensic services?" while on `/europe`):
   - Should respond in ~2s
   - Should match the EU forensic template verbatim (including `instructus.uk@jensenhughes.com`)
4. Test a non-FAQ question (e.g. "What's a Class A fire?"):
   - Should still respond via LLMz path
   - Latency unchanged (35-45s)

## Step 5 — Re-run regression

```bash
cd /Volumes/LizsDisk/mcp-wrapper
node scripts/regression/jh-bot-regression.mjs --tag gap-2026-05-20 --verbose
node scripts/regression/jh-bot-regression.mjs --tag forensics --verbose
node scripts/regression/jh-bot-regression.mjs --tag aldiana-v6 --verbose
```

Most matched scenarios should now report **<5s latency** in the verbose output (vs current 35s+). All 12/12 + 8/8 + ~all tagged tests should still PASS — answers are byte-identical to V6.8 templates by design.

---

## How to add / edit rows

**Quick edit (single row):**
- Studio → Tables → `jh_faq_v1` → click row → edit cell → Save → Publish bot.
- Change is live within seconds.

**Bulk edit:**
- Studio → Tables → `jh_faq_v1` → **Export CSV** → edit locally → **Import CSV** with "Replace all" option.
- OR edit `data/botpress-faq-table-seed.csv` in this repo → re-import.

**Add a new pattern:**
- Add row to CSV.
- For multi-region patterns (services that exist in multiple regions): one row per region. Use `region: global` only if the answer is identical across regions.
- `topic_pattern` supports pipe-separated synonyms: `"BESS|battery energy storage|lithium ion"`. Find Records matches any.
- Test the new pattern in webchat before publishing widely.

---

## Why this seed (vs. mining real questions)

The 60 rows were sourced from:
- V6 prompt **Rule 10** topic-routing table (~20 mappings, already verified-prod)
- V6 prompt **Rule 4** regional service-availability matrix (5 services × 5 regions = 25 rows)
- V6 prompt **Rule 5** forensics + sub-areas (Marine, Product Liability)
- V6 prompt **Rule 6** BIM hardcoded URL
- V6 prompt **Rule 7** privacy refusal
- V6.8 **Rule 9** pricing + off-topic refusals
- Regression suite top scenarios (careers, contact, about)

This is the **deterministic floor** — every row here is V6 prompt logic that's already meant to be byte-identical. Moving these to a Table eliminates the LLM as a source of variance on these answers.

**Next iteration:** After 2 weeks of running, open Botpress **Analytics tab** to see actual top user questions. Add the high-frequency ones not yet in the Table. The audit doc (Tier 2 item #7) is the long-term FAQ-Tables tuning loop.

---

## Rollback

If Find Records misroutes (e.g. a novel question gets a wrong-region template):

1. Studio → flow editor → **disable** the Find Records card (toggle off).
2. Republish. Bot falls back to AutonomousNode-only behavior. Zero downtime.

The seed data is in this repo; the Table in Botpress is separate. Either can be rebuilt from the other.

---

## Open question for Jonathan

When a FAQ Table row fires, the bot bypasses LLMz entirely — no per-conversation tone adjustment. The answer is fixed.

For the ~60 high-traffic questions, that's the **point**. But if Jonathan wants the bot to feel "conversational" even on common questions, we'd lose some of that. Trade-off: speed + consistency vs. micro-personalization.

Recommendation: ship as-is. If Jonathan flags "feels too rigid," revert the Branch card (Step 3) and the bot returns to AutonomousNode-only with no other change.
