# MCP Wrapper for Craft CMS

## Overview

MCP Wrapper is a Craft CMS plugin that automatically generates **MCP (Model Context Protocol) manifests** for Craft CMS GraphQL APIs. This enables MCP clients (typically AI/LLM tools) to discover and interact with Craft CMS GraphQL endpoints by providing structured metadata about available entry types, fields, and relationships.

## Key Features

### Automatic Manifest Generation

The plugin introspects your Craft CMS GraphQL schemas and automatically generates MCP manifests that describe:

- Available entry types (sections)
- Field definitions and their types
- Relationship metadata for entries, categories, assets, users, and tags

### Field Type Mapping

Maps Craft CMS field types to MCP-compatible types:

- PlainText → `string`
- Lightswitch → `boolean`
- Date → `datetime`
- Number → `number`
- Dropdown/RadioButtons → `enum`
- Relationship fields → `relation` (with detailed metadata)

### Relationship Handling

Captures comprehensive relationship metadata including:

- Element types (entry, category, asset, user, tag)
- Source restrictions (which sections, volumes, or groups are allowed)

### Intelligent Caching

Generated manifests are cached for performance and automatically cleared when:

- Project config changes
- GraphQL schemas are updated

### Control Panel Utility

Provides a built-in utility (`MCP Manifest Manager`) in the Craft CP that allows you to:

- View existing manifests
- Rebuild manifests on demand
- Monitor manifest status

## Use Case

This plugin enables MCP clients to automatically discover and query Craft CMS content through GraphQL by providing structured metadata about available entry types, fields, and relationships. This is particularly useful for AI/LLM integrations that need to understand the structure of your Craft CMS GraphQL API.