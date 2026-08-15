# Jensen Hughes Botpress Knowledge Base Strategy

**Comprehensive Analysis & Implementation Guide**
**Generated:** 2026-02-10

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Available Content in Jensen Hughes CMS](#available-content)
3. [KB vs MCP Decision Framework](#decision-framework)
4. [Recommended Architecture](#architecture)
5. [Content Priority & Implementation](#implementation)
6. [Multi-Region Strategy](#multi-region)
7. [Sync Automation Options](#automation)
8. [Step-by-Step Implementation Guide](#step-by-step)

---

## Executive Summary

### The Core Question
> Should Jensen Hughes content go in the Botpress Knowledge Base, or should the chatbot query the MCP (Craft CMS) in real-time?

### The Answer: Hybrid Architecture

| Content Type | Approach | Why |
|-------------|----------|-----|
| **Office Locations** | KB (already done) | Static, critical for contact queries |
| **Services** | KB | Core business info, rarely changes |
| **Industries** | KB | Static expertise descriptions |
| **Leadership/Team** | KB | Low change frequency |
| **FAQs** | KB | Pre-indexed = fast retrieval |
| **Events** | MCP (real-time) | Time-sensitive, changes weekly |
| **Jobs/Careers** | MCP (real-time) | Daily changes, accuracy critical |
| **News/Insights** | Hybrid | Recent via MCP, archive in KB |

---

## Available Content in Jensen Hughes CMS {#available-content}

### MCP Tools Already Available

The MCP wrapper exposes **66 entry sections** and **9 category groups**. Key ones for Botpress:

#### High-Value Content (KB Candidates)

| Section | Tool Name | Content Count | Use Case |
|---------|-----------|---------------|----------|
| `officeLocations` | `query_officeLocations` | 97 offices | "Where is your nearest office?" |
| `services` | `query_services` | All services | "What do you offer?" |
| `industries` | `query_industries` | Industry pages | "Do you work with healthcare?" |
| `ourTeam` | `query_ourTeam` | Team members | "Who leads Asia operations?" |
| `leadershipTeam` | `query_leadershipTeam` | Executives | "Who is your CEO?" |
| `insights` | `query_insights` | Articles/blogs | "What's your expertise in X?" |
| `certifiedCompanies` | `query_certifiedCompanies` | Partners | Partner directory |

#### Dynamic Content (MCP Real-Time)

| Section | Tool Name | Why Real-Time |
|---------|-----------|---------------|
| `events` | `query_events` | Dates/registration change |
| `careers` | `query_careers` | Jobs open/close daily |
| `webinars` | `query_webinars` | Time-sensitive registration |
| `podcasts` | `query_podcasts` | New episodes weekly |

### Custom Tools Available

```
craft_get_office_contact_info  - Complete office details with phone, address
craft_search_entries           - Flexible search across all sections
craft_get_entry_by_slug        - Direct entry lookup
```

---

## KB vs MCP Decision Framework {#decision-framework}

### When to Use Knowledge Base

```
+------------------+     +------------------+     +------------------+
| Content changes  | --> | Changes < 1x/wk  | --> | PUT IN KB        |
| frequently?      |     |                  |     |                  |
+------------------+     +------------------+     +------------------+
                                 |
                                 v No
                         +------------------+
                         | Use MCP/API      |
                         +------------------+
```

### Performance Comparison

| Factor | Knowledge Base | MCP Real-Time |
|--------|----------------|---------------|
| **Latency** | ~200-500ms (pre-indexed) | ~1-3s (API + processing) |
| **Availability** | 99.9% (Botpress SLA) | Depends on staging3 uptime |
| **Accuracy** | Stale by sync interval | Always current |
| **Cost** | Included in Botpress plan | API calls + compute |
| **Setup** | One-time sync script | Integration code |

### The 80/20 Rule

**80% of user questions** can be answered from static KB:
- "Where are you located?"
- "What services do you offer?"
- "Do you have experience with X industry?"
- "How do I contact you?"

**20% require real-time data:**
- "Are you hiring in New York?"
- "What events are coming up?"
- "What's your latest news?"

---

## Recommended Architecture {#architecture}

### Option 1: Full KB Approach (Simplest)

```
User Query --> Botpress KB --> LLM Response
                   |
                   v
        [Weekly sync from Craft CMS]
```

**Pros:**
- Simplest to implement
- Fastest responses
- No dependency on MCP availability

**Cons:**
- Stale data for dynamic content
- Poor UX for jobs/events queries

**Best for:** MVP or low-traffic bots

---

### Option 2: Hybrid KB + MCP (Recommended)

```
User Query --> Intent Classification
                   |
       +-----------+-----------+
       |                       |
       v                       v
  Static Query            Dynamic Query
  (services,              (jobs, events)
   locations)                  |
       |                       v
       v                  MCP API Call
  Botpress KB                  |
       |                       v
       +----------+------------+
                  |
                  v
            LLM Response
```

**Pros:**
- Best of both worlds
- Fast for common queries
- Accurate for dynamic content

**Cons:**
- More complex setup
- Need intent classification

**Best for:** Production deployment

---

### Option 3: MCP-First with KB Fallback

```
User Query --> MCP API Call
                   |
          +--------+--------+
          |                 |
       Success           Failure/Timeout
          |                 |
          v                 v
    LLM Response      Botpress KB Fallback
                           |
                           v
                    LLM Response
                    (with staleness note)
```

**Pros:**
- Always current data
- Graceful degradation

**Cons:**
- Slower responses
- More API costs
- Dependent on MCP availability

**Best for:** When data freshness is critical

---

## Content Priority & Implementation {#implementation}

### Phase 1: Core Static Content (Week 1-2)

| Content | Priority | Est. Size | Sync Frequency |
|---------|----------|-----------|----------------|
| Services Catalog | P0 | ~50KB | Monthly |
| Office Locations | P0 | ~30KB | Done |
| Industry Expertise | P1 | ~30KB | Quarterly |
| FAQs | P1 | ~20KB | Monthly |
| **Total Phase 1** | | ~130KB | |

### Phase 2: Team & Expertise (Week 3-4)

| Content | Priority | Est. Size | Sync Frequency |
|---------|----------|-----------|----------------|
| Leadership Team | P2 | ~15KB | Quarterly |
| Expert Profiles | P2 | ~40KB | Quarterly |
| Certifications | P2 | ~10KB | Annual |
| **Total Phase 2** | | ~65KB | |

### Phase 3: MCP Integration (Week 5-6)

| Content | Integration Type | Update Frequency |
|---------|-----------------|------------------|
| Jobs/Careers | Real-time MCP | On query |
| Events | Real-time MCP | On query |
| Recent Insights | Real-time MCP | On query |

---

## Multi-Region Strategy {#multi-region}

### Jensen Hughes Regional Sites

| Region | Site Handle | Language | KB Approach |
|--------|------------|----------|-------------|
| Global (US) | `jensenhughes` | English | Primary KB |
| Europe | `jensenHughesEurope` | English | Filter by region |
| Asia | `jensenHughesAsia` | English + Local | Separate embeddings |
| Korea | `jensenHughesKorea` | Korean | Separate KB |
| Pacific | `jensenHughesPacific` | English | Filter by region |
| French | `jensenHughesFrench` | French | Separate embeddings |
| Danish | `jensenHughesDanish` | Danish | Separate embeddings |
| Dutch | `jensenHughesDutch` | Dutch | Separate embeddings |
| Finnish | `jensenHughesFinnish` | Finnish | Separate embeddings |

### Recommended Multi-Language Approach

**For European Languages (French, Danish, Dutch, Finnish):**
- Use Botpress multilingual embeddings
- Single KB with language metadata
- Filter by detected language

**For Asian Languages (Korean):**
- Separate vector embeddings required
- Different script = different semantic space
- Create dedicated Korean KB

### KB Structure by Region

```markdown
# Global Services KB
- All services in English
- Metadata: region=global

# Regional Office KB
- Office locations filtered by region
- Contact info in local language
- Metadata: region=europe|asia|pacific

# Korean Services KB (Separate)
- Services translated to Korean
- Korean-specific embeddings
- Metadata: language=ko
```

---

## Sync Automation Options {#automation}

### Option A: Forge Scheduler (Current Plan)

```bash
# Add to Forge Scheduler (weekly)
BOTPRESS_PAT=xxx BOTPRESS_BOT_ID=xxx BOTPRESS_KB_ID=xxx \
node /path/to/sync-offices-to-botpress.js
```

**Pros:** Already configured, runs from server with CMS access
**Cons:** Manual cron management

### Option B: GitHub Actions + Data Export

```yaml
# .github/workflows/sync-kb.yml
name: Sync KB to Botpress
on:
  schedule:
    - cron: '0 2 * * 0'  # Weekly
  workflow_dispatch:      # Manual trigger

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Fetch from data export
        run: |
          # Use pre-exported JSON instead of live MCP
          curl -o data/services.json https://staging3.jensenhughes.com/api/services.json
      - name: Sync to Botpress
        run: node scripts/sync-services-to-botpress.js
        env:
          BOTPRESS_PAT: ${{ secrets.BOTPRESS_PAT }}
```

**Pros:** Version controlled, auditable
**Cons:** Requires data export endpoint

### Option C: Webhook-Triggered Sync

```
Craft CMS Entry Saved --> Webhook --> Serverless Function --> Botpress API
```

**Pros:** Real-time updates
**Cons:** Complex setup, may hit rate limits

### Recommendation

**Use Forge Scheduler for now** (already working for offices).
**Migrate to GitHub Actions** once you have a data export endpoint.

---

## Step-by-Step Implementation Guide {#step-by-step}

### Step 1: Create Services Sync Script

```bash
# scripts/sync-services-to-botpress.js
# Copy pattern from sync-offices-to-botpress.js
# Change section from officeLocations to services
```

**MCP Query to use:**
```json
{
  "method": "tools/call",
  "params": {
    "name": "query_services",
    "arguments": { "limit": 100 }
  }
}
```

### Step 2: Format Services for KB

```markdown
# Jensen Hughes Services

## Fire Protection Engineering
**Category:** Fire & Life Safety
**Description:** Comprehensive fire protection engineering...
**Industries Served:** Healthcare, Data Centers, Manufacturing
**Key Capabilities:**
- Fire alarm system design
- Sprinkler system analysis
- Smoke control systems
**Contact:** [Link to contact form]

## Security Risk Consulting
**Category:** Security
**Description:** ...
```

### Step 3: Add Forge Scheduler Job

```
Command: BOTPRESS_PAT=xxx ... node scripts/sync-services-to-botpress.js
User: forge
Frequency: 0 2 * * 0 (Sundays 2am)
```

### Step 4: Configure Botpress Intent Routing

In Botpress Studio:
```
IF intent = "job_inquiry" OR intent = "career_question"
  → Execute MCP query for careers
ELSE IF intent = "event_inquiry"
  → Execute MCP query for events
ELSE
  → Search Knowledge Base
```

### Step 5: Create Fallback Handler

```javascript
// In Botpress action
async function queryWithFallback(intent, query) {
  if (isDynamicIntent(intent)) {
    try {
      return await mcpQuery(intent, query);
    } catch (error) {
      // Fallback to KB with staleness notice
      return await kbQuery(query) + "\n\n_Note: For the most current information, please visit our website._";
    }
  }
  return await kbQuery(query);
}
```

---

## Botpress KB Best Practices

### Storage Limits

| Plan | Vector DB | File Storage |
|------|-----------|--------------|
| Pay-as-you-go | 100 MB | 100 MB |
| Plus | 1 GB | 10 GB |
| Team | 2 GB | 10 GB |

Jensen Hughes estimate: ~200KB total = well within limits

### Content Formatting

```markdown
# DO: Clear hierarchical structure
## Service: Fire Protection Engineering
### Overview
[Content that can stand alone as a chunk]

### Capabilities
[Each section self-contained]

# DON'T: Nested cross-references
See section 3.2 for details...  ← Bad: breaks chunking
```

### Optimal Chunk Size
- 200-500 words per logical section
- Each chunk should be a complete answer
- Include parent context in child sections

---

## Summary: What to Do Now

### Immediate (This Week)
1. Keep office locations sync as-is
2. Create `sync-services-to-botpress.js` using same pattern

### Short-Term (Next 2 Weeks)
3. Add services to KB
4. Add industries to KB
5. Add FAQs to KB

### Medium-Term (Next Month)
6. Add leadership/team to KB
7. Set up MCP integration for jobs/events in Botpress
8. Implement intent routing

### Long-Term (Quarterly)
9. Add multi-language support for Korean site
10. Implement caching layer for MCP queries
11. Analytics-driven content refinement

---

## Files Created

| File | Purpose |
|------|---------|
| `scripts/sync-offices-to-botpress.js` | Office sync (exists) |
| `scripts/sync-services-to-botpress.js` | Services sync (to create) |
| `scripts/sync-industries-to-botpress.js` | Industries sync (to create) |
| `docs/BOTPRESS-KB-STRATEGY.md` | This document |

---

## Research Sources

- Botpress Documentation: https://botpress.com/docs
- Botpress Academy: https://botpress.com/academy
- Botpress Files API: https://botpress.com/docs/api-reference/files-api
- RAG Best Practices: Pinecone, AWS Bedrock documentation
- Enterprise Chatbot Architecture: Rasa, AI Multiple research
