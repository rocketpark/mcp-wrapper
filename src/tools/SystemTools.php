<?php

namespace rocketpark\mcpwrapper\tools;

use Craft;
use rocketpark\mcpwrapper\attributes\Tool;
use rocketpark\mcpwrapper\support\Response;
use rocketpark\mcpwrapper\support\SafeExecution;

/**
 * System Tools
 * 
 * Administrative and debugging tools for Craft CMS system information
 */
class SystemTools
{
    /**
     * Get comprehensive system information
     */
    #[Tool(
        name: 'craft_get_system_info',
        description: 'Get Craft CMS system information including version, environment, PHP, database, and installed plugins',
        inputSchema: [
            'type' => 'object',
            'properties' => [],
        ],
        dangerous: false,
    )]
    public function getSystemInfo(): array
    {
        return SafeExecution::run(function() {
            $info = [
                'craft' => [
                    'version' => Craft::$app->getVersion(),
                    'edition' => Craft::$app->getEditionName(),
                    'editionId' => Craft::$app->getEdition(),
                    'environment' => Craft::$app->env,
                    'isInstalled' => Craft::$app->getIsInstalled(),
                    'schemaVersion' => Craft::$app->getInstalledSchemaVersion(),
                    'timeZone' => Craft::$app->getTimeZone(),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'memoryLimit' => ini_get('memory_limit'),
                    'maxExecutionTime' => ini_get('max_execution_time'),
                    'extensions' => get_loaded_extensions(),
                ],
                'database' => [
                    'driver' => Craft::$app->getDb()->getDriverName(),
                    'version' => Craft::$app->getDb()->getServerVersion(),
                    'tablePrefix' => Craft::$app->getDb()->tablePrefix,
                ],
                'server' => [
                    'os' => PHP_OS,
                    'hostname' => gethostname(),
                ],
                'plugins' => array_values(array_map(fn($p) => [
                    'handle' => $p->handle,
                    'name' => $p->name,
                    'version' => $p->version,
                    'enabled' => Craft::$app->getPlugins()->isPluginEnabled($p->handle),
                    'installed' => Craft::$app->getPlugins()->isPluginInstalled($p->handle),
                ], Craft::$app->getPlugins()->getAllPlugins())),
            ];
            
            return Response::success($info);
        });
    }

    /**
     * Get installed plugins list
     */
    #[Tool(
        name: 'craft_list_plugins',
        description: 'List all installed Craft CMS plugins with their versions and status',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'enabledOnly' => [
                    'type' => 'boolean',
                    'description' => 'Only return enabled plugins (default: false)',
                    'default' => false,
                ],
            ],
        ],
        dangerous: false,
    )]
    public function listPlugins(bool $enabledOnly = false): array
    {
        return SafeExecution::run(function() use ($enabledOnly) {
            $plugins = Craft::$app->getPlugins()->getAllPlugins();
            
            if ($enabledOnly) {
                $plugins = array_filter($plugins, fn($p) => Craft::$app->getPlugins()->isPluginEnabled($p->handle));
            }
            
            $pluginList = array_values(array_map(fn($p) => [
                'handle' => $p->handle,
                'name' => $p->name,
                'version' => $p->version,
                'enabled' => Craft::$app->getPlugins()->isPluginEnabled($p->handle),
                'installed' => Craft::$app->getPlugins()->isPluginInstalled($p->handle),
                'hasCpSection' => $p->hasCpSection,
            ], $plugins));
            
            return Response::found('plugins', $pluginList);
        });
    }

    /**
     * Get queue jobs status
     */
    #[Tool(
        name: 'craft_get_queue_status',
        description: 'Get Craft CMS queue status including waiting, running, and failed jobs',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum jobs to return per status (default: 20)',
                    'default' => 20,
                ],
            ],
        ],
        dangerous: false,
    )]
    public function getQueueStatus(int $limit = 20): array
    {
        return SafeExecution::run(function() use ($limit) {
            $queue = Craft::$app->getQueue();
            
            // Get job info by status
            $waiting = $queue->getJobInfo(1, $limit); // STATUS_WAITING
            $failed = $queue->getJobInfo(4, $limit);  // STATUS_FAILED
            
            $status = [
                'waiting' => array_map(fn($job) => [
                    'id' => $job['id'],
                    'description' => $job['description'],
                    'priority' => $job['priority'],
                    'timePushed' => date('Y-m-d H:i:s', $job['timePushed']),
                ], $waiting),
                'failed' => array_map(fn($job) => [
                    'id' => $job['id'],
                    'description' => $job['description'],
                    'error' => $job['error'] ?? null,
                    'timeFailed' => isset($job['timeFailed']) ? date('Y-m-d H:i:s', $job['timeFailed']) : null,
                ], $failed),
                'counts' => [
                    'waiting' => count($waiting),
                    'failed' => count($failed),
                ],
            ];
            
            return Response::success($status);
        });
    }

    /**
     * Read recent log entries
     */
    #[Tool(
        name: 'craft_read_logs',
        description: 'Read recent entries from Craft CMS log files (web.log)',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum log entries to return (default: 50)',
                    'default' => 50,
                ],
                'level' => [
                    'type' => 'string',
                    'enum' => ['error', 'warning', 'info', 'all'],
                    'description' => 'Filter by log level (default: all)',
                    'default' => 'all',
                ],
            ],
        ],
        dangerous: false,
    )]
    public function readLogs(int $limit = 50, string $level = 'all'): array
    {
        return SafeExecution::run(function() use ($limit, $level) {
            $logFile = Craft::getAlias('@storage/logs/web.log');
            
            if (!file_exists($logFile)) {
                return Response::notFound('Log file not found');
            }
            
            // Read last N lines
            $lines = $this->readLastLines($logFile, $limit * 2); // Read more to account for filtering
            
            // Parse and filter logs
            $logs = [];
            foreach ($lines as $line) {
                $parsed = $this->parseLogLine($line);
                
                if ($parsed && ($level === 'all' || strtolower($parsed['level']) === $level)) {
                    $logs[] = $parsed;
                    
                    if (count($logs) >= $limit) {
                        break;
                    }
                }
            }
            
            return Response::found('logs', array_reverse($logs)); // Most recent first
        });
    }

    /**
     * Get cache information
     */
    #[Tool(
        name: 'craft_get_cache_info',
        description: 'Get information about Craft CMS caches (data cache, template cache, compiled templates)',
        inputSchema: [
            'type' => 'object',
            'properties' => [],
        ],
        dangerous: false,
    )]
    public function getCacheInfo(): array
    {
        return SafeExecution::run(function() {
            $info = [
                'dataCache' => [
                    'enabled' => Craft::$app->getCache() !== null,
                    'path' => Craft::getAlias('@runtime/cache'),
                ],
                'templateCache' => [
                    'enabled' => Craft::$app->getConfig()->getGeneral()->enableTemplateCaching,
                ],
                'compiledTemplates' => [
                    'path' => Craft::getAlias('@runtime/compiled_templates'),
                ],
            ];
            
            return Response::success($info);
        });
    }

    /**
     * Clear Craft caches
     */
    #[Tool(
        name: 'craft_clear_caches',
        description: 'Clear Craft CMS caches (data, template, compiled templates, asset transforms)',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'caches' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['data', 'template', 'compiled', 'transforms', 'all'],
                    ],
                    'description' => 'Which caches to clear (default: all)',
                    'default' => ['all'],
                ],
            ],
        ],
        dangerous: true,
    )]
    public function clearCaches(array $caches = ['all']): array
    {
        return SafeExecution::run(function() use ($caches) {
            $cleared = [];
            
            if (in_array('all', $caches) || in_array('data', $caches)) {
                Craft::$app->getCache()->flush();
                $cleared[] = 'data';
            }
            
            if (in_array('all', $caches) || in_array('template', $caches)) {
                Craft::$app->getTemplateCaches()->deleteAllCaches();
                $cleared[] = 'template';
            }
            
            if (in_array('all', $caches) || in_array('compiled', $caches)) {
                Craft::$app->getPath()->clearCompiledTemplates();
                $cleared[] = 'compiled';
            }
            
            if (in_array('all', $caches) || in_array('transforms', $caches)) {
                Craft::$app->getImageTransforms()->deleteAllTransforms();
                $cleared[] = 'transforms';
            }
            
            return Response::success([
                'cleared' => $cleared,
                'message' => 'Caches cleared successfully',
            ]);
        });
    }

    /**
     * Get project config status
     */
    #[Tool(
        name: 'craft_get_project_config_status',
        description: 'Get Craft CMS project config status and pending changes',
        inputSchema: [
            'type' => 'object',
            'properties' => [],
        ],
        dangerous: false,
    )]
    public function getProjectConfigStatus(): array
    {
        return SafeExecution::run(function() {
            $projectConfig = Craft::$app->getProjectConfig();
            
            // Check for pending changes
            $areChangesPending = $projectConfig->areChangesPending();
            
            $status = [
                'changesPending' => $areChangesPending,
                'readOnly' => $projectConfig->readOnly,
                'writeYamlAutomatically' => $projectConfig->writeYamlAutomatically,
            ];
            
            return Response::success($status);
        });
    }

    /**
     * Read last N lines from a file efficiently
     */
    private function readLastLines(string $filePath, int $lines): array
    {
        $file = fopen($filePath, 'r');
        if (!$file) {
            return [];
        }
        
        // Read file in chunks from the end
        $buffer = 4096;
        $output = [];
        
        fseek($file, 0, SEEK_END);
        $fileSize = ftell($file);
        $pos = $fileSize;
        
        while ($pos > 0 && count($output) < $lines) {
            $chunkSize = min($buffer, $pos);
            $pos -= $chunkSize;
            fseek($file, $pos);
            $chunk = fread($file, $chunkSize);
            $output = array_merge(explode("\n", $chunk), $output);
        }
        
        fclose($file);
        
        return array_slice($output, -$lines);
    }

    /**
     * Parse a log line
     */
    private function parseLogLine(string $line): ?array
    {
        // Format: [timestamp] [level] [category] message
        if (preg_match('/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[([^\]]+)\]\s+(.+)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'category' => $matches[3],
                'message' => $matches[4],
            ];
        }
        
        return null;
    }
}
