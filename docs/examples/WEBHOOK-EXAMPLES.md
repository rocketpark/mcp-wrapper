# Webhook Examples

Example webhook receivers for testing MCP Wrapper webhooks.

## Simple PHP Receiver

```php
<?php
// webhook-receiver.php
// Test with: php -S localhost:8000 webhook-receiver.php

$payload = file_get_contents('php://input');
$headers = getallheaders();

// Verify signature
$secret = getenv('MCP_WEBHOOK_SECRET');
if ($secret && isset($headers['X-MCP-Webhook-Signature'])) {
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expectedSignature, $headers['X-MCP-Webhook-Signature'])) {
        http_response_code(401);
        die('Invalid signature');
    }
}

$data = json_decode($payload, true);

// Log webhook
file_put_contents('webhook-log.json', $payload . "\n", FILE_APPEND);

echo json_encode([
    'received' => true,
    'event' => $data['event'] ?? 'unknown',
    'timestamp' => date('c')
]);
```

## Node.js Express Receiver

```javascript
// webhook-server.js
// Run with: node webhook-server.js

const express = require('express');
const crypto = require('crypto');
const app = express();

app.use(express.json());

app.post('/webhook', (req, res) => {
    const payload = JSON.stringify(req.body);
    const signature = req.headers['x-mcp-webhook-signature'];
    const secret = process.env.MCP_WEBHOOK_SECRET;
    
    // Verify signature
    if (secret && signature) {
        const expectedSignature = 'sha256=' + crypto
            .createHmac('sha256', secret)
            .update(payload)
            .digest('hex');
        
        if (signature !== expectedSignature) {
            return res.status(401).json({ error: 'Invalid signature' });
        }
    }
    
    console.log('Webhook received:', req.body);
    
    res.json({
        received: true,
        event: req.body.event,
        timestamp: new Date().toISOString()
    });
});

app.listen(3000, () => {
    console.log('Webhook receiver running on http://localhost:3000');
});
```

## Testing Webhooks

```bash
# Test webhook delivery
php craft mcp-wrapper/webhook/test http://localhost:8000/webhook secretkey123

# List configured webhooks
php craft mcp-wrapper/webhook/list

# Monitor webhook logs
tail -f storage/logs/mcp-wrapper.log | grep webhook
```

## Botpress Integration

```php
// config/mcpwrapper.php
'webhooks' => [
    [
        'url' => 'https://webhook.botpress.cloud/abc123',
        'events' => ['entry.saved'],
        'sections' => ['news', 'projects'],
        'statuses' => ['live'],
    ],
],
```

Botpress will receive:
```json
{
    "event": "entry.saved",
    "timestamp": "2026-02-02T10:30:00+00:00",
    "entry": {
        "id": 123,
        "title": "New Project Launched",
        "slug": "new-project",
        "sectionHandle": "projects",
        "typeHandle": "project",
        "status": "live",
        "postDate": "2026-02-02T10:00:00+00:00",
        "url": "https://site.com/projects/new-project"
    },
    "changedAttributes": ["title", "slug"]
}
```

## Slack Notification

Use a Slack app webhook URL:

```php
'webhooks' => [
    [
        'url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
        'events' => ['entry.saved', 'entry.deleted'],
        'sections' => ['news'],
    ],
],
```

For formatted Slack messages, create a proxy endpoint that transforms the payload:

```php
// slack-proxy.php
$payload = json_decode(file_get_contents('php://input'), true);

$slackMessage = [
    'text' => "Content Update",
    'blocks' => [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*{$payload['event']}*\n{$payload['entry']['title']}"
            ]
        ],
        [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "Section: {$payload['entry']['sectionHandle']} | Status: {$payload['entry']['status']}"
                ]
            ]
        ]
    ]
];

$ch = curl_init(getenv('SLACK_WEBHOOK_URL'));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_exec($ch);
```
