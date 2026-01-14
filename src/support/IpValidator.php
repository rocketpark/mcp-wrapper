<?php

namespace rocketpark\mcpwrapper\support;

/**
 * IP Address Validation Helper
 * 
 * Validates IP addresses against whitelist with CIDR notation support
 */
class IpValidator
{
    /**
     * Check if an IP address is allowed based on whitelist
     * 
     * @param string $ip IP address to check
     * @param array $allowedIps Array of allowed IPs (supports CIDR notation)
     * @return bool True if IP is allowed
     */
    public static function isAllowed(string $ip, array $allowedIps): bool
    {
        // Empty whitelist = allow all
        if (empty($allowedIps)) {
            return true;
        }

        foreach ($allowedIps as $allowed) {
            if (self::matches($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches pattern (supports CIDR notation)
     * 
     * @param string $ip IP address to check
     * @param string $pattern IP or CIDR pattern (e.g., "10.0.0.0/8")
     * @return bool True if IP matches pattern
     */
    private static function matches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation check
        if (str_contains($pattern, '/')) {
            return self::ipInCidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range
     * 
     * @param string $ip IP address to check
     * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
     * @return bool True if IP is in range
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        // Convert to long integers for comparison
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        // Create mask
        $maskLong = -1 << (32 - (int)$mask);
        
        // Compare network addresses
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
