<?php

namespace rocketpark\mcpwrapper\queue\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use rocketpark\mcpwrapper\services\WebhookService;

/**
 * Deliver Webhook Job
 * 
 * Async queue job for delivering webhooks without blocking entry save.
 * Handles retries and failure logging.
 * 
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.5.0
 */
class DeliverWebhookJob extends BaseJob
{
    /**
     * @var string Event type (entry.saved, entry.deleted)
     */
    public string $event;
    
    /**
     * @var int Entry ID
     */
    public int $entryId;
    
    /**
     * @var array Changed attributes
     */
    public array $changedAttributes = [];
    
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        // Load entry
        $entry = Entry::find()
            ->id($this->entryId)
            ->status(null)
            ->one();
        
        if (!$entry) {
            Craft::warning("Webhook job: Entry #{$this->entryId} not found", 'mcp-wrapper');
            return;
        }
        
        // Get webhook service
        /** @var WebhookService $webhookService */
        $webhookService = Craft::$app->getModule('mcp-wrapper')->get('webhook');
        
        // Send webhook
        $webhookService->sendEntryWebhook($this->event, $entry, $this->changedAttributes);
    }
    
    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return "Deliver MCP webhook for entry #{$this->entryId}";
    }
}
