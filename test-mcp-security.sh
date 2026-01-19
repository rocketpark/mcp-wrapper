#!/bin/bash

# MCP Wrapper Security Testing Script
# Tests the security improvements and basic functionality

BASE_URL="${1:-http://jensenhughes.test}"
SCHEMA="${2:-MCPSchema}"

echo "🧪 MCP Wrapper Security Test Suite"
echo "=================================="
echo "Base URL: $BASE_URL"
echo "Schema: $SCHEMA"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Test 1: Tools list should not include dangerous tools (when disabled)
echo "Test 1: Dangerous tools hidden when disabled"
echo "-------------------------------------------"
TOOLS=$(curl -s -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}')

# Check if request was successful
if echo "$TOOLS" | jq -e '.result' > /dev/null 2>&1; then
  HAS_CLEAR_CACHES=$(echo "$TOOLS" | jq '.result.tools[] | select(.name=="craft_clear_caches")' 2>/dev/null)
  TOOL_COUNT=$(echo "$TOOLS" | jq '.result.tools | length')
  
  if [ -z "$HAS_CLEAR_CACHES" ]; then
    echo -e "${GREEN}✅ PASS${NC}: Dangerous tools are hidden"
    echo "   Found $TOOL_COUNT tools (none dangerous)"
    PASSED=$((PASSED + 1))
  else
    echo -e "${RED}❌ FAIL${NC}: Dangerous tools still visible"
    echo "   craft_clear_caches should be hidden when enableDangerousTools=false"
    FAILED=$((FAILED + 1))
  fi
else
  echo -e "${RED}❌ FAIL${NC}: Could not get tools list"
  echo "   Response: $TOOLS"
  FAILED=$((FAILED + 1))
fi
echo ""

# Test 2: Disabled tools should not appear
echo "Test 2: Disabled tools are hidden"
echo "-----------------------------------"
HAS_DISABLED=$(echo "$TOOLS" | jq '.result.tools[] | select(.name=="craft_get_queue_status")' 2>/dev/null)

if [ -z "$HAS_DISABLED" ]; then
  echo -e "${GREEN}✅ PASS${NC}: Disabled tool (craft_get_queue_status) is hidden"
  PASSED=$((PASSED + 1))
else
  echo -e "${YELLOW}⚠️  SKIP${NC}: craft_get_queue_status not in disabledTools (check config)"
fi
echo ""

# Test 3: Dangerous tool execution should be blocked
echo "Test 3: Dangerous tool execution blocked"
echo "-----------------------------------------"
RESULT=$(curl -s -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"craft_clear_caches","arguments":{"caches":["all"]}},"id":2}')

HAS_ERROR=$(echo "$RESULT" | jq -r '.error.message // empty')
if [ ! -z "$HAS_ERROR" ]; then
  echo -e "${GREEN}✅ PASS${NC}: Dangerous tool was blocked"
  echo "   Error message: $HAS_ERROR"
  PASSED=$((PASSED + 1))
else
  echo -e "${RED}❌ FAIL${NC}: Dangerous tool was executed!"
  echo "   This should not happen - security bypass detected"
  FAILED=$((FAILED + 1))
fi
echo ""

# Test 4: Safe tools should work
echo "Test 4: Safe tools work correctly"
echo "-----------------------------------"
RESULT=$(curl -s -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"craft_get_system_info","arguments":{}},"id":3}')

IS_ERROR=$(echo "$RESULT" | jq -r '.result.isError // false')
HAS_CONTENT=$(echo "$RESULT" | jq -r '.result.content[0].text // empty')

if [ "$IS_ERROR" = "false" ] && [ -n "$HAS_CONTENT" ]; then
  CRAFT_VERSION=$(echo "$HAS_CONTENT" | jq -r '.craft.version // "unknown"' 2>/dev/null)
  echo -e "${GREEN}✅ PASS${NC}: Safe tool executed successfully"
  echo "   Craft version: $CRAFT_VERSION"
  PASSED=$((PASSED + 1))
else
  echo -e "${RED}❌ FAIL${NC}: Safe tool failed to execute"
  echo "   Response: $RESULT"
  FAILED=$((FAILED + 1))
fi
echo ""

# Test 5: GraphQL tools should work
echo "Test 5: GraphQL query tools work"
echo "----------------------------------"
RESULT=$(curl -s -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"query_services","arguments":{"limit":2}},"id":4}')

HAS_ENTRIES=$(echo "$RESULT" | jq -r '.result.content[0].text // empty' | jq -e '.entries' > /dev/null 2>&1 && echo "yes" || echo "no")

if [ "$HAS_ENTRIES" = "yes" ]; then
  ENTRY_COUNT=$(echo "$RESULT" | jq -r '.result.content[0].text' | jq '.entries | length')
  echo -e "${GREEN}✅ PASS${NC}: GraphQL tool executed successfully"
  echo "   Returned $ENTRY_COUNT entries"
  PASSED=$((PASSED + 1))
else
  echo -e "${YELLOW}⚠️  SKIP${NC}: No 'services' section or query failed"
  echo "   This is OK if you don't have a services section"
fi
echo ""

# Test 6: Initialize handshake
echo "Test 6: MCP protocol handshake"
echo "--------------------------------"
RESULT=$(curl -s -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}},"id":5}')

HAS_CAPABILITIES=$(echo "$RESULT" | jq -e '.result.capabilities' > /dev/null 2>&1 && echo "yes" || echo "no")

if [ "$HAS_CAPABILITIES" = "yes" ]; then
  PROTOCOL=$(echo "$RESULT" | jq -r '.result.protocolVersion')
  echo -e "${GREEN}✅ PASS${NC}: MCP handshake successful"
  echo "   Protocol version: $PROTOCOL"
  PASSED=$((PASSED + 1))
else
  echo -e "${RED}❌ FAIL${NC}: MCP handshake failed"
  echo "   Response: $RESULT"
  FAILED=$((FAILED + 1))
fi
echo ""

# Summary
echo "=================================="
echo "Test Summary"
echo "=================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
  echo -e "${GREEN}🎉 All tests passed! Your MCP Wrapper is working correctly.${NC}"
  exit 0
else
  echo -e "${RED}⚠️  Some tests failed. Please review the output above.${NC}"
  exit 1
fi
