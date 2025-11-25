# MCP-Wrapper Changelog

All notable changes to this project will be documented in this file.

## 2.0.0 - 2025-11-25

### Breaking Changes

This release completely rewrites the plugin to implement the official Model Context Protocol specification (2025-06-18).

### Added


- **McpServerService**: Full JSON-RPC 2.0 server implementing MCP spec
- **McpController**: New controller handling MCP protocol requests at `/actions/mcp-wrapper/mcp/index/{schema}`
- **MCP Protocol Support**:
  - `initialize`: Capability negotiation and handshake
  - `tools/list`: Dynamic tool discovery from Craft sections
  - `tools/call`: Execute GraphQL queries via tool calls
- **Proper MCP Tool Schema**: Tools now include JSON Schema `inputSchema` definitions
- **GraphQL Query Execution**: Tools execute real queries against Craft GraphQL API
- **DEPLOYMENT.md**: Comprehensive guide for Laravel Forge deployment
- **Improved Documentation**: README updated with MCP protocol examples

### Changed


- **Architecture**: Shifted from static manifest generation to dynamic JSON-RPC server
- **Endpoints**: Primary endpoint is now JSON-RPC compliant (old manifest endpoint still available for backwards compatibility)
- **Tool Format**: Tools follow official MCP spec with proper inputSchema

### Technical Details


- Tools are generated dynamically from Craft sections
- Each section becomes a queryable MCP tool
- GraphQL queries support limit, offset, search, and ID filtering
- Multi-schema support via GraphQL bearer tokens

### Migration Notes

If you were using the old manifest endpoint (`/actions/mcpwrapper/manifest/{schema}`), it still works but is deprecated. Update MCP clients to use the new JSON-RPC endpoint:

```text
POST /actions/mcp-wrapper/mcp/index/{schema}
```

## 1.0.0 - 2025-11-25

**Initial Build** (Legacy Manifest-Based Approach)

### Features

- ManifestBuilderService for GraphQL schema introspection
- ManifestController providing static manifest endpoint
- UtilityController for CP utility
- McpManifestUtility for viewing/rebuilding manifests in CP
- File-based caching in `@storage/runtime/mcp/`
- Auto-cache clearing on project config/GraphQL schema changes
- Field type mapping (PlainText, Lightswitch, Date, Relations, etc.)
- Relationship metadata extraction from relational fields
