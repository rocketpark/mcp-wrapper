<?php
/**
 * Test script for RequestTimeoutException
 * Run: php tests/test-request-timeout-exception.php
 *
 * Tests the custom exception class added for MCP timeout handling
 */

// Manually include the class
require_once __DIR__ . '/../src/support/RequestTimeoutException.php';

use rocketpark\mcpwrapper\support\RequestTimeoutException;

echo "Testing RequestTimeoutException...\n\n";

$tests = [];

// Test 1: Error code constant is correct (-32001)
$result = RequestTimeoutException::ERROR_CODE === -32001;
$tests[] = ['ERROR_CODE is -32001', $result];

// Test 2: Default constructor message
$exception = new RequestTimeoutException();
$result = $exception->getMessage() === 'Request timeout';
$tests[] = ['Default message is "Request timeout"', $result];

// Test 3: Custom message
$exception = new RequestTimeoutException('Custom timeout message');
$result = $exception->getMessage() === 'Custom timeout message';
$tests[] = ['Custom message works', $result];

// Test 4: Exception code matches ERROR_CODE
$exception = new RequestTimeoutException();
$result = $exception->getCode() === RequestTimeoutException::ERROR_CODE;
$tests[] = ['Exception code matches ERROR_CODE', $result];

// Test 5: Previous exception chaining
$previous = new \Exception('Previous error');
$exception = new RequestTimeoutException('Timeout', $previous);
$result = $exception->getPrevious() === $previous;
$tests[] = ['Previous exception chaining works', $result];

// Test 6: Is instance of Exception
$exception = new RequestTimeoutException();
$result = $exception instanceof \Exception;
$tests[] = ['Is instance of Exception', $result];

// Test 7: Can be caught as Exception
$caught = false;
try {
    throw new RequestTimeoutException();
} catch (\Exception $e) {
    $caught = true;
}
$tests[] = ['Can be caught as Exception', $caught];

// Test 8: Can be caught specifically
$caughtSpecific = false;
try {
    throw new RequestTimeoutException();
} catch (RequestTimeoutException $e) {
    $caughtSpecific = true;
}
$tests[] = ['Can be caught as RequestTimeoutException', $caughtSpecific];

// Test 9: Error code is in valid JSON-RPC server error range (-32000 to -32099)
$code = RequestTimeoutException::ERROR_CODE;
$result = $code >= -32099 && $code <= -32000;
$tests[] = ['Error code in JSON-RPC server error range', $result];

// Test 10: Empty previous is null
$exception = new RequestTimeoutException('Test', null);
$result = $exception->getPrevious() === null;
$tests[] = ['Null previous returns null', $result];

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
