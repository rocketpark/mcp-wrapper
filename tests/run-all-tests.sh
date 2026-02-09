#!/bin/bash
#
# Run all MCP Wrapper unit tests
# Usage: ./tests/run-all-tests.sh
#

set -e
cd "$(dirname "$0")"

echo "========================================"
echo "MCP Wrapper Unit Test Suite"
echo "========================================"
echo ""

TOTAL_PASSED=0
TOTAL_FAILED=0

run_test() {
    local test_file=$1
    local test_name=$2

    echo "----------------------------------------"
    echo "Running: $test_name"
    echo "----------------------------------------"

    if php "$test_file"; then
        echo ""
    else
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
        echo ""
    fi
}

# Run all PHP test files
run_test "test-ip-validator.php" "IP Validator (IPv4)"
run_test "test-ip-validator-ipv6.php" "IP Validator (IPv6 CIDR)"
run_test "test-request-timeout-exception.php" "RequestTimeoutException"
run_test "test-tool-registry.php" "Tool Registry"
run_test "test-argument-mapping.php" "Argument Mapping"
run_test "test-graphql-sanitization.php" "GraphQL Sanitization"
run_test "test-site-settings.php" "Site Settings Configuration (v2.7.2)"
run_test "test-entry-tools-urls.php" "EntryTools URL Generation (v2.7.2)"

echo "========================================"
echo "Test Suite Complete"
echo "========================================"

if [ $TOTAL_FAILED -eq 0 ]; then
    echo "✓ All test files passed!"
    exit 0
else
    echo "✗ $TOTAL_FAILED test file(s) had failures"
    exit 1
fi
