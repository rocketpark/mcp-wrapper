# Botpress Integration Setup for Jensen Hughes

## After MCP Wrapper is Deployed & Working

### 1. Update Integration Code
Edit `/Users/elizabethstein/Projects/mcp-wrapper/botpress-integration/src/index.ts`:

```typescript
// Update the MCP endpoint URL
const JENSENHUGHES_MCP_URL = 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes';
const JENSENHUGHES_TOKEN = 'b8p18Vou0hDttkkd_cUHSAyXuIyg9U6x';
```

### 2. Deploy to Botpress Cloud
```bash
cd /Users/elizabethstein/Projects/mcp-wrapper/botpress-integration
npm install
npm run deploy
```

### 3. Configure in Botpress Dashboard
1. Go to https://app.botpress.cloud
2. Create new bot: "Jensen Hughes Assistant"
3. Install the MCP Wrapper integration
4. Configure with JH staging URL and token

### 4. Create Bot Workflows
Based on available sections (officeLocations, ourTeam), create workflows like:
- **Find Office**: "Where is your California office?"
- **Find Team Member**: "Who can I talk to about fire safety?"
- **Browse Services**: Natural language service browsing

### 5. Test Natural Language Queries
Example queries to implement:
- "Who can I contact about fire safety in California?"
- "Find an office near Los Angeles"
- "Show me experts in structural engineering"

## Next Steps After This Conversation
1. [ ] Push mcp-wrapper fix to GitHub (handle git auth)
2. [ ] Ask Adam to run `composer update rocket-park/mcp-wrapper` on staging
3. [ ] Run TEST-AFTER-DEPLOY.sh to verify sections work
4. [ ] Update Botpress integration code
5. [ ] Deploy to Botpress Cloud
6. [ ] Build natural language workflows
