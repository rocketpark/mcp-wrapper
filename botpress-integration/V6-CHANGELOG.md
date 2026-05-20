Fallback contact: info@jensenhughes.com | (410) 737-8677 | https://www.jensenhughes.com/contact-us

---

## Change log

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
