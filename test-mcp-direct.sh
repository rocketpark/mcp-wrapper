#!/bin/bash

# Simple MCP Endpoint Diagnostic Script
# Tests the MCP endpoint with verbose output to see actual responses

BASE_URL="${1:-http://jensenhughes3.test}"
SCHEMA="${2:-MCPSchema}"
ENDPOINT="${BASE_URL}/mcp/${SCHEMA}"

echo "🔍 MCP Endpoint Diagnostic"
echo "=========================="
echo "URL: $ENDPOINT"
echo ""

echo "Test 1: Basic POST request (tools/list)"
echo "----------------------------------------"
curl -v -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list"
  }' 2>&1 | head -100

echo ""
echo ""
echo "Test 2: Check if endpoint is accessible"
echo "---------------------------------------"
curl -I "$ENDPOINT" 2>&1 | head -20

echo ""
echo ""
echo "Test 3: Simple GET request"
echo "-------------------------"
curl -v "$ENDPOINT" 2>&1 | head -50
