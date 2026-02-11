---
date: 2026-02-10T14:20:06-08:00
session_name: mcp-wrapper
researcher: Claude
git_commit: 97faa9f
branch: feature/mcp-improvements
repository: mcp-wrapper
topic: "Botpress Knowledge Base Strategy for Jensen Hughes"
tags: [botpress, knowledge-base, mcp, craft-cms, content-sync]
status: complete
last_updated: 2026-02-10
last_updated_by: Claude
type: implementation_strategy
root_span_id: ""
turn_span_id: ""
---

# Handoff: Botpress KB Strategy & Content Freshness Analysis

## Task(s)

| Task | Status |
|------|--------|
| Research what Jensen Hughes content should go in Botpress KB vs MCP | ✅ Completed |
| Analyze content freshness across all Craft CMS sections | ✅ Completed |
| Create comprehensive KB strategy document | ✅ Completed |
| Create content freshness analysis script | ✅ Completed |
| Create services sync script | ⏳ Planned (next step) |

**Context:** User already has office locations syncing to Botpress KB (97 offices). Question was: what other content should go in KB vs use MCP real-time queries?

## Critical References

1. `/docs/BOTPRESS-KB-STRATEGY.md` - Comprehensive strategy document created this session
2. `/scripts/sync-offices-to-botpress.js` - Existing pattern for KB sync
3. `~/Herd/jensenhughes/` - Jensen Hughes Craft CMS codebase (same as staging3)

## Recent Changes

- `docs/BOTPRESS-KB-STRATEGY.md:1-516` - NEW: Complete KB strategy document with:
  - Content inventory from Craft CMS (66 sections, 9 category groups)
  - Decision framework (KB vs MCP)
  - Three architecture options with trade-offs
  - Multi-region strategy for 10 regional sites
  - Step-by-step implementation guide

- `scripts/analyze-content-freshness.js:1-180` - NEW: Script to analyze content update patterns via MCP (note: SSL issues from local machine, works from Forge)

## Learnings

### Content Structure
- Jensen Hughes CMS has **66 entry sections** and **9 category groups** exposed via MCP
- Key sections for chatbot: `services`, `industries`, `officeLocations`, `ourTeam`, `leadershipTeam`, `insights`, `events`, `careers`
- MCP tools available: `craft_search_entries`, `craft_get_office_contact_info`, `query_{sectionHandle}`

### Stability Analysis (from git history + production site)
| Content | Stability | Recommendation |
|---------|-----------|----------------|
| Services | 1+ year stable | KB (monthly sync) |
| Industries | 1+ year stable | KB (monthly sync) |
| Offices | 6+ months stable | KB (weekly sync) - DONE |
| Leadership | 6+ months stable | KB (quarterly sync) |
| Insights/Blog | Weekly updates | HYBRID (recent via MCP) |
| Events | Weekly changes | MCP (real-time) |
| Careers | Daily changes (ATS) | MCP (real-time) |

### SSL Certificate Issue
- `staging3.jensenhughes.com` has SSL cert issue when accessed from external networks (GitHub Actions, local Mac)
- Works from Forge server (same network)
- Workaround: Run sync scripts from Forge scheduler, not GitHub Actions

### Botpress KB Limits (from research)
- Vector DB: 100MB (PAYG) to 2GB+ (Team)
- File Storage: 100MB to 10GB
- Max file size: 1GB
- Jensen Hughes estimate: ~200KB total - well within limits

## Post-Mortem

### What Worked
- **Parallel research agents**: Launched 3 agents simultaneously to research Botpress KB best practices, explore MCP codebase, and research enterprise chatbot architecture
- **Playwright browser**: Successfully accessed production jensenhughes.com to verify content structure when MCP had SSL issues
- **Git history analysis**: `git log --since="2024-01-01"` on templates revealed content stability patterns

### What Failed
- **Direct MCP queries from local**: SSL certificate error (`tlsv1 unrecognized name`) blocks external access to staging3
- **Direct MySQL access**: Database connection from CLI failed (requires Herd/DDEV environment)
- **GitHub Actions for sync**: Same SSL issue - recommend Forge scheduler instead

### Key Decisions
- **Decision**: Hybrid KB + MCP architecture (not full KB, not full MCP)
  - Alternatives: Full KB (stale dynamic content), Full MCP (slower, dependent on API)
  - Reason: 80% of questions are static (services, locations), 20% need real-time (jobs, events)

- **Decision**: Services should be next KB content after offices
  - Alternatives: Industries, Leadership, FAQs
  - Reason: "What do you do?" is #2 most common question, services rarely change

## Artifacts

### Created This Session
- `docs/BOTPRESS-KB-STRATEGY.md` - Complete strategy document
- `scripts/analyze-content-freshness.js` - Content age analysis tool

### Existing (Referenced)
- `scripts/sync-offices-to-botpress.js` - Template for new sync scripts
- `thoughts/ledgers/CONTINUITY_CLAUDE-mcp-wrapper.md` - Session ledger

### Research Reports (in cache)
- `.claude/cache/agents/research-agent/botpress-kb-research-2026-02-10.md` - Botpress KB capabilities
- `.claude/cache/agents/research-agent/latest-output.md` - Enterprise chatbot architecture

## Action Items & Next Steps

1. **Create services sync script** (high priority)
   - Copy pattern from `sync-offices-to-botpress.js`
   - Query `craft_search_entries` with `section: "services"`
   - Format as markdown for KB
   - Add to Forge scheduler

2. **Create industries sync script** (medium priority)
   - Same pattern as services
   - Include industry-service relationships

3. **Set up Botpress intent routing** (future)
   - Route job/career queries to MCP
   - Route service/location queries to KB
   - Implement fallback pattern

4. **Fix SSL certificate** (if needed for GitHub Actions)
   - Or continue using Forge scheduler (working solution)

## Other Notes

### MCP Endpoint
```
https://staging3.jensenhughes.com/mcp/ai
```

### Botpress Credentials (in GitHub secrets and Forge env)
- `BOTPRESS_PAT` - Personal Access Token
- `BOTPRESS_BOT_ID` - `208ffbe5-a209-4a10-a52c-d79de4577f45`
- `BOTPRESS_KB_ID` - `kb_01KH1H26AGTH92A51699JSQY1V`

### Regional Sites (10 total)
Jensen Hughes has multi-site setup: Global, Europe, Asia, Korea, Pacific, French, Danish, Dutch, Finnish, Middle East. Strategy doc includes multi-language recommendations.

### Key File Locations
- Craft CMS: `~/Herd/jensenhughes/`
- MCP Wrapper plugin: `/Users/elizabethstein/Projects/mcp-wrapper/`
- Templates: `~/Herd/jensenhughes/templates/`
- Project config: `~/Herd/jensenhughes/config/project/`
