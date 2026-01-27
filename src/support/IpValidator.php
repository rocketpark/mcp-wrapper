<?php

namespace rocketpark\mcpwrapper\support;

/**
 * IP Address Validation Helper
 *
 * Validates IP addresses against whitelist with CIDR notation support.
 * Supports both IPv4 and IPv6 addresses.
 */
class IpValidator
{
    /**
     * Check if an IP address is allowed based on whitelist
     *
     * @param string $ip IP address to check (IPv4 or IPv6)
     * @param array $allowedIps Array of allowed IPs (supports CIDR notation for both IPv4 and IPv6)
     * @return bool True if IP is allowed
     */
    public static function isAllowed(string $ip, array $allowedIps): bool
    {
        // Empty whitelist = allow all
        if (empty($allowedIps)) {
            return true;
        }

        // Normalize the input IP
        $normalizedIp = self::normalizeIp($ip);
        if ($normalizedIp === null) {
            return false; // Invalid IP address
        }

        foreach ($allowedIps as $allowed) {
            if (self::matches($normalizedIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize an IP address for consistent comparison
     * Handles IPv4-mapped IPv6 addresses (::ffff:192.168.1.1)
     *
     * @param string $ip IP address
     * @return string|null Normalized IP or null if invalid
     */
    private static function normalizeIp(string $ip): ?string
    {
        // Handle IPv4-mapped IPv6 addresses
        if (str_starts_with($ip, '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return substr($ip, 7); // Extract the IPv4 part
        }

        // Validate the IP address
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return null;
    }

    /**
     * Check if IP matches pattern (supports CIDR notation for IPv4 and IPv6)
     *
     * @param string $ip IP address to check
     * @param string $pattern IP or CIDR pattern (e.g., "10.0.0.0/8" or "2001:db8::/32")
     * @return bool True if IP matches pattern
     */
    private static function matches(string $ip, string $pattern): bool
    {
        // Normalize the pattern IP as well
        $patternNormalized = str_contains($pattern, '/')
            ? explode('/', $pattern)[0]
            : $pattern;

        // Exact match (after normalization)
        if ($ip === $patternNormalized) {
            return true;
        }

        // CIDR notation check
        if (str_contains($pattern, '/')) {
            return self::ipInCidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range (supports IPv4 and IPv6)
     *
     * @param string $ip IP address to check
     * @param string $cidr CIDR notation (e.g., "192.168.1.0/24" or "2001:db8::/32")
     * @return bool True if IP is in range
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = explode('/', $cidr);
        $maskBits = (int) $maskBits;

        // Detect IP version
        $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isSubnetIpv4 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        // IP versions must match
        if ($isIpv4 !== $isSubnetIpv4) {
            return false;
        }

        if ($isIpv4) {
            return self::ipv4InCidr($ip, $subnet, $maskBits);
        } else {
            return self::ipv6InCidr($ip, $subnet, $maskBits);
        }
    }

    /**
     * Check if IPv4 address is within CIDR range
     */
    private static function ipv4InCidr(string $ip, string $subnet, int $maskBits): bool
    {
        // Validate mask range
        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        // Create mask (handle edge case of /0)
        if ($maskBits === 0) {
            return true; // /0 matches everything
        }

        $maskLong = -1 << (32 - $maskBits);

        // Compare network addresses
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IPv6 address is within CIDR range
     */
    private static function ipv6InCidr(string $ip, string $subnet, int $maskBits): bool
    {
        // Validate mask range
        if ($maskBits < 0 || $maskBits > 128) {
            return false;
        }

        // Convert to binary representation
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // /0 matches everything
        if ($maskBits === 0) {
            return true;
        }

        // Convert binary to string of bits
        $ipBits = '';
        $subnetBits = '';

        for ($i = 0; $i < strlen($ipBin); $i++) {
            $ipBits .= str_pad(decbin(ord($ipBin[$i])), 8, '0', STR_PAD_LEFT);
            $subnetBits .= str_pad(decbin(ord($subnetBin[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Compare the first $maskBits bits
        return substr($ipBits, 0, $maskBits) === substr($subnetBits, 0, $maskBits);
    }

    /**
     * Validate if a string is a valid IP address (IPv4 or IPv6)
     */
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate if a string is a valid CIDR notation
     */
    public static function isValidCidr(string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $cidr);

        if (!self::isValidIp($ip)) {
            return false;
        }

        $maskInt = (int) $mask;
        $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        if ($isIpv4) {
            return $maskInt >= 0 && $maskInt <= 32;
        } else {
            return $maskInt >= 0 && $maskInt <= 128;
        }
    }
}
