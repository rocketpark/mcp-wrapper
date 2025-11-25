# MCP Wrapper Deployment Guide

## For Laravel Forge / Production Craft Sites

### Prerequisites

1. **Craft CMS 5.x site** running on Forge
2. **GraphQL schemas configured** in Craft CP → GraphQL
3. **GraphQL tokens** generated for each schema you want to expose

### Installation Steps

#### 1. Add Plugin to Craft Site

**Option A: Via Composer (if published)**
```bash
cd /home/forge/your-craft-site
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

**Option B: Local Development (path repository)**
```bash
# Clone plugin into your Craft project
cd /home/forge/your-craft-site
mkdir -p plugins
git clone https://github.com/rocketpark/mcp-wrapper.git plugins/mcp-wrapper

# Add path repository to composer.json
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./plugins/mcp-wrapper"
    }
  ]
}
```

```bash
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

#### 2. Configure Plugin

Create `config/mcpwrapper.php`:

```php
<?php
return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
        'public' => getenv('GQL_PUBLIC_TOKEN'),
    ],
];
```

#### 3. Set Environment Variables

Add to `.env`:

```bash
# GraphQL Tokens for MCP
GQL_AI_TOKEN="your-graphql-token-here"
GQL_PUBLIC_TOKEN="another-token-here"
```

**To get tokens:**
1. Go to Craft CP → GraphQL → Schemas
2. Select/create a schema
3. Copy the Access Token

#### 4. Test MCP Endpoint

The MCP server will be available at:
```
POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai
```

**Test with curl:**
```bash
# Initialize MCP session
curl -X POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-06-18",
      "capabilities": {},
      "clientInfo": {"name": "test-client", "version": "1.0"}
    }
  }'

# List available tools
curl -X POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai \
  -H "Content-Type": application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/list",
    "params": {}
  }'

# Call a tool
curl -X POST https://your-site.com/actions/mcp-wrapper/mcp/index/ai \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "query_news",
      "arguments": {"limit": 5}
    }
  }'
```

### Connecting MCP Clients

#### Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "craft-cms": {
      "command": "curl",
      "args": [
        "-X", "POST",
        "https://your-site.com/actions/mcp-wrapper/mcp/index/ai",
        "-H", "Content-Type: application/json",
        "-d", "@-"
      ],
      "transport": "stdio"
    }
  }
}
```

**Note:** For production, you'll want to use the official MCP SDK transport instead of curl. This is a simplified example.

#### Other MCP Clients

Point any MCP-compatible client to:
```
https://your-site.com/actions/mcp-wrapper/mcp/index/{schemaHandle}
```

The endpoint accepts JSON-RPC 2.0 POST requests.

### Security Considerations

1. **GraphQL Token Security**: Keep tokens in environment variables, never commit to git
2. **Rate Limiting**: Consider adding rate limiting to the MCP endpoint via Forge/nginx
3. **CORS**: The endpoint allows anonymous access - configure CORS headers if needed
4. **Schema Permissions**: Use GraphQL schemas to limit what data each token can access

### Troubleshooting

**"Unknown schema" error:**
- Verify schema handle in URL matches `config/mcpwrapper.php`
- Check environment variable is set: `php craft/craft app/config GQL_AI_TOKEN`

**GraphQL errors:**
- Verify token has correct permissions in Craft CP → GraphQL
- Test GraphQL query directly at `/api` endpoint

**No tools returned:**
- Ensure your Craft site has sections created
- Check GraphQL schema has access to those sections

### Development Workflow

For local development before deploying to Forge:

1. **Work on feature branch:**
   ```bash
   git checkout -b feature/my-improvements
   ```

2. **Test locally** in a local Craft installation

3. **Push to Git:**
   ```bash
   git push origin feature/my-improvements
   ```

4. **Deploy to Forge test server:**
   - Create deployment trigger in Forge
   - Pull latest code
   - Run `composer install`
   - Test MCP endpoints

5. **Merge to main** when ready for production
