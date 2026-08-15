<?php

namespace rocketpark\mcpwrapper\support;

use Craft;

/**
 * Rate Limiter
 *
 * IP-based rate limiting using Craft's cache component.
 * Provides protection against DoS and abuse.
 */
class RateLimiter
{
    /**
     * Default rate limit: requests per window
     */
    private const DEFAULT_LIMIT = 100;

    /**
     * Default time window in seconds
     */
    private const DEFAULT_WINDOW = 60;

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'mcp_ratelimit_';

    /**
     * Check if request should be allowed
     *
     * @param string $ip Client IP address
     * @param int|null $limit Max requests per window (null = use config/default)
     * @param int|null $window Time window in seconds (null = use config/default)
     * @return array ['allowed' => bool, 'remaining' => int, 'resetAt' => int]
     */
    public static function check(string $ip, ?int $limit = null, ?int $window = null): array
    {
        // Get config values or use defaults
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $limit = $limit ?? ($config['security']['rateLimit'] ?? self::DEFAULT_LIMIT);
        $window = $window ?? ($config['security']['rateLimitWindow'] ?? self::DEFAULT_WINDOW);

        // Normalize IP for cache key (handle IPv6)
        $cacheKey = self::CACHE_PREFIX . md5($ip);

        // Get current request count
        $cache = Craft::$app->cache;
        $data = $cache->get($cacheKey);

        $now = time();

        if ($data === false) {
            // First request in this window
            $data = [
                'count' => 1,
                'windowStart' => $now,
            ];
            $cache->set($cacheKey, $data, $window);

            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'resetAt' => $now + $window,
            ];
        }

        // Check if window has expired
        if (($now - $data['windowStart']) >= $window) {
            // Reset window
            $data = [
                'count' => 1,
                'windowStart' => $now,
            ];
            $cache->set($cacheKey, $data, $window);

            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'resetAt' => $now + $window,
            ];
        }

        // Use mutex to prevent race conditions during increment
        $mutex = Craft::$app->getMutex();
        $lockName = "mcp_ratelimit_{$cacheKey}";
        $resetAt = $data['windowStart'] + $window;

        // Try to acquire lock (1 second timeout)
        if (!$mutex->acquire($lockName, 1)) {
            // If we can't get the lock, allow the request but log it
            Craft::warning("Rate limiter mutex timeout for IP: {$ip}", 'mcp-wrapper');
            return [
                'allowed' => true,
                'remaining' => max(0, $limit - $data['count']),
                'resetAt' => $resetAt,
            ];
        }

        try {
            // Re-fetch data inside lock to ensure consistency
            $data = $cache->get($cacheKey);
            if ($data === false) {
                // Cache expired while waiting for lock
                $data = [
                    'count' => 1,
                    'windowStart' => $now,
                ];
                $cache->set($cacheKey, $data, $window);
                return [
                    'allowed' => true,
                    'remaining' => $limit - 1,
                    'resetAt' => $now + $window,
                ];
            }

            // Increment counter
            $data['count']++;
            $remaining = max(0, $limit - $data['count']);
            $resetAt = $data['windowStart'] + $window;

            // Check if over limit
            if ($data['count'] > $limit) {
                Craft::warning("Rate limit exceeded for IP: {$ip} ({$data['count']}/{$limit})", 'mcp-wrapper');
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'resetAt' => $resetAt,
                ];
            }

            // Update cache
            $cache->set($cacheKey, $data, $window);

            return [
                'allowed' => true,
                'remaining' => $remaining,
                'resetAt' => $resetAt,
            ];
        } finally {
            $mutex->release($lockName);
        }
    }

    /**
     * Add rate limit headers to response
     *
     * @param array $rateLimitResult Result from check()
     * @param int $limit The rate limit
     */
    public static function addHeaders(array $rateLimitResult, int $limit = self::DEFAULT_LIMIT): void
    {
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$rateLimitResult['remaining']}");
        header("X-RateLimit-Reset: {$rateLimitResult['resetAt']}");
    }

    /**
     * Clear rate limit for an IP (for testing/admin purposes)
     */
    public static function clear(string $ip): void
    {
        $cacheKey = self::CACHE_PREFIX . md5($ip);
        Craft::$app->cache->delete($cacheKey);
    }
}
