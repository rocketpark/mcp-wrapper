# Airia Setup Guide

Connect Airia to the Service Curator MCP server using the legacy SSE transport.

## Prerequisites

1. Access to Airia platform
2. Service Curator MCP server running at servicecurator.com

## Setup Steps

### 1. Create MCP Credentials in Airia

1. Log in to Airia
2. Click your profile icon (bottom left)
3. Select "Settings & billing"
4. Go to "Credentials" tab
5. Click "New credentials"
6. Select "MCP Access Token"
7. Name it "Service Curator MCP"
8. Leave token blank (public schema requires no authentication)
9. Save the credential

### 2. Connect to Service Curator

1. In Airia, go to AI Lab (home icon, top left)
2. Click "Manage connections" (link icon in top bar)
3. Click "Connect with your own MCP server"
4. Enter SSE URL:
   ```
   https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=public
   ```
5. Select your "Service Curator MCP" credential
6. Click "Connect"

## How It Works

The SSE endpoint implements the MCP legacy SSE transport (two-endpoint pattern):

1. **GET /sse**: Opens an SSE stream and sends:
   - `endpoint` event with session-specific `/messages` URL
   - Keepalive messages every 15 seconds

2. **POST /messages?sessionId=XXX**: Handles client-to-server JSON-RPC messages:
   - initialize
   - tools/list
   - tools/call
   - All other MCP methods

This two-endpoint pattern is required by the legacy SSE transport and follows the MCP specification.

## Available Tools

Once connected, you'll have access to:

- **query_news**: Query news articles from Service Curator
  - Filter by ID, slug, title, date, status, etc.
  - Full-text search support
  - Pagination with limit/offset

- **query_topics**: Query topic categories
  - Same filtering capabilities as news
  - Relationship data included

## Troubleshooting

### "Failed to load Mcp server info"

This error can occur if:
- The server is unreachable
- There's a CORS issue
- The SSE stream isn't establishing correctly

To verify the server is working:
```bash
# Test SSE endpoint - should return endpoint event with sessionId
curl -N https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=public

# Should output:
# event: endpoint
# data: {"endpoint":"/actions/mcp-wrapper/mcp/messages?sessionId=..."}
# 
# : keepalive
```

Test the messages endpoint with a valid session:
```bash
# Get a session ID from the SSE stream first
# Then test the messages endpoint:
curl -X POST 'https://servicecurator.com/actions/mcp-wrapper/mcp/messages?sessionId=YOUR_SESSION_ID' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"airia","version":"1.0"}}}'

# Should return:
# {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18",...}}
```

### Connection drops frequently

SSE connections are persistent. If they drop frequently:
- Check network stability
- Verify firewall isn't blocking persistent connections
- Contact Airia support for platform-specific issues

## Alternative Schemas

To use a different GraphQL schema, change the `schemaHandle` parameter:

```
https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=ai
https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=internal
```

Note: These schemas must be configured in the plugin's `config.php` file and have valid GraphQL tokens.

## Alternative: Claude Desktop

For testing or alternative access, you can use Claude Desktop with the Streamable HTTP endpoint:

```json
{
  "mcpServers": {
    "service-curator": {
      "command": "npx",
      "args": [
        "@modelcontextprotocol/server-mcp-remote",
        "https://servicecurator.com/actions/mcp-wrapper/mcp/index?schemaHandle=public"
      ]
    }
  }
}
```

Add this to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS).

