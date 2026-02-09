# MCP Wrapper - Production Deployment Guide

**Version:** v2.7.0  
**Branch:** feature/mcp-improvements  
**Date:** February 2, 2026

This guide covers deploying all improvements from v2.2 through v2.7 to production.

---

## 🎯 What's Being Deployed

### v2.2.0 - Performance Optimization
- Tool result caching (5-minute TTL, MD5 key hashing)
- Request logging with privacy features (IP anonymization, arg hashing)
- Connection pooling for GraphQL requests (Guzzle keep-alive)

### v2.3.0 - MCP Spec Compliance
- Output schemas for all manual tools
- Enhanced annotations (costHint, confidentialityHint, destructiveHint)
- Better AI decision-making with complete metadata

### v2.4.0 - Unit Test Suite
- 33 tests with 72 assertions
- PHPUnit 10.5 infrastructure
- Coverage: ToolCache, RequestLogger, ToolAttribute, Webhook services

### v2.5.0 - Webhook Support
- HTTP POST notifications on entry save/delete
- HMAC SHA-256 signatures for security
- Event/section/status filtering
- Async delivery via queue (doesn't block saves)

### v2.6.0 - Performance Dashboard
- Visual analytics dashboard in Craft CP
- Real-time metrics (requests, success rate, response time, cache hit rate)
- CSV export, multi-schema support
- Access via Utilities → MCP Analytics

### v2.7.0 - JSON Schema Support
- Complete inputSchema and outputSchema for all 18 auto-generated tools
- Enhanced type mapping (Craft fields → JSON Schema)
- Better AI understanding with detailed parameter descriptions

---

## 📋 Pre-Deployment Checklist

### 1. Review Configuration Changes

The following config files need updates in production:

#### `config/mcpwrapper.php`

```php
return [
    // Schemas (REQUIRED - update tokens)
    'schemas' => [
        'MCPSchema' => getenv('MCP_GQLSCHEMA_TOKEN'),
    ],
    
    // NEW v2.2: Tool caching
    'toolCacheTTL' => 300,  // 5 minutes (0 = disabled)
    'toolCacheExclude' => [], // Tools to never cache
    
    // NEW v2.5: Webhooks (OPTIONAL)
    'webhooks' => [
        [
            'url' => getenv('WEBHOOK_URL'),  // e.g., Botpress endpoint
            'secret' => getenv('WEBHOOK_SECRET'),  // For HMAC signatures
            'events' => ['entry.saved', 'entry.deleted'],
            'sections' => [], // Empty = all sections
            'statuses' => [], // Empty = all statuses
            'timeout' => 5,
        ],
    ],
    'webhookUseQueue' => true,  // Async delivery (recommended)
    
    // Security settings (review for production)
    'security' => [
        'enableRateLimit' => true,
        'rateLimit' => 100,  // Requests per window
        'rateLimitWindow' => 60,  // Seconds
        'enableDangerousTools' => false,  // Disable in production
        'disabledTools' => [],
        'ipWhitelist' => [
            // '203.0.113.0/24',  // Add if needed
        ],
    ],
];
```

### 2. Environment Variables

Add to `.env` (production):

```bash
# GraphQL Schema Token (REQUIRED)
MCP_GQLSCHEMA_TOKEN="your-production-token-here"

# Webhooks (OPTIONAL)
WEBHOOK_URL="https://your-bot.example.com/webhooks/mcp"
WEBHOOK_SECRET="generate-strong-random-secret"
```

### 3. Database & Project Config

Ensure GraphQL schema permissions are synced:

```bash
php craft project-config/apply
```

This ensures the GraphQL schema scope (which sections are allowed) matches what's in `config/project/graphql/schemas/{uid}.yaml`.

### 4. File Permissions

Ensure storage directories are writable:

```bash
chmod -R 775 storage/runtime/mcp
chmod -R 775 storage/logs
```

---

## 🚀 Deployment Steps

### Step 1: Backup Current State

```bash
# Backup database
php craft db/backup

# Backup current vendor code
cp -r vendor/rocket-park/mcp-wrapper vendor/rocket-park/mcp-wrapper.backup

# Backup config
cp config/mcpwrapper.php config/mcpwrapper.php.backup
```

### Step 2: Update Code

```bash
# Update composer package
composer update rocket-park/mcp-wrapper --no-cache

# Verify version (should show commit e02ebc1 or later)
composer show rocket-park/mcp-wrapper
```

### Step 3: Update Configuration

```bash
# Copy example config to review new settings
cp vendor/rocket-park/mcp-wrapper/config/mcpwrapper.php.example config/mcpwrapper.php.example

# Edit production config
nano config/mcpwrapper.php

# Add new settings from v2.2 (caching), v2.5 (webhooks)
```

### Step 4: Clear Caches

```bash
# Clear Craft caches
php craft clear-caches/all

# Clear MCP manifest cache
rm -rf storage/runtime/mcp/manifest-*.json

# Clear compiled templates
php craft clear-caches/compiled-templates
```

### Step 5: Rebuild Project Config

```bash
# Sync DB with project config YAML files
php craft project-config/apply

# Verify GraphQL schema permissions
php craft graphql/list-schemas
```

### Step 6: Test Webhooks (if enabled)

```bash
# Test webhook delivery
php craft mcp-wrapper/webhook/test https://your-webhook-url.com your-secret

# List configured webhooks
php craft mcp-wrapper/webhook/list
```

### Step 7: Verify MCP Endpoints

```bash
# Test health check
curl http://yoursite.com/mcp/health?detailed=1

# Test manifest generation
curl http://yoursite.com/mcp/manifest/MCPSchema | jq '.tools | length'

# Verify schemas are present
curl http://yoursite.com/mcp/manifest/MCPSchema | jq '.tools[0] | {name, has_input: (.inputSchema != null), has_output: (.outputSchema != null)}'
```

---

## ✅ Post-Deployment Verification

See [VERIFICATION.md](./VERIFICATION.md) for detailed verification checklist.

**Quick Checks:**

1. ✅ Health endpoint returns 200: `curl /mcp/health`
2. ✅ Manifest includes all expected tools
3. ✅ JSON schemas present on auto-generated tools
4. ✅ Dashboard accessible: `/admin/utilities/mcp-analytics`
5. ✅ Cache hit rate increasing (check dashboard after 10+ requests)
6. ✅ Webhooks firing (if enabled, check logs: `tail -f storage/logs/queue.log`)

---

## 🔧 Troubleshooting

### Issue: Manifest shows 0 or wrong number of tools

**Cause:** GraphQL schema permissions not synced  
**Fix:**
```bash
php craft project-config/apply
rm -rf storage/runtime/mcp/manifest-*.json
curl /mcp/manifest/MCPSchema?force=1
```

### Issue: Webhooks not firing

**Check:**
1. Config: `webhooks` array in `mcpwrapper.php`
2. Queue: `php craft queue/run`
3. Logs: `tail -f storage/logs/queue.log`

**Test manually:**
```bash
php craft mcp-wrapper/webhook/test https://your-url.com your-secret
```

### Issue: Dashboard shows "No data"

**Cause:** No requests logged yet  
**Fix:** Make some MCP requests first:
```bash
curl http://yoursite.com/mcp/MCPSchema \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'
```

### Issue: Cache not working

**Check:**
1. Config: `toolCacheTTL` > 0 in `mcpwrapper.php`
2. Permissions: `chmod -R 775 storage/runtime/cache`
3. Dashboard: Cache hit rate should increase with repeated queries

---

## 📊 Performance Expectations

After deployment, you should see:

- **Response times:** 50-100ms for cached queries (vs 200-500ms uncached)
- **Cache hit rate:** 40-60% after warm-up period (30+ minutes)
- **Success rate:** 95%+ (check dashboard)
- **Webhook latency:** <100ms for queue dispatch (delivery happens async)

---

## 🔐 Security Notes

### Production Hardening

1. **Disable Dangerous Tools:**
   ```php
   'security' => [
       'enableDangerousTools' => false,
   ]
   ```

2. **Enable Rate Limiting:**
   ```php
   'enableRateLimit' => true,
   'rateLimit' => 100,  // Adjust based on usage
   ```

3. **IP Whitelisting (Optional):**
   ```php
   'ipWhitelist' => [
       '203.0.113.0/24',  // Your office
       '198.51.100.0/24', // Your bot server
   ]
   ```

4. **Webhook Secrets:**
   - Use strong random secrets (32+ characters)
   - Store in `.env`, never in code
   - Rotate periodically (every 90 days)

### Monitoring

1. **Request Logs:** `storage/logs/mcp-requests.log`
   - Monitor for unusual patterns
   - Check error rates daily

2. **Dashboard:** `/admin/utilities/mcp-analytics`
   - Review weekly
   - Export CSV for trend analysis

3. **Health Checks:**
   - Add to monitoring: `/mcp/health?detailed=1`
   - Alert if returns 503

---

## 📝 Rollback Plan

If issues occur, rollback in reverse order:

```bash
# 1. Stop processing
# (Disable webhook in config or take site offline)

# 2. Restore code
rm -rf vendor/rocket-park/mcp-wrapper
mv vendor/rocket-park/mcp-wrapper.backup vendor/rocket-park/mcp-wrapper

# 3. Restore config
cp config/mcpwrapper.php.backup config/mcpwrapper.php

# 4. Restore database (if needed)
php craft db/restore /path/to/backup.sql

# 5. Clear caches
php craft clear-caches/all
rm -rf storage/runtime/mcp/manifest-*.json

# 6. Verify
curl /mcp/health
```

---

## 🎓 Training Notes

For team members using the new features:

### Performance Dashboard
- **Access:** Utilities → MCP Analytics
- **Use:** Monitor API usage, identify slow queries, track errors
- **Export:** Click "Export CSV" for detailed analysis

### Webhooks
- **Test:** `php craft mcp-wrapper/webhook/test <url> [secret]`
- **List:** `php craft mcp-wrapper/webhook/list`
- **Logs:** Check `storage/logs/queue.log` for delivery status

### JSON Schemas
- **View:** `/mcp/manifest/MCPSchema` (look for `inputSchema` and `outputSchema`)
- **Benefit:** Better AI understanding, type safety, self-documenting API

---

## 📞 Support

For deployment issues:

1. Check logs: `storage/logs/web.log`, `storage/logs/queue.log`
2. Review [VERIFICATION.md](./VERIFICATION.md)
3. Contact: Rocket Park LLC

---

**Next Steps After Deployment:**

1. Monitor dashboard for first 24 hours
2. Review webhook delivery logs (if enabled)
3. Check cache hit rates after warm-up (1-2 hours)
4. Export analytics CSV after 1 week for baseline metrics
