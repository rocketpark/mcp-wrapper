<?php
/**
 * Test script for IpValidator
 * Run: php tests/test-ip-validator.php
 */

// Manually include the class
require_once __DIR__ . '/../src/support/IpValidator.php';

use rocketpark\mcpwrapper\support\IpValidator;

echo "Testing IpValidator...\n\n";

// Test 1: Empty whitelist (should allow all)
$result1 = IpValidator::isAllowed('192.168.1.100', []);
echo "Test 1 - Empty whitelist: " . ($result1 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 2: Exact match
$result2 = IpValidator::isAllowed('127.0.0.1', ['127.0.0.1', '192.168.1.1']);
echo "Test 2 - Exact match: " . ($result2 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 3: Not in whitelist
$result3 = !IpValidator::isAllowed('10.0.0.1', ['127.0.0.1', '192.168.1.1']);
echo "Test 3 - Not in whitelist: " . ($result3 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 4: CIDR match - 192.168.1.0/24 should include 192.168.1.50
$result4 = IpValidator::isAllowed('192.168.1.50', ['192.168.1.0/24']);
echo "Test 4 - CIDR match (in range): " . ($result4 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 5: CIDR no match - 192.168.1.0/24 should NOT include 192.168.2.50
$result5 = !IpValidator::isAllowed('192.168.2.50', ['192.168.1.0/24']);
echo "Test 5 - CIDR no match (out of range): " . ($result5 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 6: Large CIDR - 10.0.0.0/8 should include 10.255.255.255
$result6 = IpValidator::isAllowed('10.255.255.255', ['10.0.0.0/8']);
echo "Test 6 - Large CIDR (/8): " . ($result6 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 7: Mixed whitelist
$result7 = IpValidator::isAllowed('127.0.0.1', ['10.0.0.0/8', '127.0.0.1', '192.168.0.0/16']);
echo "Test 7 - Mixed whitelist (exact): " . ($result7 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 8: Mixed whitelist CIDR
$result8 = IpValidator::isAllowed('192.168.100.50', ['10.0.0.0/8', '127.0.0.1', '192.168.0.0/16']);
echo "Test 8 - Mixed whitelist (CIDR): " . ($result8 ? "✓ PASS" : "✗ FAIL") . "\n";

// Test 9: localhost IPv6
$result9 = IpValidator::isAllowed('::1', ['::1', '127.0.0.1']);
echo "Test 9 - IPv6 localhost: " . ($result9 ? "✓ PASS" : "✗ FAIL") . "\n";

echo "\n";
$totalTests = 9;
$passedTests = (int)$result1 + (int)$result2 + (int)$result3 + (int)$result4 + (int)$result5 + (int)$result6 + (int)$result7 + (int)$result8 + (int)$result9;
echo "Results: {$passedTests}/{$totalTests} tests passed\n";

if ($passedTests === $totalTests) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed\n";
    exit(1);
}
