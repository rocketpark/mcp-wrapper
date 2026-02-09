#!/bin/bash
# Run all v2.7.2 site settings tests
#
# Usage: ./tests/run-site-settings-tests.sh

echo "=========================================="
echo "MCP Wrapper v2.7.2 - Site Settings Tests"
echo "=========================================="
echo ""

TESTS_PASSED=0
TESTS_FAILED=0

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

run_test() {
    local test_file=$1
    local test_name=$2
    
    echo "Running: $test_name"
    echo "----------------------------------------"
    
    if php "$test_file"; then
        echo -e "${GREEN}✅ PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ FAILED${NC}"
        ((TESTS_FAILED++))
    fi
    
    echo ""
}

# Run tests
run_test "tests/test-site-settings.php" "Site Settings Configuration"
run_test "tests/test-entry-tools-urls.php" "EntryTools URL Generation"
run_test "tests/test-jh-config.php" "Jensen Hughes Config"

# Summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo -e "Failed: ${RED}${TESTS_FAILED}${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed! Ready for deployment.${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed. Please review.${NC}"
    exit 1
fi
