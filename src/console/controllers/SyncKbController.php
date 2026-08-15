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

        $entries = Entry::find()
            ->section('services')
            ->status('live')
            ->all();

        if (empty($entries)) {
            $this->stderr("  No services found\n", Console::FG_RED);
            return false;
        }

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

        // Get all team members - teamMemberType is a relational field
        $allEntries = Entry::find()
            ->section('ourTeam')
            ->status('live')
            ->all();

        $this->stdout("  Found " . count($allEntries) . " total team members\n");

        // Filter to only Regional Leadership members
        $entries = [];
        foreach ($allEntries as $entry) {
            $types = $entry->getFieldValue('teamMemberType');
            if ($types) {
                foreach ($types->all() as $type) {
                    if (stripos($type->title, 'Regional Leadership') !== false) {
                        $entries[] = $entry;
                        break;
                    }
                }
            }
        }

        $this->stdout("  Found " . count($entries) . " Regional Leaders\n");

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

        // Validate content before upload
        $maxSize = 10 * 1024 * 1024; // 10MB limit
        if (strlen($content) > $maxSize) {
            $this->stderr("  Content too large: " . number_format(strlen($content)) . " bytes (max " . number_format($maxSize) . ")\n", Console::FG_RED);
            return false;
        }

        // Validate UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $this->stderr("  Invalid UTF-8 encoding in content\n", Console::FG_RED);
            return false;
        }

        // Sanitize content: remove null bytes and control characters (except newlines/tabs)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        $contentSize = strlen($content);
        $this->stdout("  Content size: " . number_format($contentSize) . " bytes\n");

        // Step 1: Create file record in Botpress (PUT /v1/files API)
        $createPayload = json_encode([
            'key' => $filename,
            'size' => $contentSize,
            'index' => true,
            'tags' => [
                'source' => 'knowledge-base',
                'kbId' => $kbId,
                'title' => ucfirst($kbName) . ' (auto-synced)',
            ],
        ]);

        $url = 'https://api.botpress.cloud/v1/files';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $pat,
                'x-bot-id: ' . $botId,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $createPayload,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->stderr("  cURL error (create): {$error}\n", Console::FG_RED);
            return false;
        }

        $responseData = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $responseData['message'] ?? 'HTTP ' . $httpCode;
            $this->stderr("  File create failed: {$message}\n", Console::FG_RED);
            return false;
        }

        $uploadUrl = $responseData['file']['uploadUrl'] ?? null;
        if (!$uploadUrl) {
            $this->stderr("  No uploadUrl in response\n", Console::FG_RED);
            return false;
        }

        $this->stdout("  File record created, uploading content...\n");

        // Step 2: Upload actual content to the presigned URL
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/plain; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => $content,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->stderr("  cURL error (upload): {$error}\n", Console::FG_RED);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->stdout("  ✓ Uploaded successfully\n", Console::FG_GREEN);
            return true;
        }

        $this->stderr("  Upload failed: HTTP {$httpCode}\n", Console::FG_RED);
        return false;
    }
}
