# Connecting to Airia Platform

## SSE Endpoint for Airia

Airia's Custom MCP Server interface requires Server-Sent Events (SSE) transport. Use this endpoint:

```
https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=public
```

## Setup Steps

1. **Log in to Airia** at your organization's Airia workspace

2. **Navigate to Settings** → **Tool Library**

3. **Click "Add Custom MCP Server"**

4. **Enter the SSE URL**:
   ```
   https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=public
   ```

5. **Create or Select Credentials** (if required):
   - Credential Type: MCP Access Token
   - Name: Service Curator MCP
   - Leave token blank (no authentication required for public schema)

6. **Save and Connect**

## What Happens

When Airia connects to the SSE endpoint, it will automatically receive:

1. **Initialize Response** - Server capabilities and version info
2. **Tools List** - Available tools (`query_news`, `query_topics`)
3. **Ready Status** - Connection confirmation

The SSE stream stays open and Airia can then call the tools through its interface.

## Available Tools

- **query_news** - Query news entries from Craft CMS
- **query_topics** - Query topic categories

## Troubleshooting

### Connection Fails

- **Check URL**: Ensure you're using the `/sse` endpoint, not `/index`
- **Schema Handle**: Verify `schemaHandle=public` is in the URL
- **Network**: Ensure your network allows SSE connections (some corporate firewalls block event streams)

### No Tools Appear

- Wait 5-10 seconds after connection - SSE streams events sequentially
- Check Airia's console logs for errors
- Verify the public schema has access to news and topics sections

### Tools Don't Execute

- The SSE endpoint only handles discovery, not execution
- Tool execution may require additional Airia configuration
- Contact Airia support for execution troubleshooting

## Alternative Schemas

To use a different GraphQL schema, change the `schemaHandle` parameter:

```
https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=ai
https://servicecurator.com/actions/mcp-wrapper/mcp/sse?schemaHandle=internal
```

Note: These schemas must be configured in the plugin's `config.php` file and have valid GraphQL tokens.

## Technical Details

The SSE endpoint implements the deprecated HTTP+SSE transport from the MCP specification for compatibility with platforms that haven't yet adopted the modern Streamable HTTP transport. 

When a client connects:
1. Server sends `text/event-stream` headers
2. Server streams `initialize` response as `message` event
3. Server streams `tools/list` response as `message` event  
4. Server sends `ready` event with connection status
5. Connection stays open for additional events

This is a read-only connection for discovery. Tool execution happens through separate requests.
