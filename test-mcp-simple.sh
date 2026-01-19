#!/bin/bash

# Simple MCP Test - Just checks if the endpoint responds
# Usage: ./test-mcp-simple.sh [base-url] [schema]

BASE_URL="${1:-http://jensenhughes3.test}"
SCHEMA="${2:-MCPSchema}"

echo "🔍 Simple MCP Connection Test"
echo "=============================="
echo "Base URL: $BASE_URL"
echo "Schema: $SCHEMA"
echo ""

# Test 1: Check if endpoint is accessible
echo "Test 1: Can we reach the MCP endpoint?"
echo "--------------------------------------"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}},"id":1}')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

echo "HTTP Status: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ Endpoint is accessible"
  echo ""
  echo "Response:"
  echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
else
  echo "❌ Endpoint returned error"
  echo ""
  echo "Response:"
  echo "$BODY"
fi

echo ""
echo ""

# Test 2: Get tools list
echo "Test 2: Can we get the tools list?"
echo "-----------------------------------"
TOOLS_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/mcp/$SCHEMA" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}')

HTTP_CODE=$(echo "$TOOLS_RESPONSE" | tail -n1)
BODY=$(echo "$TOOLS_RESPONSE" | sed '$d')

echo "HTTP Status: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ]; then
  if echo "$BODY" | jq -e '.result' > /dev/null 2>&1; then
    TOOL_COUNT=$(echo "$BODY" | jq '.result.tools | length')
    echo "✅ Got tools list successfully"
    echo "   Tool count: $TOOL_COUNT"
    echo ""
    echo "Available tools:"
    echo "$BODY" | jq -r '.result.tools[] | "  - \(.name): \(.description)"' 2>/dev/null
  else
    echo "❌ Response doesn't contain expected data"
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
  fi
else
  echo "❌ Request failed"
  echo "$BODY"
fi

echo ""
echo "✅ Test complete!"
