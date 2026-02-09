<?php

namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Webhook Controller
 *
 * Handles incoming webhooks to trigger KB sync and other automated tasks.
 */
class WebhookController extends Controller
{
    /**
     * Allow anonymous access to webhook endpoints
     */
    protected array|int|bool $allowAnonymous = ['trigger-kb-sync'];

    /**
     * Disable CSRF for webhook endpoints
     */
    public $enableCsrfValidation = false;

    /**
     * Trigger Botpress KB sync via GitHub Actions
     *
     * POST /actions/mcp-wrapper/webhook/trigger-kb-sync
     *
     * Requires:
     * - X-Webhook-Secret header matching WEBHOOK_SECRET env var
     * - GITHUB_TOKEN env var for GitHub API access
     * - GITHUB_REPO env var (e.g., "owner/repo")
     */
    public function actionTriggerKbSync(): Response
    {
        $request = Craft::$app->getRequest();

        // Verify webhook secret
        $expectedSecret = getenv('WEBHOOK_SECRET') ?: Craft::$app->getConfig()->getConfigFromFile('mcpwrapper')['webhookSecret'] ?? null;
        $providedSecret = $request->getHeaders()->get('X-Webhook-Secret');

        if ($expectedSecret && $providedSecret !== $expectedSecret) {
            Craft::warning('KB sync webhook called with invalid secret', 'mcp-wrapper');
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid webhook secret',
            ])->setStatusCode(401);
        }

        // Get GitHub config
        $githubToken = getenv('GITHUB_TOKEN');
        $githubRepo = getenv('GITHUB_REPO') ?: Craft::$app->getConfig()->getConfigFromFile('mcpwrapper')['githubRepo'] ?? null;

        if (!$githubToken || !$githubRepo) {
            Craft::error('KB sync webhook missing GITHUB_TOKEN or GITHUB_REPO', 'mcp-wrapper');
            return $this->asJson([
                'success' => false,
                'error' => 'GitHub configuration missing',
            ])->setStatusCode(500);
        }

        // Trigger GitHub Actions workflow
        $result = $this->triggerGitHubWorkflow($githubToken, $githubRepo, 'sync-kb.yml');

        if ($result['success']) {
            Craft::info('KB sync workflow triggered successfully', 'mcp-wrapper');
            return $this->asJson([
                'success' => true,
                'message' => 'KB sync workflow triggered',
            ]);
        } else {
            Craft::error('Failed to trigger KB sync workflow: ' . $result['error'], 'mcp-wrapper');
            return $this->asJson([
                'success' => false,
                'error' => $result['error'],
            ])->setStatusCode(500);
        }
    }

    /**
     * Trigger a GitHub Actions workflow via API
     */
    private function triggerGitHubWorkflow(string $token, string $repo, string $workflow): array
    {
        $url = "https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: MCP-Wrapper-Webhook',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'ref' => 'main',  // Branch to run on
                'inputs' => [
                    'dry_run' => 'false',
                ],
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL error: {$error}"];
        }

        // 204 = success (no content), 200 = success with content
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        }

        $responseData = json_decode($response, true);
        $message = $responseData['message'] ?? "HTTP {$httpCode}";

        return ['success' => false, 'error' => $message];
    }
}
