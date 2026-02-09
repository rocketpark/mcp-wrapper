# Post-Deployment Verification Checklist

**Version:** v2.7.0  
**Date:** ___________  
**Deployed By:** ___________

Complete this checklist after deploying MCP Wrapper v2.2-v2.7 to production.

---

## ✅ Core Functionality

### Health & Connectivity

- [ ] **Health Endpoint Responding**
  ```bash
  curl https://yoursite.com/mcp/health
  # Expected: {"status":"healthy","timestamp":"..."}
  ```

- [ ] **Detailed Health Check**
  ```bash
  curl https://yoursite.com/mcp/health?detailed=1 | jq '.'
  ```
  - [ ] Status: "healthy" or "degraded" (not "unhealthy")
  - [ ] Cache: check="ok"
  - [ ] Database: check="ok"
  - [ ] GraphQL Schema: found=true

- [ ] **Metrics Endpoint**
  ```bash
  curl https://yoursite.com/mcp/metrics
  # Expected: Prometheus-format metrics
  ```

### Manifest Generation (v2.7)

- [ ] **Manifest Accessible**
  ```bash
  curl https://yoursite.com/mcp/manifest/MCPSchema | jq '.tools | length'
  # Expected: Number of tools (should be 18+ for JH)
  ```

- [ ] **JSON Schemas Present (v2.7)**
  ```bash
  curl https://yoursite.com/mcp/manifest/MCPSchema | \
    jq '[.tools[] | select(.name | startswith("query_")) | {name, has_input: (.inputSchema != null), has_output: (.outputSchema != null)}] | .[0]'
  ```
  - [ ] has_input: true
  - [ ] has_output: true

- [ ] **Schema Count Correct**
  - Expected tools: _____ (from pre-deployment baseline)
  - Actual tools: _____
  - [ ] Counts match

### JSON-RPC Endpoint

- [ ] **tools/list Method**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}' | jq '.result.tools | length'
  ```
  - [ ] Returns list of tools
  - [ ] No error in response

- [ ] **tools/call Method (Test with safe tool)**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"craft_get_sections","arguments":{}},"id":2}' | jq '.result'
  ```
  - [ ] Returns data successfully
  - [ ] No error in response

---

## ⚡ Performance Features (v2.2)

### Tool Caching

- [ ] **Cache Configuration**
  - [ ] `toolCacheTTL` set in config (default: 300 seconds)
  - [ ] Cache directory exists: `storage/runtime/cache`
  - [ ] Directory writable: `chmod 775`

- [ ] **Cache Working**
  ```bash
  # Make same request twice, second should be faster
  time curl -s https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"query_services","arguments":{"limit":10}},"id":1}' > /dev/null
  
  # Run again (should see ~10-50ms vs 100-500ms)
  time curl -s https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"query_services","arguments":{"limit":10}},"id":1}' > /dev/null
  ```
  - First request time: _____ ms
  - Second request time: _____ ms (should be faster)
  - [ ] Cache working (second request faster)

### Request Logging

- [ ] **Log File Created**
  ```bash
  ls -lh storage/logs/mcp-requests.log
  ```
  - [ ] File exists
  - [ ] File writable

- [ ] **Log Format Correct**
  ```bash
  tail -1 storage/logs/mcp-requests.log | jq '.'
  ```
  - [ ] Valid JSON
  - [ ] Contains: timestamp, schema, method, tool, success, duration_ms
  - [ ] IP anonymized (shows as `xxx.xxx.**.**` or `xxxx:xxxx:****`)

### Connection Pooling

- [ ] **GraphQL Requests Using Keep-Alive**
  ```bash
  # Check web server logs for multiple requests from same connection
  tail -f /var/log/nginx/access.log  # or Apache logs
  # Look for Connection: keep-alive headers
  ```
  - [ ] Confirmed in logs

---

## 📊 Performance Dashboard (v2.6)

### Dashboard Access

- [ ] **CP Utility Available**
  - [ ] Navigate to: /admin/utilities
  - [ ] "MCP Analytics" appears in list
  - [ ] Click opens dashboard

- [ ] **Direct URL Access**
  ```bash
  # Visit in browser (requires CP login)
  open https://yoursite.com/admin/mcp-wrapper/analytics
  ```
  - [ ] Dashboard loads
  - [ ] Shows metrics (may be 0 initially)

### Dashboard Functionality

- [ ] **Summary Cards Display**
  - [ ] Total Requests
  - [ ] Success Rate
  - [ ] Avg Response Time
  - [ ] Cache Hit Rate

- [ ] **Charts Render**
  - [ ] Top Tools chart shows data
  - [ ] Response Time Distribution

- [ ] **Tables Populate**
  - [ ] Slowest Requests (may be empty initially)
  - [ ] Recent Errors (should be empty if no errors)

- [ ] **Filters Work**
  - [ ] Schema selector changes data
  - [ ] Time range selector (1, 7, 30, 90 days)
  - [ ] Refresh button updates

- [ ] **CSV Export**
  ```bash
  curl -O "https://yoursite.com/actions/mcp-wrapper/analytics/export?schemaHandle=MCPSchema&days=7"
  ```
  - [ ] File downloads
  - [ ] Valid CSV format

### Analytics Data

After 10-20 test requests:

- [ ] **Metrics Update**
  - Total Requests: _____ (should be 10-20)
  - Success Rate: _____ % (should be 95-100%)
  - Avg Response Time: _____ ms
  - Cache Hit Rate: _____ % (may be 0-20% initially)

---

## 🔔 Webhooks (v2.5)

### Configuration

- [ ] **Config Present**
  - [ ] `webhooks` array defined in `config/mcpwrapper.php`
  - [ ] URL and secret configured (if using webhooks)
  - [ ] `webhookUseQueue` set to `true`

### Webhook Testing

- [ ] **Console Test Command**
  ```bash
  php craft mcp-wrapper/webhook/test https://your-webhook-url.com your-secret
  ```
  - [ ] Command executes
  - [ ] Shows "Success" or error message

- [ ] **List Webhooks**
  ```bash
  php craft mcp-wrapper/webhook/list
  ```
  - [ ] Shows configured webhooks
  - [ ] Displays filters correctly

### Webhook Delivery

- [ ] **Create Test Entry**
  - [ ] Create/save entry in CP
  - [ ] Check queue log: `tail -f storage/logs/queue.log`
  - [ ] Webhook job appears in logs
  - [ ] Job completes successfully

- [ ] **Webhook Received**
  - [ ] Check webhook endpoint logs
  - [ ] Payload received
  - [ ] HMAC signature valid

- [ ] **Filtering Works (if configured)**
  - [ ] Save entry in filtered section → webhook fires
  - [ ] Save entry in non-filtered section → no webhook
  - [ ] Change entry status → webhook fires if status matches filter

---

## 🧪 Unit Tests (v2.4)

### Test Suite Execution

- [ ] **All Tests Pass**
  ```bash
  cd vendor/rocket-park/mcp-wrapper
  vendor/bin/phpunit tests/Unit
  ```
  - [ ] 33 tests
  - [ ] 72 assertions
  - [ ] All passing (green)
  - [ ] No failures or errors

### Test Coverage

- [ ] **Coverage Report (Optional)**
  ```bash
  vendor/bin/phpunit tests/Unit --coverage-html coverage
  open coverage/index.html
  ```
  - [ ] Coverage report generated
  - [ ] Core services covered (ToolCache, RequestLogger, Webhook)

---

## 🔒 Security Verification

### Rate Limiting

- [ ] **Rate Limit Enabled**
  - [ ] `security.enableRateLimit = true` in config
  - [ ] `security.rateLimit` and `rateLimitWindow` configured

- [ ] **Rate Limit Working**
  ```bash
  # Make 101 requests rapidly (if limit is 100)
  for i in {1..101}; do
    curl -s -o /dev/null -w "%{http_code}\n" https://yoursite.com/mcp/MCPSchema \
      -H "Content-Type: application/json" \
      -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'
  done
  # Last request should return 429
  ```
  - [ ] First 100 requests: 200 OK
  - [ ] 101st request: 429 Too Many Requests

### Dangerous Tools

- [ ] **Dangerous Tools Disabled**
  - [ ] `security.enableDangerousTools = false` in production config
  - [ ] Manifest doesn't include dangerous tools

- [ ] **Verify No Dangerous Tools**
  ```bash
  curl https://yoursite.com/mcp/manifest/MCPSchema | \
    jq '[.tools[] | select(.dangerous == true)] | length'
  # Expected: 0
  ```
  - Count: _____ (should be 0)

### IP Whitelisting (if enabled)

- [ ] **Whitelist Configured**
  - [ ] `security.ipWhitelist` array populated

- [ ] **Whitelist Working**
  - [ ] Request from allowed IP: Success (200)
  - [ ] Request from denied IP: Forbidden (403)

---

## 📝 Logging & Monitoring

### Log Files

- [ ] **Log Files Exist**
  ```bash
  ls -lh storage/logs/mcp-requests.log
  ls -lh storage/logs/queue.log  # If webhooks enabled
  ```
  - [ ] Files exist
  - [ ] Writable
  - [ ] Rotating properly (not too large)

- [ ] **Log Content Valid**
  ```bash
  tail -10 storage/logs/mcp-requests.log | jq '.'
  ```
  - [ ] Valid JSON on each line
  - [ ] Contains expected fields
  - [ ] No sensitive data exposed

### Error Monitoring

- [ ] **No Errors in Logs**
  ```bash
  grep -i "error" storage/logs/web.log | tail -20
  ```
  - [ ] No MCP-related errors
  - [ ] If errors exist, documented: _______________

- [ ] **Dashboard Shows No Errors**
  - [ ] Recent Errors table: Empty
  - [ ] Success Rate: 95-100%

---

## 🎯 Functional Testing

### Test Each Tool Type

- [ ] **Auto-Generated GraphQL Tool**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"query_services","arguments":{"limit":5}},"id":1}' | jq '.result.entries | length'
  ```
  - [ ] Returns entries
  - [ ] Count matches limit
  - [ ] Has inputSchema and outputSchema (v2.7)

- [ ] **Manual Tool**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"craft_get_sections","arguments":{}},"id":1}' | jq '.result'
  ```
  - [ ] Returns section data
  - [ ] Has enhanced annotations (v2.3)

### Error Handling

- [ ] **Invalid Tool Name**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"invalid_tool","arguments":{}},"id":1}' | jq '.error'
  ```
  - [ ] Returns proper JSON-RPC error
  - [ ] Error code: -32603 (Internal error)
  - [ ] Error message not exposing sensitive info

- [ ] **Invalid Arguments**
  ```bash
  curl https://yoursite.com/mcp/MCPSchema \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"craft_get_entry_by_id","arguments":{"id":"not-a-number"}},"id":1}' | jq '.error'
  ```
  - [ ] Returns proper error
  - [ ] Helpful error message

---

## 📈 Performance Baseline

After 1 hour of normal operation, record baseline metrics:

### Dashboard Metrics
- Total Requests: _____
- Success Rate: _____ %
- Avg Response Time: _____ ms
- Cache Hit Rate: _____ %

### Top 5 Tools (by usage)
1. _______________ (_____ calls)
2. _______________ (_____ calls)
3. _______________ (_____ calls)
4. _______________ (_____ calls)
5. _______________ (_____ calls)

### System Performance
- Server CPU: _____ %
- Server Memory: _____ MB
- Database Connections: _____

---

## ✍️ Sign-Off

- [ ] All critical checks passed
- [ ] Performance within acceptable ranges
- [ ] No blocking issues identified
- [ ] Team notified of deployment
- [ ] Monitoring alerts configured

**Issues Found:**
1. _________________________________
2. _________________________________
3. _________________________________

**Follow-Up Actions:**
1. _________________________________
2. _________________________________
3. _________________________________

---

**Verified By:** ___________  
**Date:** ___________  
**Time:** ___________  
**Signature:** ___________

---

## 📞 Next Steps

1. **Monitor for 24 hours**
   - Check dashboard twice daily
   - Review error logs
   - Verify cache hit rate increasing

2. **Export Baseline Analytics**
   ```bash
   # After 1 week
   curl "https://yoursite.com/actions/mcp-wrapper/analytics/export?schemaHandle=MCPSchema&days=7" -O
   ```

3. **Schedule Regular Reviews**
   - Weekly: Review dashboard metrics
   - Monthly: Export analytics CSV for trending
   - Quarterly: Rotate webhook secrets

4. **Document Any Issues**
   - Add to internal knowledge base
   - Report bugs to Rocket Park LLC
   - Share learnings with team
