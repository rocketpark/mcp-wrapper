<?php

namespace rocketpark\mcpwrapper\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use rocketpark\mcpwrapper\services\WebhookService;
use yii\console\ExitCode;

/**
 * Webhook management commands
 * 
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.5.0
 */
class WebhookController extends Controller
{
    /**
     * Test webhook delivery
     * 
     * @param string $url Webhook URL to test
     * @param string|null $secret Optional secret for signature
     * @return int
     */
    public function actionTest(string $url, ?string $secret = null): int
    {
        $this->stdout("Testing webhook delivery...\n", Console::FG_YELLOW);
        $this->stdout("URL: {$url}\n");
        
        if ($secret) {
            $this->stdout("Secret: " . str_repeat('*', strlen($secret)) . "\n");
        }
        
        /** @var WebhookService $webhookService */
        $webhookService = Craft::$app->getModule('mcp-wrapper')->get('webhook');
        
        $result = $webhookService->testWebhook($url, $secret);
        
        if ($result['success']) {
            $this->stdout("\n✓ Webhook delivered successfully!\n", Console::FG_GREEN);
            $this->stdout("Timestamp: {$result['timestamp']}\n");
            return ExitCode::OK;
        } else {
            $this->stderr("\n✗ Webhook delivery failed\n", Console::FG_RED);
            $this->stderr("Check storage/logs/mcp-wrapper.log for details\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * List configured webhooks
     * 
     * @return int
     */
    public function actionList(): int
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $webhooks = $config['webhooks'] ?? [];
        
        if (empty($webhooks)) {
            $this->stdout("No webhooks configured\n", Console::FG_YELLOW);
            $this->stdout("Add webhooks to config/mcpwrapper.php\n");
            return ExitCode::OK;
        }
        
        $this->stdout("Configured Webhooks:\n\n", Console::FG_GREEN);
        
        foreach ($webhooks as $index => $webhook) {
            $this->stdout("#{$index}:\n", Console::BOLD);
            $this->stdout("  URL: {$webhook['url']}\n");
            
            if (!empty($webhook['events'])) {
                $this->stdout("  Events: " . implode(', ', $webhook['events']) . "\n");
            } else {
                $this->stdout("  Events: all\n");
            }
            
            if (!empty($webhook['sections'])) {
                $this->stdout("  Sections: " . implode(', ', $webhook['sections']) . "\n");
            }
            
            if (!empty($webhook['statuses'])) {
                $this->stdout("  Statuses: " . implode(', ', $webhook['statuses']) . "\n");
            }
            
            if (!empty($webhook['secret'])) {
                $this->stdout("  Secret: configured ✓\n");
            }
            
            $this->stdout("\n");
        }
        
        return ExitCode::OK;
    }
}
