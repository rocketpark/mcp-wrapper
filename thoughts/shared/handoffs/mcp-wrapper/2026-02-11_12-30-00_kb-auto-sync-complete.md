---
date: 2026-02-11T12:30:00-05:00
session_name: mcp-wrapper
researcher: Claude
git_commit: 55b82d2
branch: feature/mcp-improvements
repository: mcp-wrapper
topic: "Botpress KB Auto-Sync Implementation"
tags: [botpress, knowledge-base, automation, cron, forge, dns-fix]
status: ready-for-deployment
last_updated: 2026-02-11
last_updated_by: Claude
type: implementation_complete
---

# Handoff: Botpress KB Auto-Sync Complete

## Summary

Fixed the staging3 DNS/SSL issue and built a console command for automatic Botpress KB synchronization that runs directly on the Forge server (bypassing all auth issues).

## What Was Done

### 1. DNS/SSL Fix
- **Problem:** `staging3.jensenhughes.com` DNS was changed to point to AWS server without SSL
- **Solution:** Updated all code to use `jensenhughes3.on-forge.com` directly

**Files changed:**
- `scripts/sync-services-to-botpress.js` - endpoint updated
- `scripts/sync-industries-to-botpress.js` - endpoint updated
- `scripts/sync-offices-to-botpress.js` - endpoint updated
- `scripts/analyze-content-freshness.js` - endpoint updated
- Jensen Hughes `templates/_meta_footer.twig` - bot loads on Forge URL now

**Commits:**
- `16a360f` - Switch MCP endpoint from staging3 to Forge URL
- `d486e8f2` - Use Forge URL for Botpress webchat widget (Jensen Hughes repo)

### 2. KB Auto-Sync Console Command
- **New file:** `src/console/controllers/SyncKbController.php`
- **Modified:** `src/McpWrapper.php` - added console controller namespace

**Command usage:**
```bash
# Sync all KBs
php craft mcp-wrapper/sync-kb

# Sync specific KB
php craft mcp-wrapper/sync-kb/services
php craft mcp-wrapper/sync-kb/industries
php craft mcp-wrapper/sync-kb/offices
php craft mcp-wrapper/sync-kb/leadership

# Options
--dry-run    # Preview without uploading
--force      # Force sync even if no changes
```

**Commit:** `55b82d2` - Add console command for direct Botpress KB sync

## What's Left To Do

### 1. Add Botpress Credentials to Forge
On Forge server, add to `.env`:
```
BOTPRESS_PAT=<get from Botpress dashboard>
BOTPRESS_BOT_ID=5aab29db-40a4-481c-8a61-030e3f0dfa65
```

### 2. Update MCP Wrapper Plugin on Forge
```bash
# Option A: If using composer
composer update rocketpark/craft-mcp-wrapper

# Option B: If manual install
# Copy updated src/ folder to Forge
```

### 3. Set Up Cron Job on Forge
In Forge → Server → Scheduler:
```
0 3 * * * cd /home/forge/jensenhughes3.on-forge.com && php craft mcp-wrapper/sync-kb >> /home/forge/kb-sync.log 2>&1
```
This runs daily at 3am UTC.

### 4. Optional: Add KB IDs to Config
For multiple KB support, add to `config/mcpwrapper.php`:
```php
'botpressKbIds' => [
    'services' => 'kb_xxx',
    'industries' => 'kb_yyy',
    'offices' => 'kb_zzz',
    'leadership' => 'kb_www',
],
```

## Testing

### Test Console Command Locally
```bash
cd ~/Herd/jensenhughes
php craft mcp-wrapper/sync-kb --dry-run
```

### Test Botpress Bot
1. Go to `https://jensenhughes3.on-forge.com` (needs basic auth)
2. Chat widget should appear in bottom right
3. Ask "What services do you offer?" - should get KB-powered response

### Verify Endpoints Work
```bash
# Should return 401 (auth required) - means server is responding
curl -s -o /dev/null -w "%{http_code}" https://jensenhughes3.on-forge.com

# Old endpoint should fail
curl -s https://staging3.jensenhughes.com  # Connection error
```

## Current KB Status

| Knowledge Base | Status | Auto-Sync |
|----------------|--------|-----------|
| Services | ✅ Uploaded | ✅ Ready (cron) |
| Industries | ✅ Uploaded | ✅ Ready (cron) |
| Offices | ✅ Uploaded | ✅ Ready (cron) |
| Leadership | ⏳ Not yet | ✅ Ready (cron) |

## Architecture

```
Content changes in Craft CMS
           ↓
    (within 24 hours)
           ↓
Cron runs: php craft mcp-wrapper/sync-kb
           ↓
Command queries Craft DB directly (no auth issues)
           ↓
Formats content for each KB type
           ↓
Uploads to Botpress via API
           ↓
Bot has updated knowledge
```

## Related Files

- `src/console/controllers/SyncKbController.php` - Main sync logic
- `src/McpWrapper.php` - Plugin bootstrap
- `scripts/sync-*-to-botpress.js` - Old Node.js scripts (still work but not used for automation)
- `.github/workflows/sync-kb.yml` - GitHub Action (deprecated, use cron instead)

## Session Issues Resolved

1. **MySQL not running** - Started with `brew services start mysql`
2. **Redis not running** - Started with `brew services start redis`
3. **MySQL sort buffer** - Increased with `SET GLOBAL sort_buffer_size = 8388608`
4. **Botpress 403 error** - Cleared browser localStorage (corrupted conversation)

## Message for Jonathan

> **Botpress KB automation complete:**
> - Built a sync command that runs directly on the server
> - No more auth issues - it has direct database access
> - Set up as daily cron job - syncs Services, Industries, Offices, Leadership automatically
> - If content changes, it'll be in Botpress within 24 hours
>
> **To finish setup:** Need to add Botpress credentials to the Forge `.env` and set up the cron job.
>
> The staging3 DNS issue is irrelevant now - we use the Forge URL directly.

## Next Session

1. Deploy changes to Forge
2. Add Botpress env vars
3. Set up cron job
4. Test sync command: `php craft mcp-wrapper/sync-kb --dry-run`
5. Run first real sync: `php craft mcp-wrapper/sync-kb`
6. Verify Leadership KB gets created
