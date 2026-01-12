#!/bin/bash
# Test script to verify Matrix field fix is working

echo "Testing officeLocations (has Matrix fields)..."
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer b8p18Vou0hDttkkd_cUHSAyXuIyg9U6x' \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "query_officeLocations",
      "arguments": {"limit": 2}
    },
    "id": 1
  }' | python3 -m json.tool

echo -e "\n\nTesting ourTeam (has Matrix fields)..."
curl -s 'https://jensenhughes3.on-forge.com/actions/mcp-wrapper/mcp/index?schemaHandle=jensenhughes' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer b8p18Vou0hDttkkd_cUHSAyXuIyg9U6x' \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "query_ourTeam",
      "arguments": {"limit": 2}
    },
    "id": 1
  }' | python3 -m json.tool
