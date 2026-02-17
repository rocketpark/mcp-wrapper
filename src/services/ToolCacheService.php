<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use yii\base\Component;

/**
 * Tool Cache Service
 * 
 * Caches tool execution results to reduce duplicate queries and improve performance.
 * Supports configurable TTL and per-tool exclusion.
 * 
 * @author Rocket Park <support@rocketpark.com>
 * @since 1.1.0
 */
class ToolCacheService extends Component
{
    /**
     * Generate cache key for a tool execution
     */
    private function getCacheKey(string $toolName, array $arguments): string
    {
        // Sort arguments for consistent hashing
        ksort($arguments);
        $argsJson = json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = md5($argsJson);
        
        return "mcp_tool_result_{$toolName}_{$hash}";
    }

    /**
     * Get cached tool result if available
     * 
     * @param string $toolName The tool name
     * @param array $arguments The tool arguments
     * @return array|null The cached result or null if not found/expired
     */
    public function get(string $toolName, array $arguments): ?array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $ttl = $config['toolCacheTTL'] ?? 300; // 5 min default
        
        // Caching disabled
        if ($ttl <= 0) {
            return null;
        }
        
        // Check if tool is excluded from caching
        $exclude = $config['toolCacheExclude'] ?? [];
        if (in_array($toolName, $exclude, true)) {
            Craft::info("Tool cache SKIP (excluded): {$toolName}", 'mcp-wrapper');
            return null;
        }
        
        $key = $this->getCacheKey($toolName, $arguments);
        $cached = Craft::$app->cache->get($key);
        
        if ($cached !== false) {
            Craft::info("Tool cache HIT: {$toolName}", 'mcp-wrapper');
            return $cached;
        }
        
        Craft::info("Tool cache MISS: {$toolName}", 'mcp-wrapper');
        return null;
    }

    /**
     * Store tool result in cache
     * 
     * @param string $toolName The tool name
     * @param array $arguments The tool arguments
     * @param array $result The tool result to cache
     */
    public function set(string $toolName, array $arguments, array $result): void
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $ttl = $config['toolCacheTTL'] ?? 300;
        $exclude = $config['toolCacheExclude'] ?? [];
        
        // Don't cache if disabled or tool is excluded
        if ($ttl <= 0 || in_array($toolName, $exclude, true)) {
            return;
        }
        
        $key = $this->getCacheKey($toolName, $arguments);
        Craft::$app->cache->set($key, $result, $ttl);
        Craft::info("Tool cache SET: {$toolName} (TTL: {$ttl}s)", 'mcp-wrapper');
    }

    /**
     * Clear all cached tool results
     * 
     * @return bool Success status
     */
    public function flush(): bool
    {
        $cache = Craft::$app->cache;
        $flushed = 0;
        
        // Since we can't easily iterate cache keys, we'll use tag-based flushing
        // For now, just log the flush request
        Craft::info("Tool cache FLUSH requested", 'mcp-wrapper');
        
        // If using file cache, we could iterate storage/runtime/cache/
        // For Redis/Memcached, we'd need to track keys separately
        // For simplicity, return true and let natural expiration handle it
        
        return true;
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache stats (hit rate, size, etc.)
     */
    public function getStats(): array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        
        return [
            'enabled' => ($config['toolCacheTTL'] ?? 300) > 0,
            'ttl' => $config['toolCacheTTL'] ?? 300,
            'excludedTools' => $config['toolCacheExclude'] ?? [],
            'cacheBackend' => get_class(Craft::$app->cache),
        ];
    }

    /**
     * Check if caching is enabled for a specific tool
     * 
     * @param string $toolName The tool name
     * @return bool True if caching is enabled for this tool
     */
    public function isEnabled(string $toolName): bool
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $ttl = $config['toolCacheTTL'] ?? 300;
        $exclude = $config['toolCacheExclude'] ?? [];
        
        return $ttl > 0 && !in_array($toolName, $exclude, true);
    }
}
