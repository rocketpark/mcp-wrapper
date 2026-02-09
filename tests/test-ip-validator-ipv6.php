<?php
/**
 * Test script for IpValidator IPv6 CIDR support
 * Run: php tests/test-ip-validator-ipv6.php
 *
 * Tests the IPv6 CIDR validation added in v2.1.0
 */

// Manually include the class
require_once __DIR__ . '/../src/support/IpValidator.php';

use rocketpark\mcpwrapper\support\IpValidator;

echo "Testing IpValidator IPv6 CIDR Support...\n\n";

$tests = [];

// ============================================
// IPv6 CIDR Range Tests
// ============================================

// Test 1: IPv6 exact match in whitelist
$result = IpValidator::isAllowed('2001:db8::1', ['2001:db8::1']);
$tests[] = ['IPv6 exact match', $result === true];

// Test 2: IPv6 not in whitelist
$result = IpValidator::isAllowed('2001:db8::2', ['2001:db8::1']);
$tests[] = ['IPv6 not in whitelist', $result === false];

// Test 3: IPv6 in /64 CIDR range
$result = IpValidator::isAllowed('2001:db8:85a3::8a2e:370:7334', ['2001:db8:85a3::/64']);
$tests[] = ['IPv6 in /64 CIDR', $result === true];

// Test 4: IPv6 NOT in /64 CIDR range (different /64)
$result = IpValidator::isAllowed('2001:db8:85a4::1', ['2001:db8:85a3::/64']);
$tests[] = ['IPv6 not in /64 CIDR', $result === false];

// Test 5: IPv6 in /48 CIDR (larger range)
$result = IpValidator::isAllowed('2001:db8:85a3:ffff::1', ['2001:db8:85a3::/48']);
$tests[] = ['IPv6 in /48 CIDR', $result === true];

// Test 6: IPv6 in /32 CIDR (ISP allocation size)
$result = IpValidator::isAllowed('2001:db8:ffff:ffff::1', ['2001:db8::/32']);
$tests[] = ['IPv6 in /32 CIDR', $result === true];

// Test 7: IPv6 NOT in /32 CIDR
$result = IpValidator::isAllowed('2001:db9::1', ['2001:db8::/32']);
$tests[] = ['IPv6 not in /32 CIDR', $result === false];

// Test 8: IPv6 /128 (single host)
$result = IpValidator::isAllowed('2001:db8::1', ['2001:db8::1/128']);
$tests[] = ['IPv6 /128 single host match', $result === true];

// Test 9: IPv6 /128 no match
$result = IpValidator::isAllowed('2001:db8::2', ['2001:db8::1/128']);
$tests[] = ['IPv6 /128 no match', $result === false];

// Test 10: IPv6 /0 matches everything
$result = IpValidator::isAllowed('2001:db8::1', ['::/0']);
$tests[] = ['IPv6 /0 matches all', $result === true];

// Test 11: Loopback /128
$result = IpValidator::isAllowed('::1', ['::1/128']);
$tests[] = ['IPv6 loopback /128', $result === true];

// Test 12: Link-local CIDR
$result = IpValidator::isAllowed('fe80::1', ['fe80::/10']);
$tests[] = ['IPv6 link-local /10', $result === true];

// ============================================
// Mixed IPv4/IPv6 Tests
// ============================================

// Test 13: IPv4 doesn't match IPv6 CIDR
$result = IpValidator::isAllowed('192.168.1.1', ['2001:db8::/32']);
$tests[] = ['IPv4 does not match IPv6 CIDR', $result === false];

// Test 14: IPv6 doesn't match IPv4 CIDR
$result = IpValidator::isAllowed('2001:db8::1', ['192.168.0.0/16']);
$tests[] = ['IPv6 does not match IPv4 CIDR', $result === false];

// Test 15: Mixed whitelist with IPv4 and IPv6
$result = IpValidator::isAllowed('2001:db8::1', ['192.168.0.0/16', '2001:db8::/32', '10.0.0.0/8']);
$tests[] = ['IPv6 in mixed whitelist', $result === true];

// Test 16: IPv4 in mixed whitelist
$result = IpValidator::isAllowed('192.168.1.100', ['192.168.0.0/16', '2001:db8::/32', '10.0.0.0/8']);
$tests[] = ['IPv4 in mixed whitelist', $result === true];

// ============================================
// Edge Cases
// ============================================

// Test 17: Full IPv6 address format
$result = IpValidator::isAllowed('2001:0db8:0000:0000:0000:0000:0000:0001', ['2001:db8::/32']);
$tests[] = ['Full IPv6 format in CIDR', $result === true];

// Test 18: Invalid IPv6 mask (>128)
$result = IpValidator::isAllowed('2001:db8::1', ['2001:db8::/129']);
$tests[] = ['Invalid /129 mask rejected', $result === false];

// Test 19: Invalid IPv4 mask (>32)
$result = IpValidator::isAllowed('192.168.1.1', ['192.168.0.0/33']);
$tests[] = ['Invalid /33 mask rejected', $result === false];

// ============================================
// isValidCidr() Tests
// ============================================

// Test 20: Valid IPv4 CIDR
$result = IpValidator::isValidCidr('192.168.0.0/24');
$tests[] = ['isValidCidr: valid IPv4 /24', $result === true];

// Test 21: Valid IPv6 CIDR
$result = IpValidator::isValidCidr('2001:db8::/32');
$tests[] = ['isValidCidr: valid IPv6 /32', $result === true];

// Test 22: Invalid - no slash
$result = IpValidator::isValidCidr('192.168.0.0');
$tests[] = ['isValidCidr: no slash', $result === false];

// Test 23: Invalid - bad IP
$result = IpValidator::isValidCidr('999.999.999.999/24');
$tests[] = ['isValidCidr: invalid IP', $result === false];

// Test 24: Invalid IPv4 mask
$result = IpValidator::isValidCidr('192.168.0.0/33');
$tests[] = ['isValidCidr: IPv4 /33 invalid', $result === false];

// Test 25: Invalid IPv6 mask
$result = IpValidator::isValidCidr('2001:db8::/129');
$tests[] = ['isValidCidr: IPv6 /129 invalid', $result === false];

// Test 26: Valid edge case /0
$result = IpValidator::isValidCidr('0.0.0.0/0');
$tests[] = ['isValidCidr: IPv4 /0 valid', $result === true];

// Test 27: Valid IPv6 /0
$result = IpValidator::isValidCidr('::/0');
$tests[] = ['isValidCidr: IPv6 /0 valid', $result === true];

// Test 28: Valid /128
$result = IpValidator::isValidCidr('2001:db8::1/128');
$tests[] = ['isValidCidr: IPv6 /128 valid', $result === true];

// ============================================
// isValidIp() Tests
// ============================================

// Test 29: Valid IPv4
$result = IpValidator::isValidIp('192.168.1.1');
$tests[] = ['isValidIp: valid IPv4', $result === true];

// Test 30: Valid IPv6
$result = IpValidator::isValidIp('2001:db8::1');
$tests[] = ['isValidIp: valid IPv6', $result === true];

// Test 31: Invalid IP
$result = IpValidator::isValidIp('not-an-ip');
$tests[] = ['isValidIp: invalid string', $result === false];

// Test 32: IPv4 out of range
$result = IpValidator::isValidIp('256.256.256.256');
$tests[] = ['isValidIp: out of range', $result === false];

// ============================================
// Print Results
// ============================================

echo "Running " . count($tests) . " tests...\n\n";

$passed = 0;
$failed = 0;

foreach ($tests as $i => $test) {
    [$name, $result] = $test;
    $num = $i + 1;

    if ($result) {
        echo "Test {$num} - {$name}: ✓ PASS\n";
        $passed++;
    } else {
        echo "Test {$num} - {$name}: ✗ FAIL\n";
        $failed++;
    }
}

echo "\n";
echo "Results: {$passed}/" . count($tests) . " tests passed\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ {$failed} test(s) failed\n";
    exit(1);
}
