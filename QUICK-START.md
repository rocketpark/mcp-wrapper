# MCP Wrapper - Quick Start Guide

> 💡 **TL;DR for Managers:** This plugin lets AI assistants (like Claude, Airia, or any AI agent platform) directly query and interact with your Craft CMS content. Think of it like giving AI a read-only API to your website's content.

---

## 🚀 Try It Now (Live Demo)

**Want to experience it without installing anything?**

If you have access to an AI agent platform like **Airia**, you can connect to a test instance:

1. **For Airia/HTTP-based AI platforms**, use this endpoint:
   ```
   https://servicecurator.com/mcp/public
   ```

2. **For Claude Desktop** (local app), add this to your config:
   ```json
   {
     "mcpServers": {
       "craft-demo": {
         "command": "npx",
         "args": ["-y", "mcp-remote", "https://servicecurator.com/mcp/public"]
       }
     }
   }
   ```

3. **Test it by asking the AI:**
   - "What content is available on this site?"
   - "Show me the latest news entries"
   - "Find blog posts about [topic]"

---

## 🛠️ Testing Locally (Without Touching Live Sites)

### Prerequisites
- Local Craft CMS site running (DDEV, Laravel Valet, or any local dev environment)
- Craft CMS 5.x installed
- Local site accessible via URL (e.g., `http://mysite.test`)

### Installation Steps

#### 1. Install the Plugin

SSH/terminal into your local Craft site:

```bash
cd /path/to/your-local-craft-site
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

#### 2. Configure GraphQL Token

**In Craft Control Panel:**
1. Go to **GraphQL** → **Schemas**
2. Create or select a schema (e.g., "Public API")
3. Give it read access to the sections you want to test with
4. Copy the **Access Token**

#### 3. Create Config File

Create `config/mcpwrapper.php`:

```php
<?php
return [
    'schemas' => [
        'public' => getenv('GQL_PUBLIC_TOKEN'),
    ],
];
```

#### 4. Add Token to Environment

Add to `.env`:

```bash
GQL_PUBLIC_TOKEN="paste-your-token-here"
```

#### 5. Enable GraphQL

In `config/general.php`, add:

```php
return \craft\config\GeneralConfig::create()
    ->enableGql()
    // ... your other settings
;
```

In `config/routes.php`, add:

```php
return [
    'api' => 'graphql/api',
];
```

#### 6. Test It Works

Open terminal and run:

```bash
curl -X POST 'http://mysite.test/mcp/public' \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
  }'
```

**Expected Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "query_news",
        "description": "Query News entries from Craft CMS",
        ...
      }
    ]
  }
}
```

If you see a list of tools (one per Craft section), it's working! 🎉

---

## 🔌 Connecting AI Platforms

### Option 1: Airia (or Other HTTP-Based AI Platforms)

**If you want to connect Airia to a test site:**
1. Use the live demo: `https://servicecurator.com/mcp/public`
2. In Airia, configure the MCP endpoint to the URL above
3. Start asking questions about the content

**If you want to connect Airia to YOUR OWN local development site:**

Your local MCP endpoint is:
```
http://mysite.test/mcp/public
```

Since Airia is cloud-based and can't reach your local machine, you need a tunnel:

1. **Use a tunnel service** (choose one):
   - **ngrok**: `ngrok http 80` (simplest - download from ngrok.com)
   - **Cloudflare Tunnel**: `cloudflared tunnel --url http://localhost:80`
   - **LocalTunnel**: `lt --port 80`

2. **Get the public URL** (e.g., `https://abc123.ngrok.io`)

3. **In Airia**, configure the MCP endpoint:
   ```
   https://abc123.ngrok.io/mcp/public
   ```

### Option 2: Claude Desktop (Local Mac/Windows App)

Edit `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "my-craft-site": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "http://mysite.test/mcp/public"
      ]
    }
  }
}
```

Restart Claude Desktop. You'll see your Craft sections appear as available tools.

---

## What You Can Do

Once connected, the AI assistant can:
- **Discover your content**: "What content is available?"
- **Query entries**: "Show me the latest 5 news articles"
- **Search content**: "Find products related to 'coffee'"
- **Get specific entries**: "Get the entry with ID 123"

The plugin automatically exposes each Craft section as a queryable tool.

---

## Local Development Workflow

### Making Changes
1. Edit plugin files in `/path/to/craft-site/vendor/rocket-park/mcp-wrapper/`
2. Clear Craft caches: `php craft clear-caches/all`
3. Test with curl or your AI client

### Viewing Logs
```bash
tail -f storage/logs/web.log
```

### Testing Different Schemas
Create multiple schemas in the config:

```php
'schemas' => [
    'public' => getenv('GQL_PUBLIC_TOKEN'),
    'internal' => getenv('GQL_INTERNAL_TOKEN'),
    'ai' => getenv('GQL_AI_TOKEN'),
]
```

Each schema can have different permissions, giving you control over what content AI assistants can access.

---

## Troubleshooting

### "Action not found" error
- Make sure plugin is installed: `php craft plugin/list`
- Check routes are enabled in `config/routes.php`

### "Unauthorized" or empty tools list
- Verify GraphQL token is correct in `.env`
- Check schema has read permissions in Craft CP → GraphQL

###🎯 For Your Boss: How to Try This

**Easiest way to experience it:**

1. **In your Airia platform**, add a new MCP connection:
   ```
   https://servicecurator.com/actions/mcp-wrapper/mcp/index?schemaHandle=ai
   ```
   
   *(This is the live test site - no setup needed!)*

3. **Start chatting** with your AI agent:
   - "What sections are available?"
   - "Show me recent entries"
   - "Find content about [topic]"
   
   The AI will automatically discover and query your Craft CMS content.

**Why this is cool:**
- AI assistants can now intelligently search and retrieve content from Craft
- No need to manually copy/paste content - the AI fetches it directly
- You control what content is accessible via GraphQL permissions
- Works with any AI platform that supports MCP (Claude, Airia, custom agents, etc.)

---

## 🚀  AI client can't connect
- For local testing with cloud AI: Use ngrok or similar tunnel
- For Claude Desktop: Ensure local URL is accessible
- Test the endpoint with curl first to confirm it's working

---

## Moving to Production

Once tested locally, deploying to a live site is the same process:
1. Run `composer require rocket-park/mcp-wrapper` on production
2. Add config file with production GraphQL token
3. Test at `https://yoursite.com/actions/mcp-wrapper/mcp/index?schemaHandle=public`
4. Connect your AI platform to the live URL (no tunnel needed!)

**Security Note:** Control what content is exposed by configuring GraphQL schema permissions in Craft CP. Only sections/fields you grant access to will be available to AI assistants.
