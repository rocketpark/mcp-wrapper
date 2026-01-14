<?php

namespace rocketpark\mcpwrapper\attributes;

use Attribute;

/**
 * Marks a method as an MCP tool
 * 
 * Use this attribute to register methods as callable MCP tools.
 * The method will be automatically discovered by the ToolRegistry.
 * 
 * @example
 * #[Tool(
 *     name: 'get_entry_by_id',
 *     description: 'Get entry by ID bypassing GraphQL permissions',
 *     inputSchema: ['type' => 'object', 'properties' => [...]]
 * )]
 * public function getEntryById(int $id): array { }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    /**
     * @param string $name Tool name (e.g., 'get_entry_by_id')
     * @param string $description Human-readable description
     * @param array $inputSchema JSON Schema for input parameters
     * @param bool $dangerous Whether tool performs dangerous operations
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema = [],
        public bool $dangerous = false,
    ) {}
}
