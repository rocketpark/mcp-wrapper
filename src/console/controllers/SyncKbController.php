<?php

namespace rocketpark\mcpwrapper\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Sync content to Botpress Knowledge Base
 *
 * Usage:
 *   php craft mcp-wrapper/sync-kb           # Sync all KBs
 *   php craft mcp-wrapper/sync-kb/services  # Sync services only
 *   php craft mcp-wrapper/sync-kb/industries
 *   php craft mcp-wrapper/sync-kb/offices
 *   php craft mcp-wrapper/sync-kb/leadership
 *
 * Environment variables:
 *   BOTPRESS_PAT    - Botpress Personal Access Token
 *   BOTPRESS_BOT_ID - Bot ID
 *
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.8.0
 */
class SyncKbController extends Controller
{
    /**
     * @var bool Force sync even if no changes detected
     */
    public bool $force = false;

    /**
     * @var bool Dry run - show what would be synced without uploading
     */
    public bool $dryRun = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'force',
            'dryRun',
        ]);
    }

    /**
     * Sync all knowledge bases
     */
    public function actionIndex(): int
    {
        $this->stdout("Syncing all Knowledge Bases to Botpress...\n\n", Console::FG_CYAN);

        $results = [
            'services' => $this->syncServices(),
            'industries' => $this->syncIndustries(),
            'offices' => $this->syncOffices(),
            'leadership' => $this->syncLeadership(),
        ];

        $this->stdout("\n=== Summary ===\n", Console::BOLD);
        foreach ($results as $kb => $result) {
            $status = $result ? '✓' : '✗';
            $color = $result ? Console::FG_GREEN : Console::FG_RED;
            $this->stdout("{$status} {$kb}\n", $color);
        }

        return array_sum($results) === count($results) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sync services to KB
     */
    public function actionServices(): int
    {
        return $this->syncServices() ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sync industries to KB
     */
    public function actionIndustries(): int
    {
        return $this->syncIndustries() ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sync offices to KB
     */
    public function actionOffices(): int
    {
        return $this->syncOffices() ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sync leadership to KB
     */
    public function actionLeadership(): int
    {
        return $this->syncLeadership() ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sync services KB
     */
    private function syncServices(): bool
    {
        $this->stdout("Syncing Services...\n", Console::FG_YELLOW);

        $section = Craft::$app->getSections()->getSectionByHandle('services');
        if (!$section) {
            $this->stderr("  Services section not found\n", Console::FG_RED);
            return false;
        }

        $entries = Entry::find()
            ->section('services')
            ->status('live')
            ->all();

        $this->stdout("  Found " . count($entries) . " services\n");

        if ($this->dryRun) {
            $this->stdout("  [DRY RUN] Would upload to Botpress\n", Console::FG_CYAN);
            return true;
        }

        $content = $this->formatServicesForKb($entries);
        return $this->uploadToBotpress('services', $content, 'jh-services.txt');
    }

    /**
     * Sync industries KB
     */
    private function syncIndustries(): bool
    {
        $this->stdout("Syncing Industries...\n", Console::FG_YELLOW);

        $entries = Entry::find()
            ->section('industries')
            ->status('live')
            ->all();

        $this->stdout("  Found " . count($entries) . " industries\n");

        if ($this->dryRun) {
            $this->stdout("  [DRY RUN] Would upload to Botpress\n", Console::FG_CYAN);
            return true;
        }

        $content = $this->formatIndustriesForKb($entries);
        return $this->uploadToBotpress('industries', $content, 'jh-industries.txt');
    }

    /**
     * Sync offices KB
     */
    private function syncOffices(): bool
    {
        $this->stdout("Syncing Offices...\n", Console::FG_YELLOW);

        $entries = Entry::find()
            ->section('officeLocations')
            ->status('live')
            ->all();

        $this->stdout("  Found " . count($entries) . " offices\n");

        if ($this->dryRun) {
            $this->stdout("  [DRY RUN] Would upload to Botpress\n", Console::FG_CYAN);
            return true;
        }

        $content = $this->formatOfficesForKb($entries);
        return $this->uploadToBotpress('offices', $content, 'jh-offices.txt');
    }

    /**
     * Sync leadership KB
     */
    private function syncLeadership(): bool
    {
        $this->stdout("Syncing Leadership...\n", Console::FG_YELLOW);

        $entries = Entry::find()
            ->section('experts')
            ->relatedTo(['field' => 'executiveLeadership', 'value' => true])
            ->status('live')
            ->all();

        // If no relation field, try getting by leadership category
        if (empty($entries)) {
            $entries = Entry::find()
                ->section('experts')
                ->status('live')
                ->limit(50)
                ->all();
            // Filter for leadership - adjust based on actual field structure
        }

        $this->stdout("  Found " . count($entries) . " leaders\n");

        if ($this->dryRun) {
            $this->stdout("  [DRY RUN] Would upload to Botpress\n", Console::FG_CYAN);
            return true;
        }

        $content = $this->formatLeadershipForKb($entries);
        return $this->uploadToBotpress('leadership', $content, 'jh-leadership.txt');
    }

    /**
     * Format services entries for KB
     */
    private function formatServicesForKb(array $entries): string
    {
        $lines = [
            "JENSEN HUGHES SERVICES",
            "======================",
            "",
            "Jensen Hughes provides comprehensive fire protection, security, and risk consulting services.",
            "",
        ];

        foreach ($entries as $entry) {
            $lines[] = "## " . $entry->title;

            if ($entry->serviceDescription ?? null) {
                $lines[] = strip_tags($entry->serviceDescription);
            }

            $url = $entry->getUrl();
            if ($url) {
                $lines[] = "Learn more: {$url}";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Format industries entries for KB
     */
    private function formatIndustriesForKb(array $entries): string
    {
        $lines = [
            "JENSEN HUGHES INDUSTRIES",
            "========================",
            "",
            "Jensen Hughes serves a wide range of industries with specialized expertise.",
            "",
        ];

        foreach ($entries as $entry) {
            $lines[] = "## " . $entry->title;

            if ($entry->industryDescription ?? null) {
                $lines[] = strip_tags($entry->industryDescription);
            }

            $url = $entry->getUrl();
            if ($url) {
                $lines[] = "Learn more: {$url}";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Format offices entries for KB
     */
    private function formatOfficesForKb(array $entries): string
    {
        $lines = [
            "JENSEN HUGHES OFFICE LOCATIONS",
            "==============================",
            "",
            "Jensen Hughes has offices worldwide. Find your nearest location:",
            "",
        ];

        foreach ($entries as $entry) {
            $lines[] = "## " . $entry->title;

            $address = [];
            if ($entry->streetAddress ?? null) $address[] = $entry->streetAddress;
            if ($entry->city ?? null) $address[] = $entry->city;
            if ($entry->state ?? null) $address[] = $entry->state;
            if ($entry->postalCode ?? null) $address[] = $entry->postalCode;
            if ($entry->country ?? null) $address[] = $entry->country;

            if (!empty($address)) {
                $lines[] = "Address: " . implode(', ', $address);
            }

            if ($entry->phone ?? null) {
                $lines[] = "Phone: " . $entry->phone;
            }

            $url = $entry->getUrl();
            if ($url) {
                $lines[] = "Details: {$url}";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Format leadership entries for KB
     */
    private function formatLeadershipForKb(array $entries): string
    {
        $lines = [
            "JENSEN HUGHES LEADERSHIP TEAM",
            "=============================",
            "",
            "Meet the leadership team at Jensen Hughes:",
            "",
        ];

        foreach ($entries as $entry) {
            $lines[] = "## " . $entry->title;

            if ($entry->jobTitle ?? null) {
                $lines[] = "Title: " . $entry->jobTitle;
            }

            if ($entry->bio ?? null) {
                $lines[] = strip_tags($entry->bio);
            }

            $url = $entry->getUrl();
            if ($url) {
                $lines[] = "Profile: {$url}";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Upload content to Botpress KB
     */
    private function uploadToBotpress(string $kbName, string $content, string $filename): bool
    {
        $pat = getenv('BOTPRESS_PAT');
        $botId = getenv('BOTPRESS_BOT_ID');

        if (!$pat || !$botId) {
            $this->stderr("  Missing BOTPRESS_PAT or BOTPRESS_BOT_ID\n", Console::FG_RED);
            return false;
        }

        // Get or create KB ID from config
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $kbIds = $config['botpressKbIds'] ?? [];
        $kbId = $kbIds[$kbName] ?? getenv('BOTPRESS_KB_ID');

        if (!$kbId) {
            $this->stderr("  No KB ID configured for {$kbName}\n", Console::FG_RED);
            return false;
        }

        $this->stdout("  Uploading to Botpress KB: {$kbId}\n");

        // Create multipart form data
        $boundary = uniqid('boundary');
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: text/plain\r\n\r\n";
        $body .= $content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $url = "https://api.botpress.cloud/v1/files/knowledge-bases/{$kbId}/files";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$pat}",
                "x-bot-id: {$botId}",
                "Content-Type: multipart/form-data; boundary={$boundary}",
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->stderr("  cURL error: {$error}\n", Console::FG_RED);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->stdout("  ✓ Uploaded successfully\n", Console::FG_GREEN);
            return true;
        }

        $responseData = json_decode($response, true);
        $message = $responseData['message'] ?? "HTTP {$httpCode}";
        $this->stderr("  Upload failed: {$message}\n", Console::FG_RED);

        return false;
    }
}
