---
date: 2026-02-11T10:19:19-05:00
session_name: mcp-wrapper
researcher: Claude
git_commit: 8eaa252
branch: feature/mcp-improvements
repository: mcp-wrapper
topic: "Botpress KB Services Implementation"
tags: [botpress, knowledge-base, services, craft-cms, content-sync]
status: complete
last_updated: 2026-02-11
last_updated_by: Claude
type: implementation_strategy
root_span_id: ""
turn_span_id: ""
---

# Handoff: Botpress KB Services & Instructions Update

## Task(s)

| Task | Status |
|------|--------|
| Resume from previous handoff (botpress-kb-strategy) | ✅ Completed |
| Create services sync script | ✅ Completed |
| Create industries sync script | ✅ Completed |
| Manually upload Services KB content | ✅ Completed |
| Update bot instructions for token efficiency | ✅ Completed |
| Test bot with new KB and instructions | ✅ Completed |
| Fix shareable link 403 error | ✅ Completed |
| Create industries KB content | ⏳ Next step |

**Context:** Continued from handoff `2026-02-10_14-20-06_botpress-kb-strategy.md`. Services KB is now live and tested.

## Critical References

1. `docs/BOTPRESS-KB-STRATEGY.md` - Comprehensive strategy document (created previous session)
2. `data/botpress-services.txt` - Services content uploaded to Botpress KB (created this session)
3. Previous handoff: `thoughts/shared/handoffs/mcp-wrapper/2026-02-10_14-20-06_botpress-kb-strategy.md`

## Recent Changes

- `scripts/sync-services-to-botpress.js:1-298` - NEW: Services sync script following office pattern
- `scripts/sync-industries-to-botpress.js:1-280` - NEW: Industries sync script
- `data/botpress-services.txt:1-145` - NEW: Services KB content (manually uploaded to Botpress)
- `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md:95-99` - Updated state to reflect KB expansion work

## Learnings

### Manual Upload vs Sync Scripts
- User doesn't have direct Forge SSH access (auto-deploy via git)
- **Solution:** Created `data/botpress-services.txt` for manual upload to Botpress Studio
- Sync scripts will be used when Forge admin sets up scheduler

### Botpress KB Architecture
- Separate KBs for different content types: "Office Locations", "Services"
- Bot searches ALL KBs via `global.search` tool automatically
- KB search is faster and cheaper than MCP tools (~500ms vs 3-5s)

### Shareable Link 403 Fix
- **Root cause:** Stale conversation ID in browser's IndexedDB
- **Fix:** Clear site data (DevTools → Application → Storage → Clear site data)
- Or use incognito window for fresh state

### Token-Optimized Instructions
- Consolidated instructions from ~800 tokens to ~550 tokens
- Priority order: KB first → MCP fallback → Contact fallback
- Removed redundant sections and duplicate explanations

## Post-Mortem

### What Worked
- **Playwright browser testing:** Successfully scraped jensenhughes.com/services to create KB content
- **Pattern reuse:** sync-services-to-botpress.js copied directly from sync-offices pattern
- **KB-first architecture:** Bot correctly uses `global.search` before MCP tools (confirmed in logs)
- **Manual upload workflow:** Simple alternative when automated sync isn't available

### What Failed
- **Playwright session state:** Old conversation ID persisted across sessions causing 403 errors
- **MCP tools for Jensen Hughes:** Demo MCP tools connect to servicecurator, not staging3.jensenhughes.com
- **SSL certificate:** staging3.jensenhughes.com still has SSL issues from external access

### Key Decisions
- **Decision:** Manual KB upload for now, sync scripts for future automation
  - Alternatives: Wait for Forge scheduler setup, use GitHub Actions
  - Reason: User needed immediate solution, manual upload works reliably

- **Decision:** Separate KBs per content type (Offices, Services, Industries)
  - Alternatives: Single combined KB
  - Reason: Botpress searches all KBs automatically, separation allows different sync frequencies

## Artifacts

### Created This Session
- `scripts/sync-services-to-botpress.js` - Automated sync script (for Forge scheduler)
- `scripts/sync-industries-to-botpress.js` - Automated sync script (for Forge scheduler)
- `data/botpress-services.txt` - Services content for manual upload

### Updated
- `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md` - State updated

### Botpress (External)
- "Services" Knowledge Base - Created and populated with 6 service categories
- Agent Instructions - Updated with token-optimized version

## Action Items & Next Steps

1. **Create Industries KB** (next priority)
   - Scrape jensenhughes.com/industries
   - Create `data/botpress-industries.txt`
   - Upload to new "Industries" KB in Botpress

2. **Set up Forge scheduler** (when admin available)
   - Add `sync-services-to-botpress.js` - Monthly (0 2 1 * *)
   - Add `sync-industries-to-botpress.js` - Quarterly (0 2 1 1,4,7,10 *)

3. **Optional: Create FAQ KB**
   - Common questions about Jensen Hughes
   - Would further reduce MCP calls

4. **Monitor token usage**
   - Current: ~$0.06-0.07 per complex query
   - KB-first pattern should keep costs reasonable

## Other Notes

### Updated Bot Instructions (token-optimized)
```markdown
# Jensen Hughes AI Assistant

You represent Jensen Hughes on jensenhughes.com.

## Priority Order
1. **Knowledge Base** (fastest, cheapest) → Offices, Services, Industries
2. **MCP Tools** (fallback only) → Real-time data, specific lookups
3. **Never use general knowledge**

## Knowledge Base Content
| KB | Contains |
|----|----------|
| Office Locations | 96 offices worldwide - addresses, phones, contact links |
| Services | 6 service categories with capabilities and descriptions |
```
(Full instructions provided to user in conversation)

### Botpress Credentials (unchanged)
- Bot ID: `208ffbe5-a209-4a10-a52c-d79de4577f45`
- KB ID: `kb_01KH1H26AGTH92A51699JSQY1V`

### Test Results (all passing)
| Test | Result | Source |
|------|--------|--------|
| Fire engineering services | ✅ | KB |
| London office | ✅ | KB |
| Mumbai phone | ✅ | KB |
| Shareable link | ✅ | Fixed (clear IndexedDB) |
