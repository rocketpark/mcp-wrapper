# Botpress Testing Workflows

Copy these workflows into your Botpress bot to test the MCP integration.

---

## Test Workflow 1: Check Tool Count

**Purpose:** Verify connection and count available tools

### Steps:
1. Add "Execute Code" card
2. Paste this code:

```javascript
const craftMcp = workflow.craftMcp

try {
  // List all available tools
  const result = await craftMcp.listTools()
  
  workflow.toolCount = result.tools.length
  workflow.hasServices = result.tools.some(t => t.name === 'query_services')
  workflow.hasSystemInfo = result.tools.some(t => t.name === 'craft_get_system_info')
  workflow.hasDangerousTools = result.tools.some(t => t.name === 'craft_clear_caches')
  
  workflow.success = true
  workflow.message = `Found ${workflow.toolCount} tools`
  
} catch (error) {
  workflow.success = false
  workflow.error = error.message
  workflow.message = 'Failed to connect to Craft CMS'
}
```

3. Add "Send Message" card:

```
{{workflow.message}}

✅ Connection: {{#if workflow.success}}Working{{else}}Failed{{/if}}
📊 Tool Count: {{workflow.toolCount}}
🔍 Has query_services: {{workflow.hasServices}}
⚙️ Has system_info: {{workflow.hasSystemInfo}}
⚠️ Has dangerous tools: {{workflow.hasDangerousTools}}

{{#if workflow.error}}
❌ Error: {{workflow.error}}
{{/if}}
```

### ✅ Expected Output:
```
Found 19 tools

✅ Connection: Working
📊 Tool Count: 19
🔍 Has query_services: true
⚙️ Has system_info: true
⚠️ Has dangerous tools: false  <-- Should be false in production!
```

---

## Test Workflow 2: Query Content

**Purpose:** Test querying Craft CMS content

### Steps:
1. Add "Execute Code" card:

```javascript
const craftMcp = workflow.craftMcp

try {
  // Query services section (replace with your section handle)
  const result = await craftMcp.callTool('query_services', {
    limit: 5,
    search: 'fire'  // Optional search term
  })
  
  // Parse the response
  const data = JSON.parse(result.content[0].text)
  
  if (data.entries && data.entries.length > 0) {
    workflow.entryCount = data.entries.length
    workflow.firstEntry = data.entries[0].title
    workflow.success = true
    workflow.message = `Found ${data.entries.length} services`
  } else {
    workflow.entryCount = 0
    workflow.success = true
    workflow.message = 'No services found'
  }
  
} catch (error) {
  workflow.success = false
  workflow.error = error.message
  workflow.message = 'Query failed'
}
```

2. Add "Send Message" card:

```
{{workflow.message}}

{{#if workflow.success}}
  Found {{workflow.entryCount}} entries
  {{#if workflow.firstEntry}}
  First entry: {{workflow.firstEntry}}
  {{/if}}
{{else}}
  ❌ Error: {{workflow.error}}
{{/if}}
```

### ✅ Expected Output:
```
Found 5 services

Found 5 entries
First entry: Fire Protection Services
```

---

## Test Workflow 3: Security Check

**Purpose:** Verify dangerous tools are blocked

### Steps:
1. Add "Execute Code" card:

```javascript
const craftMcp = workflow.craftMcp

// First check if dangerous tools are in the list
try {
  const toolsList = await craftMcp.listTools()
  const hasClearCaches = toolsList.tools.some(t => t.name === 'craft_clear_caches')
  
  workflow.inList = hasClearCaches
  workflow.listCheck = hasClearCaches ? '❌ FAIL: Exposed' : '✅ PASS: Hidden'
  
} catch (error) {
  workflow.inList = null
  workflow.listCheck = `Error: ${error.message}`
}

// Then try to execute it
try {
  const result = await craftMcp.callTool('craft_clear_caches', {
    caches: ['all']
  })
  
  // If we get here, the tool was executed (BAD!)
  workflow.canExecute = true
  workflow.executeCheck = '❌ FAIL: Tool executed'
  
} catch (error) {
  // If we get an error, that's good (tool was blocked)
  workflow.canExecute = false
  workflow.executeCheck = '✅ PASS: Tool blocked'
  workflow.blockMessage = error.message
}

// Overall security status
if (!workflow.inList && !workflow.canExecute) {
  workflow.securityStatus = '✅ SECURE'
  workflow.statusColor = 'green'
} else {
  workflow.securityStatus = '⚠️ INSECURE'
  workflow.statusColor = 'red'
}
```

2. Add "Send Message" card:

```
🔒 Security Test Results
========================

Tool Visibility: {{workflow.listCheck}}
Tool Execution: {{workflow.executeCheck}}

{{#if workflow.blockMessage}}
Block message: "{{workflow.blockMessage}}"
{{/if}}

Overall Status: {{workflow.securityStatus}}

{{#if workflow.statusColor == 'red'}}
⚠️ WARNING: Dangerous tools are accessible!
Consider setting enableDangerousTools: false in production.
{{else}}
✅ Your MCP server is properly secured for production use.
{{/if}}
```

### ✅ Expected Output (Production):
```
🔒 Security Test Results
========================

Tool Visibility: ✅ PASS: Hidden
Tool Execution: ✅ PASS: Tool blocked

Block message: "Dangerous tools are not enabled. Set 'enableDangerousTools' to true in config."

Overall Status: ✅ SECURE

✅ Your MCP server is properly secured for production use.
```

---

## Test Workflow 4: Get System Info

**Purpose:** Test a safe administrative tool

### Steps:
1. Add "Execute Code" card:

```javascript
const craftMcp = workflow.craftMcp

try {
  const result = await craftMcp.callTool('craft_get_system_info', {})
  
  const data = JSON.parse(result.content[0].text)
  
  if (data.success && data.data) {
    workflow.craftVersion = data.data.craftVersion
    workflow.phpVersion = data.data.phpVersion
    workflow.dbDriver = data.data.database?.driver || 'unknown'
    workflow.pluginCount = data.data.plugins?.length || 0
    workflow.success = true
  } else {
    workflow.success = false
    workflow.error = 'Invalid response format'
  }
  
} catch (error) {
  workflow.success = false
  workflow.error = error.message
}
```

2. Add "Send Message" card:

```
{{#if workflow.success}}
📊 System Information
===================

Craft CMS: {{workflow.craftVersion}}
PHP: {{workflow.phpVersion}}
Database: {{workflow.dbDriver}}
Plugins: {{workflow.pluginCount}} installed

✅ System info retrieved successfully
{{else}}
❌ Failed to get system info
Error: {{workflow.error}}
{{/if}}
```

### ✅ Expected Output:
```
📊 System Information
===================

Craft CMS: 5.8.21
PHP: 8.2.30
Database: mysql
Plugins: 21 installed

✅ System info retrieved successfully
```

---

## Test Workflow 5: Search with AI Context

**Purpose:** Use natural language search

### Steps:
1. Add "Capture Information" card to get user input:
   - Variable: `searchTerm`
   - Question: "What would you like to search for?"

2. Add "Execute Code" card:

```javascript
const craftMcp = workflow.craftMcp
const searchTerm = workflow.searchTerm || 'fire'

try {
  // Search across services (or your section)
  const result = await craftMcp.callTool('query_services', {
    search: searchTerm,
    limit: 10
  })
  
  const data = JSON.parse(result.content[0].text)
  
  if (data.entries && data.entries.length > 0) {
    // Format results for display
    workflow.results = data.entries.map(entry => ({
      title: entry.title,
      slug: entry.slug,
      url: entry.uri || `/${entry.slug}`
    }))
    
    workflow.resultCount = data.entries.length
    workflow.success = true
    
  } else {
    workflow.results = []
    workflow.resultCount = 0
    workflow.success = true
  }
  
} catch (error) {
  workflow.success = false
  workflow.error = error.message
}
```

3. Add "Send Message" card:

```
{{#if workflow.success}}
  {{#if workflow.resultCount > 0}}
Found {{workflow.resultCount}} results for "{{workflow.searchTerm}}":

{{#each workflow.results}}
• {{this.title}}
  URL: {{this.url}}
{{/each}}
  {{else}}
No results found for "{{workflow.searchTerm}}"
  {{/if}}
{{else}}
❌ Search failed: {{workflow.error}}
{{/if}}
```

### ✅ Expected Output:
```
Found 5 results for "fire":

• Fire Protection Services
  URL: /services/fire-protection
• Fire Alarm Systems
  URL: /services/fire-alarms
• Fire Safety Consulting
  URL: /services/fire-safety
...
```

---

## Quick Setup in Botpress

1. **Install the Integration:**
   - Go to Integrations in Botpress Studio
   - Find "Craft CMS via MCP"
   - Click Install
   - Configure:
     - Base URL: `http://jensenhughes.test`
     - Schema Handle: `MCPSchema`

2. **Initialize craftMcp in your bot:**
   - Add this at the START of your main workflow:

```javascript
// Initialize MCP client
const { client } = bp.integration.craftCmsMcp
workflow.craftMcp = client
```

3. **Now you can use any of the test workflows above!**

---

## Troubleshooting in Botpress

### Error: "Cannot read property 'client' of undefined"
**Solution:** Make sure you've installed and configured the Craft CMS MCP integration

### Error: "fetch failed" or "ECONNREFUSED"
**Solution:** 
- Check your Base URL is correct
- Make sure the Jensen Hughes site is running
- Verify the URL is accessible from Botpress servers

### Error: "Tool 'X' is disabled"
**Solution:** This is working correctly! The tool is intentionally blocked by security settings

### No results from query
**Solution:**
- Check the section handle matches (e.g., 'services')
- Verify the GraphQL schema has access to that section
- Try without search filters first

---

## Production Testing Checklist

Before going live with Botpress:

- [ ] Security test shows "✅ SECURE" status
- [ ] Tool count is reasonable (10-20 tools)
- [ ] craft_clear_caches is NOT in tools list
- [ ] Query tools return actual data
- [ ] System info returns correct versions
- [ ] Search functionality works
- [ ] Error messages are user-friendly
- [ ] Response times are acceptable (< 2s)
- [ ] IP whitelist is configured (if needed)
- [ ] GraphQL token has proper permissions

**Once all checked, you're ready for production! 🚀**
