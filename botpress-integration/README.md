# Craft CMS MCP Integration for Botpress

This is a Botpress integration that connects your bots to Craft CMS content via the Model Context Protocol (MCP).

## 🚀 Quick Start

### Prerequisites

1. **Botpress Account**: Sign up at [botpress.com](https://botpress.com)
2. **Botpress CLI**: Install globally
   ```bash
   npm install -g @botpress/cli
   ```
3. **Craft CMS with MCP Wrapper**: Your Craft site must have the MCP Wrapper plugin installed

### Installation Steps

#### 1. Install Dependencies

```bash
cd botpress-integration
npm install
```

#### 2. Login to Botpress

```bash
bp login
```

This will prompt you for a **Personal Access Token**. Get one from:
- Go to [Botpress Dashboard](https://app.botpress.cloud)
- Navigate to **Profile** → **Personal Access Tokens**
- Create a new token and paste it

#### 3. Build the Integration

```bash
npm run build
```

This compiles the TypeScript code and generates the integration bundle.

#### 4. Deploy to Botpress

```bash
npm run deploy
```

You'll be prompted to:
- Select your **Workspace**
- Confirm deployment

The integration will be deployed **privately** to your workspace, visible only to your team.

#### 5. Verify Deployment

After deployment:
1. Go to [Botpress Dashboard](https://app.botpress.cloud)
2. Navigate to **Integrations** → **Your Integrations**
3. You should see **Craft CMS via MCP** listed

## 🤖 Using in Your Bot

### 1. Install Integration in Your Bot

1. Open your bot in **Botpress Studio** (or create a new one)
2. Go to **Integrations** tab (left sidebar)
3. Find **Craft CMS via MCP** in your private integrations
4. Click **Install**

### 2. Configure the Integration

When prompted, enter:

- **MCP Server URL**: `https://servicecurator.com`
- **Schema Handle**: Choose `ai`, `frontend`, or `internal`

### 3. Use Actions in Workflows

The integration provides 7 actions:

#### Action 1: List Available MCP Tools

Discovers what content types are available from Craft CMS.

**Example:**
```
In a Workflow Node:
1. Add "Execute Code" or "Standard Action"
2. Select: "List Available MCP Tools"
3. Store output in: workflow.availableTools
```

#### Action 2: Query Craft CMS Content

Search and retrieve content entries.

**Example:**
```
1. Add action: "Query Craft CMS Content"
2. Configure inputs:
   - Tool Name: "query_news"
   - Search Query: {{workflow.userQuery}}
   - Limit: 10
   - Offset: 0
3. Store output in: workflow.searchResults
```

#### Action 3: Get Specific Entry

Fetch a single entry by ID.

**Example:**
```
1. Add action: "Get Specific Entry"
2. Configure inputs:
   - Tool Name: "query_news"
   - Entry ID: {{workflow.selectedId}}
3. Store output in: workflow.entryDetail
```

#### Action 4: Intelligent Natural Language Query 🆕

Parse natural language and automatically query the right content with smart filters.

**Example:**
```
1. Add action: "Intelligent Natural Language Query"
2. Configure inputs:
   - User Input: {{event.preview}} (or "show me recent sustainability news")
   - Conversation Context: {{workflow.previousSearches}} (optional)
3. Store output in: workflow.intelligentResults

The action automatically:
- Detects content types (news, topics)
- Extracts search keywords
- Applies date filters (last week, last month, etc.)
- Returns formatted results with summary
```

#### Action 5: Query with AI Knowledge 🆕

Answer questions about your content using AI.

**Example:**
```
1. Add action: "Query with AI Knowledge"
2. Configure inputs:
   - Question: "What sustainability topics do we cover?"
   - Content Types: ["news", "topics"]
   - Max Results: 10
3. Store output in: workflow.knowledgeAnswer

Returns:
- AI-generated answer based on your content
- Source entries used
- Confidence level (high/medium/low)
```

#### Action 6: Remember Conversation Context 🆕

Store user preferences and history for better follow-ups.

**Example:**
```
1. Add action: "Remember Conversation Context"
2. Configure inputs:
   - User ID: {{event.userId}}
   - Context Type: "search" or "viewed" or "preference"
   - Data: {{workflow.currentSearch}}
3. Store output in: workflow.contextStored
```

#### Action 7: Get User Context 🆕

Retrieve stored conversation history.

**Example:**
```
1. Add action: "Get User Context"
2. Configure inputs:
   - User ID: {{event.userId}}
   - Context Type: "all" (or specific type)
3. Store output in: workflow.userHistory
```

## 📝 Example Bot Workflow

Here's a complete example for a news bot:

### Workflow: "Search News"

1. **Trigger**: User message contains "news" or "article"

2. **Node 1: Extract Search Term**
   - Use AI to extract what the user wants to search for
   - Store in `workflow.searchTerm`

3. **Node 2: Query News**
   - Action: Query Craft CMS Content
   - Tool Name: `query_news`
   - Search Query: `{{workflow.searchTerm}}`
   - Limit: 5
   - Store output: `workflow.newsResults`

4. **Node 3: Format Response**
   - Execute Code to format the results
   ```javascript
   const results = workflow.newsResults.results
   if (results.length === 0) {
     workflow.response = "I couldn't find any news articles matching your search."
   } else {
     const titles = results.map((r, i) => `${i+1}. ${r.title}`).join('\n')
     workflow.response = `Here are the top ${results.length} news articles:\n\n${titles}\n\nWhich one would you like to know more about?`
   }
   ```

5. **Node 4: Send Response**
   - Send text: `{{workflow.response}}`

### Workflow: "Natural Language Search" 🆕

1. **Trigger**: User asks naturally (e.g., "show me sustainability news from last month")

2. **Node 1: Intelligent Query**
   - Action: Intelligent Natural Language Query
   - User Input: `{{event.preview}}`
   - Store output: `workflow.intelligentResults`

3. **Node 2: Display Results**
   - Send text: `{{workflow.intelligentResults.summary}}`
   - Execute Code to format entries:
   ```javascript
   const results = workflow.intelligentResults.results
   let formatted = ""
   
   results.forEach((entry, i) => {
     formatted += `\n${i+1}. **${entry.title}**\n`
     if (entry.summary) formatted += `   ${entry.summary.substring(0, 100)}...\n`
     if (entry.postDate) formatted += `   📅 ${new Date(entry.postDate).toLocaleDateString()}\n`
   })
   
   workflow.formattedResults = formatted
   ```
   - Send text: `{{workflow.formattedResults}}`

### Workflow: "Ask Questions About Content" 🆕

1. **Trigger**: User asks a question (e.g., "What are our main sustainability topics?")

2. **Node 1: Knowledge Query**
   - Action: Query with AI Knowledge
   - Question: `{{event.preview}}`
   - Content Types: `["news", "topics"]`
   - Max Results: 10
   - Store output: `workflow.knowledgeAnswer`

3. **Node 2: Display Answer**
   - Send text: `{{workflow.knowledgeAnswer.answer}}`
   - Execute Code to show sources:
   ```javascript
   const sources = workflow.knowledgeAnswer.sources
   if (sources.length > 0) {
     let sourcesText = "\n\n📚 **Sources:**\n"
     sources.forEach((source, i) => {
       sourcesText += `${i+1}. [${source.title}](${source.url})\n`
     })
     workflow.sourcesFormatted = sourcesText
   }
   ```
   - Send text: `{{workflow.sourcesFormatted}}`

### Workflow: "Conversational Follow-ups" 🆕

1. **Initial Search**: User searches for content

2. **Node 1: Remember What They Searched**
   - Action: Remember Conversation Context
   - User ID: `{{event.userId}}`
   - Context Type: `search`
   - Data: `{{workflow.intelligentResults}}`

3. **User Follow-up**: "Show me more like that"

4. **Node 2: Get Context**
   - Action: Get User Context
   - User ID: `{{event.userId}}`
   - Context Type: `search`
   - Store output: `workflow.userContext`

5. **Node 3: Smart Follow-up Query**
   - Action: Intelligent Natural Language Query
   - User Input: `{{event.preview}}`
   - Conversation Context: `{{workflow.userContext.summary}}`
   - Store output: `workflow.followupResults`

## 🔧 Configuration Options

### MCP Server URL
The base URL of your Craft CMS installation. For Service Curator, use:
```
https://servicecurator.com
```

### Schema Handle
Choose which GraphQL schema to use:
- **`ai`**: Optimized for AI assistants (recommended for bots)
- **`frontend`**: Public-facing content
- **`internal`**: Internal content with more permissions

## 🛠️ Development

### File Structure

```
botpress-integration/
├── package.json              # Dependencies and scripts
├── tsconfig.json            # TypeScript configuration
├── integration.definition.ts # Defines actions and config
├── src/
│   └── index.ts            # Implementation logic
├── icon.svg                # Integration icon
└── hub.md                  # Hub documentation
```

### Making Changes

1. Edit the files
2. Run `npm run build` to rebuild
3. Run `npm run deploy` to redeploy
4. Refresh your bot in Botpress Studio

### Type Checking

```bash
npm run check:type
```

## 🐛 Troubleshooting

### "Failed to list tools"
**Issue**: Integration can't connect to MCP server

**Solutions**:
- Verify MCP Server URL is correct
- Check that servicecurator.com is accessible
- Ensure MCP Wrapper plugin is installed and active in Craft CMS

### "Unknown schema handle"
**Issue**: The schema doesn't exist in Craft CMS config

**Solutions**:
- Check `config/mcpwrapper.php` in Craft CMS
- Verify the schema handle (`ai`, `frontend`, `internal`) is defined
- Check environment variables for GraphQL tokens

### "No results returned"
**Issue**: Query returns empty results

**Solutions**:
- Check GraphQL schema permissions in Craft CMS
- Verify the tool name is correct (use "List Tools" action first)
- Test the MCP server directly with curl:
  ```bash
  curl -X POST 'https://servicecurator.com/actions/mcp-wrapper/mcp/index?schemaHandle=ai' \
    -H 'Content-Type: application/json' \
    -d '{
      "jsonrpc": "2.0",
      "id": 1,
      "method": "tools/list"
    }'
  ```

### Integration Not Showing in Studio
**Issue**: After deployment, integration doesn't appear

**Solutions**:
- Wait a few minutes for cache to clear
- Refresh Botpress Studio (hard refresh: Cmd+Shift+R)
- Check deployment logs for errors
- Verify you're in the correct workspace

## 📚 Additional Resources

- [Botpress Documentation](https://botpress.com/docs)
- [Botpress SDK Reference](https://botpress.com/docs/for-developers/sdk)
- [Model Context Protocol Spec](https://modelcontextprotocol.io)
- [Craft CMS MCP Wrapper](../README.md)

## 🤝 Support

For issues or questions:
- Check the troubleshooting section above
- Review Botpress logs in the dashboard
- Check Craft CMS logs for MCP server errors
- Contact Rocket Park support

## 📄 License

MIT License - See main project for details
