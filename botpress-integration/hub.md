# Craft CMS via MCP Integration

Connect your Botpress bot to Craft CMS content through the Model Context Protocol (MCP).

## Overview

This integration allows your Botpress bot to query and retrieve content from a Craft CMS website using the Model Context Protocol. It provides actions to list available content types and query entries from any configured section.

## Features

- 🔍 **List Tools**: Discover all available Craft CMS content types
- 📄 **Query Content**: Search and retrieve entries with pagination
- 🎯 **Get Entry**: Fetch specific entries by ID
- 🔐 **Secure**: Uses GraphQL schema permissions from your Craft CMS setup
- ⚡ **Fast**: Direct JSON-RPC 2.0 communication with MCP server

## Setup

### Prerequisites

- A Craft CMS site with the MCP Wrapper plugin installed
- The MCP server must be publicly accessible

### Configuration

When installing this integration in your bot, you'll need to provide:

1. **MCP Server URL**: The base URL of your Craft CMS site (e.g., `https://servicecurator.com`)
2. **Schema Handle**: The GraphQL schema to use (`ai`, `frontend`, or `internal`)

## Usage in Botpress Studio

### 1. List Available Content Types

Add the **List Available MCP Tools** action to your workflow to discover what content is available:

```
Action: List Available MCP Tools
Output: → workflow.tools
```

This returns an array of available tools like:
- `query_news`
- `query_products`
- `query_pages`

### 2. Query Content

Use the **Query Craft CMS Content** action to retrieve entries:

```
Action: Query Craft CMS Content
Inputs:
  - Tool Name: "query_news"
  - Search Query: "latest updates"
  - Limit: 10
  - Offset: 0
Output: → workflow.newsResults
```

### 3. Get Specific Entry

Retrieve a single entry by ID:

```
Action: Get Specific Entry
Inputs:
  - Tool Name: "query_news"
  - Entry ID: "123"
Output: → workflow.newsEntry
```

## Example Bot Workflow

Here's a simple example of a bot that answers questions about news articles:

1. **User asks**: "What are the latest news articles?"
2. **Bot executes**: Query Craft CMS Content (query_news, limit: 5)
3. **Bot responds**: "Here are the latest 5 news articles: [list titles]"
4. **User asks**: "Tell me more about the first one"
5. **Bot executes**: Get Specific Entry (query_news, id from previous results)
6. **Bot responds**: [Full article details]

## Troubleshooting

- **"Failed to list tools"**: Check that your MCP Server URL is correct and accessible
- **"Unknown schema handle"**: Verify the schema handle exists in your Craft CMS configuration
- **Empty results**: Check your GraphQL schema permissions in Craft CMS

## Support

For issues with this integration, please contact Rocket Park or check the MCP Wrapper plugin documentation.
