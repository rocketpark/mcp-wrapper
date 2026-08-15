<?php

namespace rocketpark\mcpwrapper\support;

/**
 * URL normalization for tool output.
 *
 * Craft's $entry->url uses each Site's base URL, which on staging/dev is
 * a non-production hostname (e.g. https://jensenhughes3.on-forge.com).
 * Bot replies that quote $entry->url leak the staging hostname to users.
 *
 * normalizeForProduction() rewrites the host portion of any URL whose host
 * matches a known staging-class pattern. URLs that already point at a
 * production host pass through untouched. External URLs (social, video,
 * maps, podcast platforms) pass through untouched.
 *
 * The allowlist of staging hosts is hardcoded — Jensen Hughes specific.
 * If/when this plugin is generalized for other Rocket Park CMS clients,
 * lift these into config/mcpwrapper.php under a `productionHost` +
 * `stagingHosts` block.
 */
class UrlNormalizer
{
    /**
     * Canonical production host for Jensen Hughes.
     */
    private const PRODUCTION_HOST = 'www.jensenhughes.com';

    /**
     * Hosts whose URLs should be rewritten to PRODUCTION_HOST.
     * Match is exact for hostnames + suffix-match for *.test / .ddev.site / .on-forge.com.
     */
    private const STAGING_HOSTS_EXACT = [
        'jensenhughes3.on-forge.com',
        'staging3.jensenhughes.com',
        'jensenhughes.test',
        'jensenhughes.ddev.site',
        'localhost',
    ];

    private const STAGING_HOSTS_SUFFIX = [
        '.on-forge.com',
        '.ddev.site',
        '.test',
    ];

    /**
     * Rewrite the host portion of a Jensen Hughes URL to the production host
     * if the URL is currently pointing at a known staging host.
     *
     * Pass-through cases (returned unmodified):
     * - Null / empty / non-string inputs
     * - URLs without a host (malformed, relative)
     * - URLs already on PRODUCTION_HOST
     * - URLs on external hosts (maps.google.com, podcast platforms, social)
     *
     * @param mixed $url
     * @return mixed Same type as input — string in, string out; null in, null out
     */
    public static function normalizeForProduction($url)
    {
        if (!is_string($url) || trim($url) === '') {
            return $url;
        }

        $parsed = parse_url($url);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return $url;
        }

        $host = strtolower($parsed['host']);
        if ($host === self::PRODUCTION_HOST) {
            return $url;
        }

        if (!self::isStagingHost($host)) {
            return $url;
        }

        $scheme   = $parsed['scheme'] ?? 'https';
        $path     = $parsed['path'] ?? '';
        $query    = isset($parsed['query'])    ? '?' . $parsed['query']    : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . '://' . self::PRODUCTION_HOST . $path . $query . $fragment;
    }

    private static function isStagingHost(string $host): bool
    {
        if (in_array($host, self::STAGING_HOSTS_EXACT, true)) {
            return true;
        }
        foreach (self::STAGING_HOSTS_SUFFIX as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }
}
