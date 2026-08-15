<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use yii\base\Component;

/**
 * Request Logger Service
 * 
 * Logs structured MCP request/response data for analytics and debugging.
 * Enables analysis of tool usage patterns, performance metrics, and error tracking.
 * 
 * @author Rocket Park <support@rocketpark.com>
 * @since 1.1.0
 */
class RequestLoggerService extends Component
{
    /**
     * Log an MCP request and its outcome
     * 
     * @param string $method The JSON-RPC method (tools/list, tools/call, etc.)
     * @param string|null $toolName The tool name (for tools/call)
     * @param array $arguments The tool arguments
     * @param array $response The complete JSON-RPC response
     * @param float $duration The request duration in seconds
     * @param string $schemaHandle The schema handle
     * @param string $ip The client IP address
     */
    public function logRequest(
        string $method,
        ?string $toolName,
        array $arguments,
        array $response,
        float $duration,
        string $schemaHandle,
        string $ip
    ): void {
        // Build structured log entry
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'schema' => $schemaHandle,
            'method' => $method,
            'tool' => $toolName,
            'args_hash' => $this->hashArguments($arguments),
            'args_count' => count($arguments),
            'success' => !isset($response['error']),
            'error_code' => $response['error']['code'] ?? null,
            'error_message' => $response['error']['message'] ?? null,
            'duration_ms' => round($duration * 1000, 2),
            'ip' => $this->anonymizeIp($ip),
            'user_agent' => $this->getUserAgent(),
        ];
        
        // Log to separate category for easy parsing
        Craft::getLogger()->log(
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            \yii\log\Logger::LEVEL_INFO,
            'mcp-requests'
        );
    }

    /**
     * Hash arguments for consistent tracking without exposing sensitive data
     */
    private function hashArguments(array $arguments): string
    {
        if (empty($arguments)) {
            return 'none';
        }
        
        // Sort for consistent hashing
        ksort($arguments);
        return substr(md5(json_encode($arguments)), 0, 8);
    }

    /**
     * Anonymize IP address for privacy (keep first 2 octets for IPv4, first 4 for IPv6)
     */
    private function anonymizeIp(string $ip): string
    {
        // IPv6
        if (strpos($ip, ':') !== false) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . ':****';
        }
        
        // IPv4
        if (strpos($ip, '.') !== false) {
            $parts = explode('.', $ip);
            return implode('.', array_slice($parts, 0, 2)) . '.**.**';
        }
        
        return 'unknown';
    }

    /**
     * Get user agent safely
     */
    private function getUserAgent(): string
    {
        $ua = Craft::$app->request->userAgent ?? 'unknown';
        
        // Truncate long user agents
        if (strlen($ua) > 200) {
            $ua = substr($ua, 0, 197) . '...';
        }
        
        return $ua;
    }

    /**
     * Get analytics summary from logs
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @param string|null $schemaHandle Filter by schema (null = all schemas)
     * @return array Analytics data
     */
    public function getAnalytics(int $days = 7, ?string $schemaHandle = null): array
    {
        $logPath = Craft::getAlias('@storage/logs/mcp-requests.log');
        
        if (!file_exists($logPath)) {
            return [
                'total_requests' => 0,
                'success_rate' => 0,
                'avg_duration_ms' => 0,
                'cache_hit_rate' => 0,
                'top_tools' => [],
                'slowest_requests' => [],
                'recent_errors' => [],
                'error' => 'No request logs found',
            ];
        }
        
        $cutoff = strtotime("-{$days} days");

        $stats = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_duration_ms' => 0,
            'cache_hits' => 0,
            'cache_checks' => 0,
            'by_tool' => [],
            'by_method' => [],
            'by_error_code' => [],
            'slowest_requests' => [],
            'recent_errors' => [],
        ];

        // Use SplFileObject for memory-efficient line-by-line reading
        // instead of file() which loads the entire log into memory
        $file = new \SplFileObject($logPath, 'r');
        $file->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);

        foreach ($file as $line) {
            if (empty($line)) {
                continue;
            }

            // Extract JSON from log line (format: "timestamp [category] JSON")
            if (preg_match('/\{.*\}/', $line, $matches)) {
                $entry = json_decode($matches[0], true);

                if (!$entry) continue;

                // Skip old entries
                $entryTime = strtotime($entry['timestamp'] ?? '');
                if ($entryTime < $cutoff) continue;

                // Filter by schema if specified
                if ($schemaHandle && ($entry['schema'] ?? '') !== $schemaHandle) {
                    continue;
                }

                $stats['total_requests']++;

                if ($entry['success'] ?? false) {
                    $stats['successful_requests']++;
                } else {
                    $stats['failed_requests']++;
                    $code = $entry['error_code'] ?? 'unknown';
                    $stats['by_error_code'][$code] = ($stats['by_error_code'][$code] ?? 0) + 1;

                    // Track recent errors
                    $stats['recent_errors'][] = [
                        'tool' => $entry['tool'] ?? $entry['method'] ?? 'unknown',
                        'error_code' => $entry['error_code'] ?? 'unknown',
                        'error_message' => $entry['error_message'] ?? 'Unknown error',
                        'timestamp' => $entry['timestamp'] ?? '',
                    ];
                }

                $stats['total_duration_ms'] += ($entry['duration_ms'] ?? 0);

                // Track cache usage (look for cache-related info in log or infer from very fast responses)
                if (($entry['duration_ms'] ?? 0) < 10) {
                    $stats['cache_hits']++;
                }
                $stats['cache_checks']++;

                // By method
                $method = $entry['method'] ?? 'unknown';
                if (!isset($stats['by_method'][$method])) {
                    $stats['by_method'][$method] = ['count' => 0, 'total_ms' => 0];
                }
                $stats['by_method'][$method]['count']++;
                $stats['by_method'][$method]['total_ms'] += ($entry['duration_ms'] ?? 0);

                // By tool
                if (!empty($entry['tool'])) {
                    $tool = $entry['tool'];
                    if (!isset($stats['by_tool'][$tool])) {
                        $stats['by_tool'][$tool] = ['count' => 0, 'total_ms' => 0, 'errors' => 0];
                    }
                    $stats['by_tool'][$tool]['count']++;
                    $stats['by_tool'][$tool]['total_ms'] += ($entry['duration_ms'] ?? 0);
                    if (!($entry['success'] ?? false)) {
                        $stats['by_tool'][$tool]['errors']++;
                    }
                }

                // Track slowest requests
                $stats['slowest_requests'][] = [
                    'tool' => $entry['tool'] ?? $entry['method'] ?? 'unknown',
                    'duration_ms' => $entry['duration_ms'] ?? 0,
                    'timestamp' => $entry['timestamp'] ?? '',
                ];
            }
        }

        // Release file handle
        $file = null;
        
        // Calculate averages and rates
        if ($stats['total_requests'] > 0) {
            $stats['avg_duration_ms'] = round($stats['total_duration_ms'] / $stats['total_requests'], 2);
            $stats['success_rate'] = round(($stats['successful_requests'] / $stats['total_requests']) * 100, 1);
        } else {
            $stats['avg_duration_ms'] = 0;
            $stats['success_rate'] = 0;
        }
        
        if ($stats['cache_checks'] > 0) {
            $stats['cache_hit_rate'] = round(($stats['cache_hits'] / $stats['cache_checks']) * 100, 1);
        } else {
            $stats['cache_hit_rate'] = 0;
        }
        
        foreach ($stats['by_method'] as $method => &$data) {
            $data['avg_ms'] = round($data['total_ms'] / $data['count'], 2);
        }
        
        // Build top_tools array for dashboard
        $topTools = [];
        foreach ($stats['by_tool'] as $tool => $data) {
            $topTools[] = [
                'tool' => $tool,
                'count' => $data['count'],
                'avg_duration_ms' => round($data['total_ms'] / $data['count'], 2),
                'error_rate' => round(($data['errors'] / $data['count']) * 100, 1),
            ];
        }
        
        // Sort slowest requests
        usort($stats['slowest_requests'], fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        $stats['slowest_requests'] = array_slice($stats['slowest_requests'], 0, 10);
        
        // Sort recent errors by timestamp (newest first)
        usort($stats['recent_errors'], fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));
        $stats['recent_errors'] = array_slice($stats['recent_errors'], 0, 10);
        
        // Sort top tools by usage
        usort($topTools, fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_tools'] = array_slice($topTools, 0, 10);
        
        return $stats;
    }
}
