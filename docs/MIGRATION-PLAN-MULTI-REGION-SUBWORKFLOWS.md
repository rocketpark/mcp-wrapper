# Migration Plan: Multi-Region Sub-Workflow Restructure

**Date:** 2026-05-11
**Author:** Liz Stein (with Claude Code research agent)
**Status:** Proposed — execute in next focused Studio session

## Why

Current Botpress setup uses a **single AutonomousNode** with a ~5,400-char prose prompt + 7 flat KBs. The recurring "edit instructions → re-test → new bug" loop (forensics URL, EU service availability, capability/availability contradictions, 404 deep URLs) is a symptom of this monolithic shape — not random bugs.

Research agent (May 11, 2026) verdict: keep Botpress + MCP, replace monolithic node with Botpress' own **Advanced Starter Template** pattern (Standard Node router → per-region Autonomous sub-workflows + tag-filtered KBs). Same bot ID, same workspace, same MCP plugin. Reorganization inside Studio.

## Outcome

After migration:
- 5 sub-workflows (Global/Americas, Europe, Pacific, Asia, MiddleEast)
- Each sub-workflow has ~1,500-char prompt scoped to its region's services + URLs only
- Each sub-workflow's Search Knowledge Card filters KB content by `region:` tag
- Europe sub-workflow LITERALLY cannot offer Accessibility/Security/Emergency Mgmt — those services aren't in its instructions or KB scope
- 404 URLs class disappears — each region only has URLs from its own scope
- Capability/availability contradiction class disappears — only services that exist are listed

## Pre-flight checklist

- [ ] Bot is **NOT actively being tested by Jonathan or Aldiana** (avoid mid-edit feedback collisions)
- [ ] Latest published version backed up (Botpress versions panel — pin current as `v6-pre-restructure`)
- [ ] All 7 KB files current + indexed
- [ ] Twig templates synced (`_meta_footer.twig` and `_partials/_meta_footer.twig`)
- [ ] Forge cron NOT mid-run (KB sync paused or scheduled outside migration window)
- [ ] Access to Botpress Cloud with Plus plan limits checked (multi-workflow allowed)
- [ ] At least 2 hours uninterrupted (Studio canvas work cannot be hurried)
- [ ] Fresh Chrome session (Studio degrades with long-running tabs; previous Playwright session crashed twice)

## Phase 1 — KB region-tagging (do BEFORE Studio restructure)

Goal: each KB document has `region:` metadata so Search Knowledge Cards can filter.

1. Update `scripts/sync-services-to-botpress.js`:
   - Read Craft entries with `siteId` per regional site (Europe, Pacific, Asia, MiddleEast, Global)
   - For each region, push services as separate KB documents with `tags: { region: '<handle>' }`
   - Use Botpress Files API `PUT /v1/files` with tags payload
2. Same pattern for `sync-industries-to-botpress.js`, `sync-offices-to-botpress.js`
3. Run sync command: `php craft mcp-wrapper/sync-kb`
4. Verify in Botpress: each KB document has `region` tag in `Knowledge Bases → <kb name> → file details`
5. Delete the curated `botpress-regional-services.txt` from Services KB (replaced by tagged Craft-synced content)

Test: in Botpress, attempt Search Knowledge with filter `{region: 'europe'}` — should return only Europe-tagged docs.

## Phase 2 — Studio restructure

Goal: replace the single AutonomousNode with router → 5 sub-workflows.

**Step 2a — Create 5 new workflows:**
- `Region_Global` (default)
- `Region_Europe`
- `Region_Pacific`
- `Region_Asia`
- `Region_MiddleEast`

Each workflow contains ONE AutonomousNode + its own Search Knowledge Cards (filtered by region tag).

**Step 2b — In Main workflow, replace AutonomousNode with a Standard Node router:**
- Standard1 Card: "Get User Data" (already exists)
- Execute Code card: read `user.data.region`, default to `"global"` if empty/null
- Transitions to sub-workflow by `region`:
  - `region === 'europe'` → `workflow.transition(Region_Europe)`
  - `region === 'pacific'` → `workflow.transition(Region_Pacific)`
  - `region === 'asia'` → `workflow.transition(Region_Asia)`
  - `region === 'middle_east' || region === 'middleEast'` → `workflow.transition(Region_MiddleEast)`
  - else → `workflow.transition(Region_Global)`

**Step 2c — Per-region AutonomousNode instructions:**
- Each ~1,500-char prompt only includes URLs/services/restrictions for that region
- Use **Region_Europe** template:
  ```
  You represent Jensen Hughes on jensenhughes.com Europe site.
  Use KB and MCP tools only. URL prefix: /europe/...
  Available services: [list ONLY Europe services from real slug list]
  Forensics: enumerate from KB; route via instructus.uk@; link /scotland.
  ```
- Region_Pacific, Region_Asia: omit Forensics entirely (not available).
- Region_MiddleEast: include Forensics with office routing (Dubai/Abu Dhabi/Doha/Riyadh).
- Region_Global: full portfolio.

**Step 2d — Search Knowledge Cards per workflow:**
- Region_Europe's Search Card: `Included KBs = Services, Industries, Offices, Default` filtered `tags.region = europe`
- Same pattern per region.

## Phase 3 — Webchat init cleanup

Goal: drop DOMContentLoaded race-condition hack from Twig.

Per Botpress docs (`webchat/interact/send-user-data.mdx`):
```js
window.botpress.on('webchat:initialized', () => {
  window.botpress.updateUser({ data: { region: '{{ region }}' } });
});
```

Replace current `if (window.botpress) ... else document.addEventListener('DOMContentLoaded', ...)` block in both `_meta_footer.twig` and `_partials/_meta_footer.twig`.

## Phase 4 — Verification

- Test each region in Studio emulator: 8 forensics × 5 regions + 4 Aldiana scenarios
- Test on staging webchat (without greeting): bot picks up region from `user.data.region`
- Validate URLs returned (`curl -L`): every emitted URL should be 200
- Compare bot response quality vs current (capability detail, /scotland link, correct contact email)

## Rollback

If migration breaks production:
1. Botpress Cloud "Versions" panel — restore `v6-pre-restructure`
2. Revert Twig template commits (`git revert` on Herd repo)
3. Re-upload `botpress-regional-services.txt` to Services KB as fallback
4. Re-publish

## Risks

| Risk | Mitigation |
|---|---|
| Studio canvas crashes mid-edit | Save frequently; commit instruction text to repo before each Studio session |
| Sub-workflow transitions don't fire | Test with explicit `region` set in Execute Code first; add log statements |
| KB tag filter syntax wrong | Use Botpress UI to test Search Knowledge Card filter before saving |
| Web sync (jensenhughes.com 100+ pages) bleeds region-mixed content into all sub-workflows | Move web sync into Global KB only; per-region KBs use curated Craft-synced content |

## Estimated effort

- Phase 1 (KB tagging via sync script): 3-4 hours coding + 1 hour Botpress setup
- Phase 2 (Studio restructure): 2-3 hours focused Studio time
- Phase 3 (Twig cleanup): 30 min
- Phase 4 (verification): 1-2 hours
- **Total:** ~8 hours focused work

## Open questions

- Does Botpress Plus plan allow 6 workflows in one bot? (Check workspace limits)
- Does `workflow.transition` from a Standard Node end the conversation cleanly? Verify before mass-implementing.
- Is there a way to share common rules (privacy, fallback contact) across sub-workflows without duplicating? (Maybe a parent prompt or shared Knowledge Card)

## References

- Research agent report (May 11, 2026 — saved as task transcript)
- Botpress Advanced Starter Template: https://botpress.com/docs/studio/guides/advanced/kitchen-sink-advanced-starter-template
- Botpress AutonomousNode + workflow.transition: https://botpress.com/docs/studio/concepts/nodes/autonomous-node
- Botpress Files API tag filtering: https://botpress.com/docs/api-reference/files-api/how-tos/manage-files
- ClickUp parent task: https://app.clickup.com/t/868hcqjz6
- Aldiana feedback ticket: https://app.clickup.com/t/868hv4tuy
