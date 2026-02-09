# Botpress Knowledge Base Sync Setup

This guide explains how to set up automatic syncing of Jensen Hughes office locations from Craft CMS to the Botpress Knowledge Base.

## Overview

```
Craft CMS (97 offices) → Sync Script → Botpress Files API → Knowledge Base
```

**Two sync methods:**
1. **Scheduled** - GitHub Actions runs weekly (or on-demand)
2. **Real-time** - Webhook triggers sync when offices change in Craft

The sync script:
1. Fetches all office locations from the MCP endpoint
2. Gets detailed contact info (phone, address) for each office
3. Formats as searchable text document
4. Uploads to Botpress KB via Files API

---

## Step 1: Get Botpress Credentials

You need three things from Botpress:

### 1.1 Personal Access Token (PAT)

1. Log into [Botpress Cloud](https://app.botpress.cloud)
2. Click your avatar (bottom-left) → **Personal Access Tokens**
3. Click **Create Token**
4. Name it: `KB Sync Script`
5. Copy the token immediately (you won't see it again!)

### 1.2 Bot ID

1. Open your bot in Botpress Studio
2. Look at the URL: `https://app.botpress.cloud/workspaces/xxx/bots/BOT_ID_HERE/...`
3. Or go to **Bot Settings** → The ID is shown at the top

### 1.3 Knowledge Base ID

1. In Botpress Studio, go to **Knowledge Bases**
2. Click on your Knowledge Base
3. The ID is in the URL: `...knowledge-bases/KB_ID_HERE`
4. Or check the KB settings panel

---

## Step 2: Configure Environment

Create a `.env.botpress` file in the project root (or set these as environment variables):

```bash
# Botpress API Credentials
BOTPRESS_PAT=bp_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
BOTPRESS_BOT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
BOTPRESS_KB_ID=kb_xxxxxxxxxxxxxxxxxxxxxxxx

# MCP Endpoint (default shown, change if needed)
MCP_ENDPOINT=https://staging3.jensenhughes.com/mcp/ai
```

---

## Step 3: Test with Dry Run

First, test that the script can fetch office data:

```bash
# Dry run - fetches data but doesn't upload
DRY_RUN=true node scripts/sync-offices-to-botpress.js
```

This will show a preview of the formatted content.

### Saving Data for Offline Testing

If you want to test the formatting without MCP access:

```bash
# Fetch and save data locally (run from server with MCP access)
DRY_RUN=true SAVE_LOCAL_DATA=true node scripts/sync-offices-to-botpress.js

# Later, test with local data (no MCP needed)
DRY_RUN=true USE_LOCAL_DATA=true node scripts/sync-offices-to-botpress.js
```

The data is saved to `data/office-locations.json`.

---

## Step 4: Run the Sync

Once credentials are configured:

```bash
# Load env and run
source .env.botpress && node scripts/sync-offices-to-botpress.js
```

Or in one line:

```bash
BOTPRESS_PAT=xxx BOTPRESS_BOT_ID=xxx BOTPRESS_KB_ID=xxx node scripts/sync-offices-to-botpress.js
```

---

## Step 5: Set Up GitHub Actions (Scheduled Sync)

The workflow file is already created at `.github/workflows/sync-kb.yml`.

### 5.1 Add Secrets to GitHub

1. Go to your repo on GitHub
2. **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret** for each:

| Secret Name | Value |
|-------------|-------|
| `BOTPRESS_PAT` | Your Personal Access Token |
| `BOTPRESS_BOT_ID` | Your Bot ID |
| `BOTPRESS_KB_ID` | Your Knowledge Base ID |
| `MCP_ENDPOINT` | `https://staging3.jensenhughes.com/mcp/ai` |

### 5.2 Test with GitHub CLI

```bash
# Trigger manually (dry run)
gh workflow run sync-kb.yml -f dry_run=true

# Watch the run
gh run watch

# Trigger for real
gh workflow run sync-kb.yml
```

### 5.3 Schedule

The workflow runs automatically every Sunday at 2am UTC. You can change this in `.github/workflows/sync-kb.yml`.

---

## Step 6: Set Up Webhook (Real-time Sync)

When an office is created/updated in Craft, trigger an immediate sync.

### 6.1 Add More GitHub Secrets

| Secret Name | Value |
|-------------|-------|
| `GITHUB_TOKEN` | A GitHub PAT with `repo` and `workflow` scopes |
| `GITHUB_REPO` | `your-org/mcp-wrapper` |
| `WEBHOOK_SECRET` | A random string for security |

### 6.2 Configure Environment on Server

Add to your server's environment (`.env` or Forge env vars):

```bash
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
GITHUB_REPO=your-org/mcp-wrapper
WEBHOOK_SECRET=your-random-secret-string
```

### 6.3 Webhook Endpoint

The webhook endpoint is:
```
POST https://staging3.jensenhughes.com/mcp/webhook/kb-sync
Header: X-Webhook-Secret: your-random-secret-string
```

### 6.4 Trigger from Craft

You can call the webhook from:

**A) Craft Event (in a module):**
```php
use craft\elements\Entry;
use craft\events\ModelEvent;
use yii\base\Event;

Event::on(Entry::class, Entry::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $entry = $event->sender;
    if ($entry->section->handle === 'officeLocations') {
        // Trigger KB sync
        $client = new \GuzzleHttp\Client();
        $client->post('https://staging3.jensenhughes.com/mcp/webhook/kb-sync', [
            'headers' => ['X-Webhook-Secret' => getenv('WEBHOOK_SECRET')],
        ]);
    }
});
```

**B) Craft Webhooks Plugin:**
If using a webhooks plugin, point it to the endpoint above.

**C) Manual curl:**
```bash
curl -X POST https://staging3.jensenhughes.com/mcp/webhook/kb-sync \
  -H "X-Webhook-Secret: your-secret"
```

---

## Verifying the Sync

After running, check in Botpress:

1. Go to **Knowledge Bases** → Your KB
2. Look for `kb-sync/office-locations.txt` in Sources
3. Test by asking the bot: "What is the phone number for the Roseville office?"

---

## Troubleshooting

### "Missing required environment variables"

Make sure all three env vars are set:
- `BOTPRESS_PAT`
- `BOTPRESS_BOT_ID`
- `BOTPRESS_KB_ID`

### "Failed to fetch office list"

- Check that the MCP endpoint is accessible
- Verify the endpoint URL is correct
- Test with: `curl -X POST https://staging3.jensenhughes.com/mcp/ai -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'`

### "Upload failed: 401"

- PAT may be expired or invalid
- Regenerate a new token in Botpress

### "Upload failed: 404"

- Bot ID or KB ID may be incorrect
- Double-check the IDs from Botpress Studio

---

## What Gets Synced

The script creates a formatted document with:

- All 97 office locations grouped by country
- For each office:
  - Full address
  - Phone number
  - Contact form URL
  - Office details page URL
- General contact info (info@jensenhughes.com)

Example output:

```
# Jensen Hughes Office Locations

Last updated: 2026-02-09
Total offices: 97

---

## United States

### Roseville
Address: 2175 N California Blvd, Suite 615, Walnut Creek, CA 94596
Phone: +1 925 938 3550
Contact Form: https://www.jensenhughes.com/contact/office-locations/form/roseville
Details: https://www.jensenhughes.com/contact/office-locations/roseville

### Oakland
Address: 1999 Harrison Street, Suite 700, Oakland, CA 94612
Phone: +1 510 893 2400
...

## United Kingdom

### London
...
```

---

## Updating the Script

The script is in `scripts/sync-offices-to-botpress.js`. To modify:

- **Change output format**: Edit `formatOfficesForKB()` function
- **Add more fields**: Modify the office detail extraction
- **Change file key**: Update `fileKey` in `uploadToBotpress()`
