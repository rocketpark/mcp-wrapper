#!/usr/bin/env node

/**
 * MCP Client for Service Curator
 * Connects Claude Desktop to the servicecurator.com MCP server
 */

const readline = require('readline');

const BASE_URL = 'https://servicecurator.com';
const SCHEMA = 'public'; // Change this if you have a different schema configured

// JSON-RPC 2.0 handler
async function handleMCPRequest(request) {
  try {
    const response = await fetch(`${BASE_URL}/actions/mcp-wrapper/mcp/index/${SCHEMA}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(request)
    });

    return await response.json();
  } catch (error) {
    return {
      jsonrpc: '2.0',
      id: request.id,
      error: {
        code: -32603,
        message: `Internal error: ${error.message}`
      }
    };
  }
}

// Read JSON-RPC requests from stdin and write responses to stdout
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
  terminal: false
});

rl.on('line', async (line) => {
  try {
    const request = JSON.parse(line);
    const response = await handleMCPRequest(request);
    console.log(JSON.stringify(response));
  } catch (error) {
    console.error(JSON.stringify({
      jsonrpc: '2.0',
      id: null,
      error: {
        code: -32700,
        message: 'Parse error'
      }
    }));
  }
});
