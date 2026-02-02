#!/bin/bash

# PHPUnit Test Runner for MCP Wrapper
# Runs unit tests with various options

set -e  # Exit on error

echo "========================================="
echo "MCP Wrapper Unit Test Runner"
echo "========================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Change to project directory
cd "$(dirname "$0")/.."

# Parse arguments
COVERAGE=false
FILTER=""
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --coverage)
            COVERAGE=true
            shift
            ;;
        --filter)
            FILTER="$2"
            shift 2
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--coverage] [--filter TestName] [--verbose]"
            exit 1
            ;;
    esac
done

# Build command
CMD="vendor/bin/phpunit tests/Unit --colors=always"

if [ "$FILTER" != "" ]; then
    CMD="$CMD --filter $FILTER"
    echo -e "${YELLOW}Running filtered tests: $FILTER${NC}"
    echo ""
fi

if [ "$COVERAGE" = true ]; then
    echo -e "${YELLOW}Generating coverage report...${NC}"
    CMD="$CMD --coverage-html tests/coverage --coverage-text"
    echo ""
fi

if [ "$VERBOSE" = true ]; then
    CMD="$CMD --verbose"
fi

# Run tests
echo "Command: $CMD"
echo ""
eval $CMD

echo ""
if [ "$COVERAGE" = true ]; then
    echo -e "${GREEN}Coverage report generated: tests/coverage/index.html${NC}"
fi

echo ""
echo -e "${GREEN}Tests complete!${NC}"
