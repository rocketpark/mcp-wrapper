# MCP Wrapper Testing Guide

Complete testing documentation for the MCP Wrapper plugin.

---

## Quick Test Scripts

Three test scripts are available for different scenarios:

### 1. Security Test Suite
**File:** `test-mcp-security.sh`  
**Purpose:** Comprehensive security validation (6 test scenarios)

```bash
./test-mcp-security.sh
```

Tests:
- ✅ Dangerous tools hidden when disabled
- ✅ Disabled tools filtered correctly
- ✅ Dangerous tool execution blocked
- ✅ Safe tools work correctly
- ✅ GraphQL query tools functional
- ✅ MCP protocol handshake

### 2. Simple Diagnostic Tests
**File:** `test-mcp-simple.sh`  
**Purpose:** Basic connectivity and functionality checks

```bash
./test-mcp-simple.sh
```

### 3. Direct HTTP Test
**File:** `test-mcp-direct.sh`  
**Purpose:** Verbose curl diagnostic with full HTTP details

```bash
./test-mcp-direct.sh
```

---

## Local Testing

### Prerequisites

- Craft CMS 5.0+ installed and running
- PHP 8.2+
- GraphQL schema configured in Craft
- Redis (for caching)
- curl or Postman for API testing

### Setup

1. **Install plugin in Craft project:**
   ```bash
   cd /path/to/craft-project
   composer require rocket-park/mcp-wrapper
   php craft plugin/install mcp-wrapper
   ```

2. **Configure GraphQL token in `.env`:**
   ```bash
   MCP_GQLSCHEMA_TOKEN="your-token-here"
   ```

3. **Add to `config/mcpwrapper.php`:**
   ```php
   <?php
   return [
       'schemas' => [
           'MCPSchema' => getenv('MCP_GQLSCHEMA_TOKEN'),
       ],
       'security' => [
           'enableDangerousTools' => false,
           'disabledTools' => [],
           'ipWhitelist' => [],
       ],
   ];
   ```

4. **Start Redis:**
   ```bash
   brew services start redis
   ```

5. **Verify site is running:**
   ```bash
   curl http://your-site.test
   ```

### Manual Tests

#### Test 1: Tools List
```bash
curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq
```

**Expected:** List of available tools (GraphQL queries + craft system tools)

#### Test 2: Safe Tool Execution
```bash
curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "id":2,
    "method":"tools/call",
    "params":{
      "name":"craft_get_system_info",
      "arguments":{}
    }
  }' | jq
```

**Expected:** System information including Craft version

#### Test 3: Security Blocking
```bash
curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "id":3,
    "method":"tools/call",
    "params":{
      "name":"craft_clear_caches",
      "arguments":{}
    }
  }' | jq
```

**Expected:** Error message: "Dangerous tools are not enabled"

#### Test 4: GraphQL Query
```bash
curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "id":4,
    "method":"tools/call",
    "params":{
      "name":"query_yourSection",
      "arguments":{"limit":5}
    }
  }' | jq
```

**Expected:** Entries from your section

---

## Staging Testing

### Environment
- **URL:** https://jensenhughes3.on-forge.com
- **Schema:** MCPSchema
- **Auto-deploy:** From Bitbucket staging3 branch via Laravel Forge

### Quick Verification

```bash
# Test tools list
curl -s -X POST "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq -r '.result.tools | length'

# Test security blocking
curl -s -X POST "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"craft_clear_caches","arguments":{}}}' | jq -r '.error.message'

# Test safe tool
curl -s -X POST "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"craft_get_system_info","arguments":{}}}' | jq -r '.result.content[0].text' | jq -r '.craft.version'
```

---

## Botpress Integration Testing

### Setup

1. **Update Botpress Configuration:**
   - Go to Botpress Cloud Dashboard
   - Navigate to bot settings → Integrations
   - Find "Craft CMS via MCP"
   - Update configuration:
     - **mcpServerUrl:** `https://jensenhughes3.on-forge.com`
     - **schemaHandle:** `MCPSchema`
   - Save and restart integration

2. **Use Test Checklist:**
   See [BOTPRESS-STAGING-TEST-CHECKLIST.md](BOTPRESS-STAGING-TEST-CHECKLIST.md) for comprehensive test scenarios.

### Quick Bot Tests

Ask these questions in your bot:

**Content Queries:**
1. "What office locations do you have?"
2. "Tell me about your team"
3. "What services do you offer?"

**System Information:**
4. "What version of Craft CMS?"
5. "Show me system information"

**Security Tests (should fail gracefully):**
6. "Clear the cache"
7. "Run the queue"

### Expected Behavior

✅ **Should Work:**
- All content queries return real data
- System information tools execute
- Responses are accurate and complete

❌ **Should Be Blocked:**
- Dangerous operations (clear cache, rebuild config, run queue)
- Bot receives: "Dangerous tools are not enabled"
- Bot handles limitation gracefully

---

## Test Workflows

### Workflow 1: Office Location Search
See [BOTPRESS-TEST-WORKFLOWS.md](BOTPRESS-TEST-WORKFLOWS.md) for detailed autonomous node test scenarios including:
- Office location queries by region
- Team member searches
- Service browsing
- Multi-step conversations

---

## Troubleshooting

### Common Issues

#### 1. Route Not Found (404)
**Symptoms:** `curl` returns "Site not found" or 404

**Solutions:**
```bash
# Check Herd site link
cd /path/to/craft-project
herd unlink your-site
herd link your-site

# Verify .env has correct URL
grep PRIMARY_SITE_URL .env

# Clear Craft caches
php craft clear-caches/all
```

#### 2. Redis Connection Refused
**Symptoms:** "Connection refused" in logs

**Solutions:**
```bash
# Start Redis
brew services start redis

# Verify running
brew services list | grep redis
```

#### 3. Plugin Not Found
**Symptoms:** Plugin doesn't appear in Craft CP

**Solutions:**
```bash
# Reinstall plugin
php craft plugin/uninstall mcp-wrapper
composer require rocket-park/mcp-wrapper
php craft plugin/install mcp-wrapper
```

#### 4. Empty Tools List
**Symptoms:** `tools/list` returns empty array

**Possible causes:**
- GraphQL schema doesn't exist
- GraphQL token is invalid
- No sections/entries in Craft
- Schema has no permissions

**Debug:**
```bash
# Check GraphQL schema exists
php craft graphql/print-schema MCPSchema

# Verify token in config
cat config/mcpwrapper.php

# Test GraphQL directly
curl -X POST http://your-site.test/api \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"query":"{ entries { id title } }"}'
```

#### 5. Test Script JSON Path Errors
**Symptoms:** `test-mcp-security.sh` reports failures

**Common fixes:**
- JSON path should be `.craft.version` not `.data.craftVersion`
- IS_ERROR default should be `// false` not `// true`
- Use `[ -n "$VAR" ]` instead of `[ ! -z "$VAR" ]`

---

## Security Configuration Testing

### Test Different Security Scenarios

#### Scenario 1: Production (Locked Down)
```php
'security' => [
    'enableDangerousTools' => false,
    'disabledTools' => ['craft_read_logs'],
    'ipWhitelist' => ['203.0.113.0/24'],
],
```

**Verify:**
- Dangerous tools hidden and blocked
- Only allowed IPs can access
- Logs tool disabled

#### Scenario 2: Development (Open)
```php
'security' => [
    'enableDangerousTools' => true,
    'disabledTools' => [],
    'ipWhitelist' => [],
],
```

**Verify:**
- All tools available
- All IPs can access
- Cache clearing works

#### Scenario 3: Staging (Selective)
```php
'security' => [
    'enableDangerousTools' => false,
    'disabledTools' => [],
    'ipWhitelist' => ['10.0.0.0/8'],
],
```

**Verify:**
- Dangerous tools blocked
- Internal IPs only
- Safe tools available

---

## Performance Testing

### Tool Response Times

```bash
# Measure response time
time curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' > /dev/null
```

**Expected:**
- Tools list: < 500ms
- Simple queries: < 1s
- Complex queries: < 3s

### Load Testing

```bash
# Run 100 concurrent requests
for i in {1..100}; do
  curl -s -X POST "http://your-site.test/mcp/MCPSchema" \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","id":'$i',"method":"tools/list"}' &
done
wait
```

---

## Deployment Testing Checklist

After deploying to a new environment:

- [ ] Plugin installed and enabled
- [ ] Config file created with correct tokens
- [ ] Redis running
- [ ] GraphQL schema exists
- [ ] Test scripts pass (run `test-mcp-security.sh`)
- [ ] Tools list returns expected tools
- [ ] Safe tools execute correctly
- [ ] Dangerous tools blocked properly
- [ ] Botpress integration working
- [ ] Performance acceptable
- [ ] Logs show no errors

---

## Regression Testing

Before merging or deploying major changes:

1. **Run full security test suite:**
   ```bash
   ./test-mcp-security.sh
   ```

2. **Test all tool categories:**
   - GraphQL query tools
   - Entry management tools (search, get by ID/slug)
   - System information tools
   - Plugin management tools
   - Queue/logs tools

3. **Test security configurations:**
   - enableDangerousTools: true/false
   - disabledTools with various tools
   - ipWhitelist with CIDR ranges

4. **Test Botpress integration:**
   - Follow BOTPRESS-STAGING-TEST-CHECKLIST.md
   - Verify all 7 test scenarios pass

---

## Additional Resources

- [BOTPRESS-STAGING-TEST-CHECKLIST.md](BOTPRESS-STAGING-TEST-CHECKLIST.md) - Comprehensive bot testing
- [BOTPRESS-TEST-WORKFLOWS.md](BOTPRESS-TEST-WORKFLOWS.md) - Autonomous node test scenarios
- [DEPLOYMENT.md](DEPLOYMENT.md) - Deployment instructions
- [QUICK-START.md](QUICK-START.md) - Getting started guide
- [FIELD-PRIVACY-GUIDE.md](FIELD-PRIVACY-GUIDE.md) - Privacy and security best practices
