<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

/**
 * Webhook Service
 * 
 * Sends HTTP POST notifications to configured webhooks when content changes.
 * Supports entry save/delete events with automatic cache invalidation.
 * 
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.5.0
 */
class WebhookService extends Component
{
    /**
     * Send webhook notification for an entry event
     * 
     * @param string $event Event type (entry.saved, entry.deleted)
     * @param Entry $entry The affected entry
     * @param array $changedAttributes Changed attributes (for updates)
     * @return void
     */
    public function sendEntryWebhook(string $event, Entry $entry, array $changedAttributes = []): void
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $webhooks = $config['webhooks'] ?? [];
        
        // Skip if no webhooks configured
        if (empty($webhooks)) {
            return;
        }
        
        // Build payload
        $payload = [
            'event' => $event,
            'timestamp' => date('c'),
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'sectionHandle' => $entry->section->handle ?? null,
                'typeHandle' => $entry->type->handle ?? null,
                'status' => $entry->status,
                'postDate' => $entry->postDate?->format('c'),
                'url' => $entry->url,
            ],
            'changedAttributes' => array_keys($changedAttributes),
        ];
        
        // Send to each configured webhook
        foreach ($webhooks as $webhookConfig) {
            $this->deliverWebhook($webhookConfig, $payload);
        }
    }
    
    /**
     * Deliver webhook to a single endpoint
     * 
     * @param array $config Webhook configuration
     * @param array $payload Event data
     * @return bool Success status
     */
    private function deliverWebhook(array $config, array $payload): bool
    {
        $url = $config['url'] ?? null;
        if (!$url) {
            Craft::warning('Webhook missing URL', 'mcp-wrapper');
            return false;
        }
        
        // Check if event matches filters
        if (!$this->shouldSendWebhook($config, $payload)) {
            return false;
        }
        
        try {
            $client = new Client([
                'timeout' => $config['timeout'] ?? 5,
                'connect_timeout' => 3,
                'http_errors' => false,
            ]);
            
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'MCP-Wrapper-Webhook/2.5.0',
            ];
            
            // Add secret header if configured
            if (!empty($config['secret'])) {
                $headers['X-MCP-Webhook-Secret'] = $config['secret'];
            }
            
            // Add signature if configured
            if (!empty($config['secret'])) {
                $signature = hash_hmac('sha256', json_encode($payload), $config['secret']);
                $headers['X-MCP-Webhook-Signature'] = 'sha256=' . $signature;
            }
            
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $payload,
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                Craft::info("Webhook delivered: {$url} ({$statusCode})", 'mcp-wrapper');
                return true;
            } else {
                Craft::warning("Webhook failed: {$url} ({$statusCode})", 'mcp-wrapper');
                return false;
            }
            
        } catch (GuzzleException $e) {
            Craft::error("Webhook error: {$url} - {$e->getMessage()}", 'mcp-wrapper');
            return false;
        }
    }
    
    /**
     * Check if webhook should be sent based on filters
     * 
     * @param array $config Webhook configuration
     * @param array $payload Event data
     * @return bool Whether to send webhook
     */
    private function shouldSendWebhook(array $config, array $payload): bool
    {
        // Check event filter
        $events = $config['events'] ?? [];
        if (!empty($events) && !in_array($payload['event'], $events, true)) {
            return false;
        }
        
        // Check section filter
        $sections = $config['sections'] ?? [];
        if (!empty($sections)) {
            $sectionHandle = $payload['entry']['sectionHandle'] ?? null;
            if (!in_array($sectionHandle, $sections, true)) {
                return false;
            }
        }
        
        // Check status filter
        $statuses = $config['statuses'] ?? [];
        if (!empty($statuses)) {
            $status = $payload['entry']['status'] ?? null;
            if (!in_array($status, $statuses, true)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Queue webhook delivery for async processing
     * 
     * @param string $event Event type
     * @param Entry $entry The affected entry
     * @param array $changedAttributes Changed attributes
     * @return void
     */
    public function queueWebhook(string $event, Entry $entry, array $changedAttributes = []): void
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $useQueue = $config['webhookUseQueue'] ?? true;
        
        if ($useQueue) {
            // Queue for async delivery
            Craft::$app->queue->push(new \rocketpark\mcpwrapper\queue\jobs\DeliverWebhookJob([
                'event' => $event,
                'entryId' => $entry->id,
                'changedAttributes' => $changedAttributes,
            ]));
            
            Craft::info("Webhook queued: {$event} for entry #{$entry->id}", 'mcp-wrapper');
        } else {
            // Immediate delivery
            $this->sendEntryWebhook($event, $entry, $changedAttributes);
        }
    }
    
    /**
     * Test webhook delivery
     * 
     * @param string $url Webhook URL
     * @param string|null $secret Optional secret for signature
     * @return array Test result
     */
    public function testWebhook(string $url, ?string $secret = null): array
    {
        $payload = [
            'event' => 'webhook.test',
            'timestamp' => date('c'),
            'test' => true,
            'message' => 'This is a test webhook from MCP Wrapper',
        ];
        
        $config = [
            'url' => $url,
            'secret' => $secret,
            'timeout' => 5,
        ];
        
        $success = $this->deliverWebhook($config, $payload);
        
        return [
            'success' => $success,
            'url' => $url,
            'timestamp' => date('c'),
        ];
    }
}
