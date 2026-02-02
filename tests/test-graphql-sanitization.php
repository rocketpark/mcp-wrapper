<?php
/**
 * Test script for GraphQL string escaping and input sanitization
 * Run: php tests/test-graphql-sanitization.php
 *
 * Tests for:
 * - escapeGraphQLString() - GraphQL string escaping using JSON encoding
 * - sanitizeStringInput() - Whitelist-based input validation
 */

// Manually include the class
require_once __DIR__ . '/../src/support/GraphQLSanitizer.php';

use rocketpark\mcpwrapper\support\GraphQLSanitizer;

echo "Testing GraphQL Sanitization Functions...\n";
echo str_repeat("=", 60) . "\n\n";

$passedTests = 0;
$totalTests = 0;

/**
 * Helper function to run a test
 */
function runTest(string $name, callable $test): bool
{
    global $passedTests, $totalTests;
    $totalTests++;

    try {
        $result = $test();
        if ($result === true) {
            echo "  {$totalTests}. {$name}: PASS\n";
            $passedTests++;
            return true;
        } else {
            echo "  {$totalTests}. {$name}: FAIL - {$result}\n";
            return false;
        }
    } catch (Throwable $e) {
        echo "  {$totalTests}. {$name}: FAIL - Exception: {$e->getMessage()}\n";
        return false;
    }
}

/**
 * Helper to test that an exception is thrown
 */
function expectException(callable $fn, string $expectedMessage = null): bool
{
    try {
        $fn();
        return false; // Should have thrown
    } catch (Exception $e) {
        if ($expectedMessage !== null) {
            return str_contains($e->getMessage(), $expectedMessage);
        }
        return true;
    }
}

// ============================================================
// escapeGraphQLString() Tests
// ============================================================
echo "escapeGraphQLString() Tests:\n";
echo str_repeat("-", 40) . "\n";

runTest("Basic string unchanged", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("hello world");
    return $result === "hello world" ? true : "Got: {$result}";
});

runTest("Escapes double quotes", function() {
    $result = GraphQLSanitizer::escapeGraphQLString('say "hello"');
    return $result === 'say \\"hello\\"' ? true : "Got: {$result}";
});

runTest("Escapes backslashes", function() {
    $result = GraphQLSanitizer::escapeGraphQLString('path\\to\\file');
    return $result === 'path\\\\to\\\\file' ? true : "Got: {$result}";
});

runTest("Escapes newlines", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("line1\nline2");
    return $result === 'line1\\nline2' ? true : "Got: {$result}";
});

runTest("Escapes tabs", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("col1\tcol2");
    return $result === 'col1\\tcol2' ? true : "Got: {$result}";
});

runTest("Escapes carriage returns", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("line1\rline2");
    return $result === 'line1\\rline2' ? true : "Got: {$result}";
});

runTest("Handles Unicode characters (preserved)", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("Hello cafe");
    return $result === "Hello cafe" ? true : "Got: {$result}";
});

runTest("Handles CJK characters", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("Hello World");
    return $result === "Hello World" ? true : "Got: {$result}";
});

runTest("Escapes control characters", function() {
    // Form feed character
    $result = GraphQLSanitizer::escapeGraphQLString("test\fchar");
    return str_contains($result, '\\') ? true : "Control char not escaped: {$result}";
});

runTest("Combined escaping", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("Say \"hi\"\nand\\bye");
    return $result === 'Say \\"hi\\"\\nand\\\\bye' ? true : "Got: {$result}";
});

runTest("Empty string", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("");
    return $result === "" ? true : "Got: {$result}";
});

runTest("Single quote preserved", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("it's fine");
    return $result === "it's fine" ? true : "Got: {$result}";
});

// GraphQL injection prevention tests
runTest("GraphQL fragment syntax safe in string context", function() {
    // ...fragmentName in string value is just literal text
    $result = GraphQLSanitizer::escapeGraphQLString("test...value");
    return $result === "test...value" ? true : "Got: {$result}";
});

runTest("GraphQL introspection safe in string context", function() {
    // __typename in string value is just literal text
    $result = GraphQLSanitizer::escapeGraphQLString("test__typename");
    return $result === "test__typename" ? true : "Got: {$result}";
});

runTest("Curly braces safe in string context", function() {
    $result = GraphQLSanitizer::escapeGraphQLString("query{id}");
    return $result === "query{id}" ? true : "Got: {$result}";
});

runTest("Quote injection prevented", function() {
    // Attempt to break out of string with quote
    $result = GraphQLSanitizer::escapeGraphQLString('test" } injection { "');
    // Quotes should be escaped, preventing breakout
    return str_contains($result, '\\"') ? true : "Quotes not escaped: {$result}";
});

echo "\n";

// ============================================================
// sanitizeStringInput() Tests
// ============================================================
echo "sanitizeStringInput() Tests:\n";
echo str_repeat("-", 40) . "\n";

// Valid inputs that should pass
runTest("Basic alphanumeric", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("Hello World 123");
    return $result === "Hello World 123" ? true : "Got: {$result}";
});

runTest("Unicode letters allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("Bonjour cafe Munich");
    return $result === "Bonjour cafe Munich" ? true : "Got: {$result}";
});

runTest("Safe punctuation allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("Hello, World! How are you?");
    return $result === "Hello, World! How are you?" ? true : "Got: {$result}";
});

runTest("Date format ISO 8601", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("2024-01-15T14:30:00Z");
    return $result === "2024-01-15T14:30:00Z" ? true : "Got: {$result}";
});

runTest("Quotes allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput('Say "hello" and \'bye\'');
    return $result === 'Say "hello" and \'bye\'' ? true : "Got: {$result}";
});

runTest("Common operators allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("price >= 100");
    return str_contains($result, ">=") ? true : "Got: {$result}";
});

runTest("Email-like patterns", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("contact@example.com");
    return $result === "contact@example.com" ? true : "Got: {$result}";
});

runTest("Path-like patterns", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("path/to/resource");
    return $result === "path/to/resource" ? true : "Got: {$result}";
});

runTest("Percentage and dollar signs", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("50% off at \$100");
    return str_contains($result, "%") && str_contains($result, "\$") ? true : "Got: {$result}";
});

runTest("Parentheses allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("Test (value) here");
    return $result === "Test (value) here" ? true : "Got: {$result}";
});

// Invalid inputs that should be blocked
runTest("Blocks curly braces", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("query{id}"), "invalid characters");
});

runTest("Blocks square brackets", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("items[0]"), "invalid characters");
});

runTest("Blocks backticks", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("test`injection`"), "invalid characters");
});

runTest("Blocks triple dots (fragment spread)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("test...fragmentName"), "invalid character sequence");
});

runTest("Blocks double underscore (introspection)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("get__typename"), "invalid character sequence");
});

runTest("Blocks newlines (log injection)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("line1\nline2"), "invalid characters");
});

runTest("Blocks tabs (log injection)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("col1\tcol2"), "invalid characters");
});

runTest("Blocks carriage returns (log injection)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("line1\rline2"), "invalid characters");
});

runTest("Removes null bytes", function() {
    // Null bytes are removed before validation, so string should pass
    $result = GraphQLSanitizer::sanitizeStringInput("test\x00value");
    return $result === "testvalue" ? true : "Got: {$result}";
});

runTest("Blocks tilde", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("test~value"), "invalid characters");
});

runTest("Blocks caret", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput("test^value"), "invalid characters");
});

// Edge cases
runTest("Empty string blocked (requires at least one char)", function() {
    return expectException(fn() => GraphQLSanitizer::sanitizeStringInput(""), "invalid characters");
});

runTest("Space-only string allowed", function() {
    $result = GraphQLSanitizer::sanitizeStringInput("   ");
    return $result === "   " ? true : "Got: {$result}";
});

runTest("Warning logger callback called on error", function() {
    $warningCalled = false;
    $warningMessage = '';
    $logger = function($msg, $cat) use (&$warningCalled, &$warningMessage) {
        $warningCalled = true;
        $warningMessage = $msg;
    };

    try {
        GraphQLSanitizer::sanitizeStringInput("test{injection}", $logger);
    } catch (Exception $e) {
        // Expected
    }

    return $warningCalled && str_contains($warningMessage, "disallowed characters") ? true : "Warning not called or wrong message";
});

echo "\n";

// ============================================================
// isValidHandle() Tests
// ============================================================
echo "isValidHandle() Tests:\n";
echo str_repeat("-", 40) . "\n";

runTest("Valid simple handle", function() {
    return GraphQLSanitizer::isValidHandle("myHandle") === true ? true : "Should be valid";
});

runTest("Valid handle with underscore", function() {
    return GraphQLSanitizer::isValidHandle("my_handle") === true ? true : "Should be valid";
});

runTest("Valid handle with hyphen", function() {
    return GraphQLSanitizer::isValidHandle("my-handle") === true ? true : "Should be valid";
});

runTest("Valid handle with numbers", function() {
    return GraphQLSanitizer::isValidHandle("handle123") === true ? true : "Should be valid";
});

runTest("Invalid: starts with number", function() {
    return GraphQLSanitizer::isValidHandle("123handle") === false ? true : "Should be invalid";
});

runTest("Invalid: contains space", function() {
    return GraphQLSanitizer::isValidHandle("my handle") === false ? true : "Should be invalid";
});

runTest("Invalid: contains special char", function() {
    return GraphQLSanitizer::isValidHandle("my@handle") === false ? true : "Should be invalid";
});

runTest("Invalid: empty string", function() {
    return GraphQLSanitizer::isValidHandle("") === false ? true : "Should be invalid";
});

echo "\n";

// ============================================================
// MAX_STRING_LENGTH constant test
// ============================================================
echo "Constants Tests:\n";
echo str_repeat("-", 40) . "\n";

runTest("MAX_STRING_LENGTH is 500", function() {
    return GraphQLSanitizer::MAX_STRING_LENGTH === 500 ? true : "Got: " . GraphQLSanitizer::MAX_STRING_LENGTH;
});

echo "\n";

// ============================================================
// Summary
// ============================================================
echo str_repeat("=", 60) . "\n";
echo "Results: {$passedTests}/{$totalTests} tests passed\n";

if ($passedTests === $totalTests) {
    echo "All tests passed!\n";
    exit(0);
} else {
    $failed = $totalTests - $passedTests;
    echo "{$failed} test(s) failed\n";
    exit(1);
}
