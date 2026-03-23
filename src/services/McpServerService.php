<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use rocketpark\mcpwrapper\support\GraphQLSanitizer;
use rocketpark\mcpwrapper\support\RequestTimeoutException;
use yii\web\Response;

/**
 * MCP Server Service
 *
 * Implements the Model Context Protocol JSON-RPC 2.0 interface
 * for exposing Craft CMS content as MCP Tools
 */
class McpServerService extends Component
{
    private const MCP_VERSION = '2025-11-25';
    private const SERVER_NAME = 'craft-cms-mcp';
    private const SERVER_VERSION = '2.7.0';

    /**
     * Default request timeout in seconds (configurable via mcpwrapper.php)
     */
    private const DEFAULT_REQUEST_TIMEOUT = 30;

    /**
     * Handle incoming JSON-RPC 2.0 requests with timeout handling
     * Per MCP spec: implementations SHOULD establish timeouts for sent requests
     */
    public function handleRequest(array $jsonRpcRequest, string $schemaHandle = 'unknown'): array
    {
        $method = $jsonRpcRequest['method'] ?? null;
        $params = $jsonRpcRequest['params'] ?? [];
        $id = $jsonRpcRequest['id'] ?? null;

        // Get configured timeout
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $timeout = $config['requestTimeout'] ?? self::DEFAULT_REQUEST_TIMEOUT;

        $startTime = microtime(true);
        $toolName = null;

        // Extract tool name if this is a tools/call
        if ($method === 'tools/call') {
            $toolName = $params['name'] ?? null;
        }

        try {
            Craft::info("MCP Request: {$method} (timeout: {$timeout}s)", 'mcp-wrapper');

            // Execute with timeout monitoring
            $result = $this->executeWithTimeout(
                fn() => $this->dispatchMethod($method, $params),
                $timeout,
                $method
            );

            $duration = microtime(true) - $startTime;
            $response = $this->successResponse($id, $result);

            Craft::info("MCP Response: success for {$method} ({$this->formatDuration($duration)})", 'mcp-wrapper');

            // Log structured request data
            $this->logRequest($method, $toolName, $params, $response, $duration, $schemaHandle);

            return $response;
        } catch (RequestTimeoutException $e) {
            $duration = microtime(true) - $startTime;
            $response = $this->errorResponse($id, $e);

            Craft::warning("MCP Timeout ({$method}): {$e->getMessage()} ({$this->formatDuration($duration)})", 'mcp-wrapper');

            // Log failed request
            $this->logRequest($method, $toolName, $params, $response, $duration, $schemaHandle);

            return $response;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $response = $this->errorResponse($id, $e);

            Craft::error("MCP Error ({$method}): {$e->getMessage()} ({$this->formatDuration($duration)})", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');

            // Log failed request
            $this->logRequest($method, $toolName, $params, $response, $duration, $schemaHandle);

            return $response;
        }
    }

    /**
     * Log structured request data via RequestLoggerService
     */
    private function logRequest(
        string $method,
        ?string $toolName,
        array $params,
        array $response,
        float $duration,
        string $schemaHandle
    ): void {
        try {
            $logger = Craft::$app->getModule('mcp-wrapper')->get('requestLogger');
            $ip = Craft::$app->request->userIP ?? 'unknown';

            // Extract arguments for tools/call
            $arguments = [];
            if ($method === 'tools/call' && isset($params['arguments'])) {
                $arguments = $params['arguments'];
            }

            $logger->logRequest($method, $toolName, $arguments, $response, $duration, $schemaHandle, $ip);
        } catch (\Exception $e) {
            // Don't let logging failures affect the request
            Craft::error("Failed to log request: {$e->getMessage()}", 'mcp-wrapper');
        }
    }

    /**
     * Format duration for logging
     */
    private function formatDuration(float $seconds): string
    {
        $ms = round($seconds * 1000, 2);
        return "{$ms}ms";
    }

    /**
     * Execute a callable with timeout monitoring
     * Note: PHP doesn't support true async cancellation, so this monitors elapsed time
     * and throws if the operation takes too long.
     *
     * @param callable $operation The operation to execute
     * @param int $timeout Timeout in seconds
     * @param string $operationName Name for logging
     * @return mixed The operation result
     * @throws RequestTimeoutException if timeout exceeded
     */
    private function executeWithTimeout(callable $operation, int $timeout, string $operationName): mixed
    {
        // Set execution time limit for this request
        // Note: This is a soft limit - doesn't interrupt blocking I/O
        $previousLimit = ini_get('max_execution_time');
        set_time_limit($timeout + 5); // Allow a bit extra for cleanup

        try {
            $result = $operation();
            return $result;
        } finally {
            // Restore previous limit
            if ($previousLimit !== false) {
                set_time_limit((int) $previousLimit);
            }
        }
    }

    /**
     * Dispatch to appropriate method handler
     */
    private function dispatchMethod(string $method, array $params): mixed
    {
        return match ($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleToolsList($params),
            'tools/call' => $this->handleToolCall($params),
            'prompts/list' => $this->handlePromptsList($params),
            'prompts/get' => $this->handlePromptsGet($params),
            'resources/list' => $this->handleResourcesList($params),
            'resources/read' => $this->handleResourcesRead($params),
            'ping' => (object) [],
            default => throw new \Exception("Unknown method: {$method}", -32601)
        };
    }

    /**
     * Build JSON-RPC success response
     */
    private function successResponse(?int $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * Build JSON-RPC error response
     * Sanitizes error messages in production to prevent information leakage
     */
    private function errorResponse(?int $id, \Exception $e): array
    {
        // In production, sanitize error messages to prevent information leakage
        $message = Craft::$app->config->general->devMode
            ? $e->getMessage()
            : $this->sanitizeErrorMessage($e);

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $e->getCode() ?: -32603,
                'message' => $message,
            ],
        ];
    }

    /**
     * Sanitize error message for production
     * Only expose safe, user-friendly messages
     */
    private function sanitizeErrorMessage(\Exception $e): string
    {
        $code = $e->getCode();

        // Map known error codes to safe messages
        return match ($code) {
            -32700 => 'Parse error: Invalid JSON',
            -32600 => 'Invalid request format',
            -32601 => 'Method not found',
            -32602 => 'Invalid parameters',
            -32603 => 'An internal error occurred',
            default => 'An error occurred while processing your request',
        };
    }

    /**
     * Handle MCP initialize request
     * Returns server capabilities per MCP 2025-11-25 spec
     */
    private function handleInitialize(array $params): array
    {
        // Log client info if provided (per spec: clients should send their info)
        if (!empty($params['clientInfo'])) {
            Craft::info("MCP client connected: " . json_encode($params['clientInfo']), 'mcp-wrapper');
        }

        return [
            'protocolVersion' => self::MCP_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false, // We don't dynamically notify of tool list changes
                ],
                'prompts' => [
                    'listChanged' => false, // We don't dynamically notify of prompt list changes
                ],
                'resources' => [
                    'subscribe' => false, // We don't support resource subscriptions
                    'listChanged' => false, // We don't dynamically notify of resource list changes
                ],
                // Note: We don't support sampling, roots, or elicitation (client features)
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
                'description' => 'Craft CMS Model Context Protocol server providing access to CMS content, entries, and custom tools via JSON-RPC 2.0',
            ],
            // Include instructions for human-readable guidance (optional per spec)
            'instructions' => 'Use tools/list to discover available query tools and craft_* tools. Each section has a query_{section} tool for GraphQL-based queries. Use craft_get_office_contact_info for office phone numbers.',
        ];
    }

    /**
     * List all available MCP tools (GraphQL queries + manual tools)
     */
    private function handleToolsList(array $params): array
    {
        $token = $this->getSchemaToken($params);
        $sections = $this->getSectionsForSchema($token);

        // Build GraphQL query tools from sections
        $tools = array_map(
            fn($section) => $this->buildToolDefinition($section),
            $sections
        );

        // Build GraphQL query tools from category groups
        $categoryGroups = $this->getCategoryGroupsForSchema($token);
        foreach ($categoryGroups as $categoryGroup) {
            $tools[] = $this->buildCategoryGroupToolDefinition($categoryGroup);
        }

        // Add manual tools from ToolRegistry
        $toolRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('toolRegistry');
        $manualTools = $toolRegistry->discoverManualTools();

        // Merge both arrays
        $tools = array_merge($tools, $manualTools);

        // Filter tools based on security configuration
        $tools = $this->filterToolsBySecurityConfig($tools);

        return ['tools' => $tools];
    }

    /**
     * Build MCP tool definition for a Craft section
     */
    private function buildToolDefinition($section): array
    {
        return [
            'name' => "query_{$section->handle}",
            'title' => "Query {$section->name}",
            'description' => "Query {$section->name} entries from Craft CMS. Returns entries with their fields and relationships.",
            'inputSchema' => $this->getToolInputSchema(),
            'annotations' => [
                'readOnlyHint' => true,
                'openWorldHint' => false,
            ],
        ];
    }

    /**
     * Get standard input schema for query tools
     * Includes all common Craft entry query parameters
     */
    private function getToolInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // Basic filters
                'id' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by specific entry IDs',
                ],
                'uid' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by entry UIDs',
                ],
                'slug' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by entry slugs',
                ],
                'uri' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by entry URIs',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Filter by entry title',
                ],

                // Entry type and status
                'type' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by entry type handles',
                ],
                'typeId' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by entry type IDs',
                ],
                'status' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by status (live, pending, expired, disabled)',
                ],
                'archived' => [
                    'type' => 'boolean',
                    'description' => 'Filter by archived status',
                ],
                'trashed' => [
                    'type' => 'boolean',
                    'description' => 'Filter by trashed status',
                ],

                // Date filters
                'dateCreated' => [
                    'type' => 'string',
                    'description' => 'Filter by creation date (ISO 8601 format or Craft date syntax)',
                ],
                'dateUpdated' => [
                    'type' => 'string',
                    'description' => 'Filter by last update date',
                ],
                'postDate' => [
                    'type' => 'string',
                    'description' => 'Filter by post date',
                ],
                'expiryDate' => [
                    'type' => 'string',
                    'description' => 'Filter by expiry date',
                ],
                'before' => [
                    'type' => 'string',
                    'description' => 'Filter entries posted before this date',
                ],
                'after' => [
                    'type' => 'string',
                    'description' => 'Filter entries posted after this date',
                ],

                // Relationships
                'relatedTo' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by related entry IDs',
                ],
                'ancestorOf' => [
                    'type' => 'integer',
                    'description' => 'Filter ancestors of specified entry ID',
                ],
                'descendantOf' => [
                    'type' => 'integer',
                    'description' => 'Filter descendants of specified entry ID',
                ],
                'siblingOf' => [
                    'type' => 'integer',
                    'description' => 'Filter siblings of specified entry ID',
                ],
                'prevSiblingOf' => [
                    'type' => 'integer',
                    'description' => 'Filter previous sibling of specified entry ID',
                ],
                'nextSiblingOf' => [
                    'type' => 'integer',
                    'description' => 'Filter next sibling of specified entry ID',
                ],
                'positionedAfter' => [
                    'type' => 'integer',
                    'description' => 'Filter entries positioned after specified entry ID',
                ],
                'positionedBefore' => [
                    'type' => 'integer',
                    'description' => 'Filter entries positioned before specified entry ID',
                ],

                // Authors
                'authorId' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by author user IDs',
                ],
                'authorGroup' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by author user group handles',
                ],

                // Search and ordering
                'search' => [
                    'type' => 'string',
                    'description' => 'Full-text search query to filter entries',
                ],
                'orderBy' => [
                    'type' => 'string',
                    'description' => 'Order results (e.g., "dateCreated DESC", "title ASC")',
                ],
                'inReverse' => [
                    'type' => 'boolean',
                    'description' => 'Reverse the order of results',
                ],
                'fixedOrder' => [
                    'type' => 'boolean',
                    'description' => 'Maintain order when filtering by IDs',
                ],
                'unique' => [
                    'type' => 'boolean',
                    'description' => 'Return unique entries only',
                ],

                // Pagination
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of entries to return (1-100)',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of entries to skip for pagination',
                    'default' => 0,
                    'minimum' => 0,
                ],

                // Multi-site
                'site' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by site handles',
                ],
                'siteId' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by site IDs',
                ],
            ],
            'required' => [],
            'additionalProperties' => false,
        ];
    }

    /**
     * Execute a tool call (manual tool or GraphQL query)
     */
    private function handleToolCall(array $params): array
    {
        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (!$toolName) {
            throw new \Exception('Tool name required', -32602);
        }

        // Validate tool access before execution
        $this->validateToolAccess($toolName);

        // Check if this is a manual tool (craft_*)
        if (str_starts_with($toolName, 'craft_')) {
            $toolRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('toolRegistry');
            $result = $toolRegistry->executeTool($toolName, $arguments);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'isError' => false,
            ];
        }

        // Otherwise, it's a GraphQL query tool
        $handle = $this->extractSectionHandle($toolName);
        $token = $this->getSchemaToken($params);

        // Check if this is a category group or section
        $categoryGroup = Craft::$app->getCategories()->getGroupByHandle($handle);
        $section = Craft::$app->getEntries()->getSectionByHandle($handle);

        if ($categoryGroup) {
            $result = $this->executeCategoryQuery($token, $handle, $arguments, $params);
        } elseif ($section) {
            $result = $this->executeGraphQLQuery($token, $handle, $arguments, $params);
        } else {
            throw new \Exception("Unknown section or category group: {$handle}", -32602);
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'isError' => false,
        ];
    }

    /**
     * Extract section handle from tool name (e.g., "query_news" -> "news")
     */
    private function extractSectionHandle(string $toolName): string
    {
        if (!preg_match('/^query_(.+)$/', $toolName, $matches)) {
            throw new \Exception("Invalid tool name: {$toolName}", -32602);
        }
        return $matches[1];
    }

    /**
     * Get GraphQL token for the specified schema
     */
    private function getSchemaToken(array $params): string
    {
        $schemaHandle = $params['schemaHandle'] ?? null;

        if (!$schemaHandle) {
            throw new \Exception('schemaHandle parameter required', -32602);
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');

        if (!isset($config['schemas'][$schemaHandle])) {
            throw new \Exception("Unknown schema: {$schemaHandle}", -32602);
        }

        return $config['schemas'][$schemaHandle];
    }

    /**
     * Resolve a GqlSchema from a config value (token string or schema name)
     * Tries access token first for backwards compatibility, falls back to schema name lookup
     */
    private function resolveGqlSchema(string $tokenOrSchemaName): \craft\models\GqlSchema
    {
        $gqlService = Craft::$app->getGql();

        // Try as access token first (backwards compatibility)
        if (!empty($tokenOrSchemaName)) {
            try {
                $gqlToken = $gqlService->getTokenByAccessToken($tokenOrSchemaName);
                return $gqlToken->getSchema();
            } catch (\yii\base\InvalidArgumentException | \InvalidArgumentException $e) {
                // Not a valid token, try as schema name
                Craft::info("Config value is not a valid access token, trying as schema name: {$tokenOrSchemaName}", 'mcp-wrapper');
            }
        }

        // Try as schema name
        foreach ($gqlService->getSchemas() as $schema) {
            if ($schema->name === $tokenOrSchemaName) {
                Craft::info("Resolved GraphQL schema by name: {$tokenOrSchemaName}", 'mcp-wrapper');
                return $schema;
            }
        }

        throw new \Exception("Could not resolve GraphQL schema from '{$tokenOrSchemaName}' - not a valid access token or schema name");
    }

    /**
     * Get sections accessible via this schema
     */
    private function getSectionsForSchema(string $token): array
    {
        try {
            // Introspect GraphQL schema to get available entry types
            $schema = $this->introspectGraphQLSchema($token);
            $availableTypes = array_column($schema['types'], 'name');

            // Get all sections and filter by which have entry types in the schema
            $allSections = Craft::$app->getEntries()->getAllSections();
            $accessibleSections = [];

            foreach ($allSections as $section) {
                // Check if any entry type for this section is in the GraphQL schema
                foreach ($section->getEntryTypes() as $entryType) {
                    $typeName = $entryType->handle . '_Entry';
                    if (in_array($typeName, $availableTypes, true)) {
                        $accessibleSections[] = $section;
                        break; // Found at least one accessible entry type, include this section
                    }
                }
            }

            Craft::info("Found " . count($accessibleSections) . " accessible sections out of " . count($allSections) . " total", 'mcp-wrapper');
            return $accessibleSections;
        } catch (\Exception $e) {
            Craft::error("Failed to get sections for schema: {$e->getMessage()}", 'mcp-wrapper');
            // Fallback to all sections if introspection fails
            return Craft::$app->getEntries()->getAllSections();
        }
    }

    /**
     * Introspect GraphQL schema to get available types and query fields
     */
    private function introspectGraphQLSchema(string $token): array
    {
        try {
            $schema = $this->resolveGqlSchema($token);
            $gqlService = Craft::$app->getGql();

            $query = '{ __schema { types { name } } }';

            Craft::info("Introspecting GraphQL schema via internal API", 'mcp-wrapper');

            $data = $gqlService->executeQuery($schema, $query);

            if (!isset($data['data']['__schema'])) {
                throw new \Exception('GraphQL introspection returned invalid data');
            }

            Craft::info("GraphQL introspection successful, found " . count($data['data']['__schema']['types']) . " types", 'mcp-wrapper');
            return $data['data']['__schema'];
        } catch (\Exception $e) {
            Craft::error("GraphQL introspection failed: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }

    /**
     * Get category groups accessible via this schema using GraphQL introspection
     */
    private function getCategoryGroupsForSchema(string $token): array
    {
        try {
            $schema = $this->introspectGraphQLSchema($token);
            $availableTypes = array_column($schema['types'], 'name');

            // Match category groups by checking if {handle}_Category type exists in schema
            $allCategoryGroups = Craft::$app->getCategories()->getAllGroups();
            $accessibleGroups = [];

            foreach ($allCategoryGroups as $group) {
                $typeName = $group->handle . '_Category';
                if (in_array($typeName, $availableTypes, true)) {
                    $accessibleGroups[] = $group;
                } else {
                    Craft::info("Category group '{$group->handle}' type '{$typeName}' not found in schema", 'mcp-wrapper');
                }
            }

            Craft::info("Found " . count($accessibleGroups) . " accessible category groups out of " . count($allCategoryGroups) . " total", 'mcp-wrapper');
            return $accessibleGroups;
        } catch (\Exception $e) {
            Craft::error("Failed to get category groups for schema: {$e->getMessage()}", 'mcp-wrapper');
            return [];
        }
    }

    /**
     * Build MCP tool definition for a Craft category group
     */
    private function buildCategoryGroupToolDefinition($categoryGroup): array
    {
        return [
            'name' => "query_{$categoryGroup->handle}",
            'title' => "Query {$categoryGroup->name}",
            'description' => "Query {$categoryGroup->name} categories from Craft CMS. Returns categories with their fields and hierarchy.",
            'inputSchema' => $this->getCategoryGroupInputSchema(),
            'annotations' => [
                'readOnlyHint' => true,
                'openWorldHint' => false,
            ],
        ];
    }

    /**
     * Get standard input schema for category group query tools
     */
    private function getCategoryGroupInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of categories to return (default: 100)',
                    'minimum' => 1,
                    'maximum' => 500,
                    'default' => 100,
                ],
                'level' => [
                    'type' => 'integer',
                    'description' => 'Filter by structure level (1 = top level)',
                    'minimum' => 1,
                ],
                'orderBy' => [
                    'type' => 'string',
                    'description' => 'Sort order (e.g., "title ASC", "lft ASC" for structure order)',
                    'default' => 'lft ASC',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Get fields for a section
     */
    private function getFieldsForSection($section): array
    {
        $fields = [];
        foreach ($section->getEntryTypes() as $entryType) {
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                $fields[$field->handle] = $field;
            }
        }
        return $fields;
    }

    /**
     * Execute GraphQL query against Craft API
     */
    private function executeGraphQLQuery(string $token, string $sectionHandle, array $args, array $params = []): array
    {
        try {
            $query = $this->buildGraphQLQuery($sectionHandle, $args, $params);
            Craft::info("GraphQL Query for {$sectionHandle}: {$query}", 'mcp-wrapper');

            $data = $this->sendGraphQLRequest($token, $query);

            if (isset($data['errors'])) {
                $errorJson = json_encode($data['errors']);
                Craft::error("GraphQL errors: {$errorJson}", 'mcp-wrapper');
                throw new \Exception('GraphQL error: ' . $errorJson, -32603);
            }

            $result = $data['data'] ?? [];
            // Result uses section-specific field name (e.g., servicesEntries) not generic 'entries'
            $sectionField = $sectionHandle . 'Entries';
            $entries = $result[$sectionField] ?? $result['entries'] ?? [];
            $entryCount = count($entries);
            Craft::info("GraphQL query returned {$entryCount} entries from field '{$sectionField}'", 'mcp-wrapper');

            // Filter out sensitive fields from entries
            $entries = $this->filterSensitiveFields($entries);

            // Normalize result to always use 'entries' key for consistency
            return ['entries' => $entries];
        } catch (\Exception $e) {
            Craft::error("Failed to execute GraphQL query: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }

    /**
     * Execute a category query
     */
    private function executeCategoryQuery(string $token, string $categoryGroupHandle, array $args, array $params = []): array
    {
        try {
            $query = $this->buildCategoryGraphQLQuery($categoryGroupHandle, $args, $params);
            Craft::info("Category GraphQL Query for {$categoryGroupHandle}: {$query}", 'mcp-wrapper');

            $data = $this->sendGraphQLRequest($token, $query);

            if (isset($data['errors'])) {
                $errorJson = json_encode($data['errors']);
                Craft::error("GraphQL errors: {$errorJson}", 'mcp-wrapper');
                throw new \Exception('GraphQL error: ' . $errorJson, -32603);
            }

            $result = $data['data'] ?? [];
            // Result uses category group-specific field name (e.g., leadershipTeamsCategories)
            $categoryField = $categoryGroupHandle . 'Categories';
            $categories = $result[$categoryField] ?? $result['categories'] ?? [];
            $categoryCount = count($categories);
            Craft::info("GraphQL query returned {$categoryCount} categories from field '{$categoryField}'", 'mcp-wrapper');

            // Filter out sensitive fields
            $categories = $this->filterSensitiveFields($categories);

            // Normalize result to use 'categories' key
            return ['categories' => $categories];
        } catch (\Exception $e) {
            Craft::error("Failed to execute category query: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }

    /**
     * Build GraphQL query string for categories
     */
    private function buildCategoryGraphQLQuery(string $categoryGroupHandle, array $args, array $params = []): string
    {
        // Validate handle
        if (!$this->isValidHandle($categoryGroupHandle)) {
            throw new \Exception("Invalid category group handle: '{$categoryGroupHandle}'", -32602);
        }

        // Validate and sanitize arguments
        $args = $this->validateQueryArgs($args);

        $limit = min(500, max(1, (int) ($args['limit'] ?? 100)));

        $filters = [
            "limit: {$limit}",
        ];

        // Level filter (for structure)
        if (isset($args['level'])) {
            $filters[] = "level: " . intval($args['level']);
        }

        // Ordering
        if (!empty($args['orderBy'])) {
            $filters[] = 'orderBy: "' . $this->escapeGraphQLString($args['orderBy']) . '"';
        }

        // Get custom fields for this category group
        $fieldsList = $this->getFieldsListForCategoryQuery($categoryGroupHandle, $params);

        // Use category group-specific GraphQL field (e.g., leadershipTeamsCategories)
        $categoryField = $categoryGroupHandle . 'Categories';

        return "query { {$categoryField}(" . implode(', ', $filters) . ") { id title slug uri {$fieldsList} } }";
    }

    /**
     * Get fields list for category query
     */
    private function getFieldsListForCategoryQuery(string $categoryGroupHandle, array $params = []): string
    {
        $categoryGroup = Craft::$app->getCategories()->getGroupByHandle($categoryGroupHandle);
        if (!$categoryGroup) {
            return '';
        }

        $fieldLayout = $categoryGroup->getFieldLayout();
        if (!$fieldLayout) {
            return '';
        }

        $fields = [];
        foreach ($fieldLayout->getCustomFields() as $field) {
            $handle = $field->handle;
            $fieldClass = get_class($field);

            // Skip sensitive fields
            $sensitiveFields = [
                'formSubmissionNotificationEmail',
                'formSubmissionNotificationEmail2',
                'internalNotes',
                'internalComments',
                'adminNotes',
            ];

            if (in_array($handle, $sensitiveFields, true)) {
                continue;
            }

            // Handle relational fields
            if (
                $field instanceof \craft\fields\Entries ||
                $field instanceof \craft\fields\Categories ||
                $field instanceof \craft\fields\Tags ||
                $field instanceof \craft\fields\Users ||
                $field instanceof \craft\fields\Assets
            ) {
                $fields[] = "{$handle} { id title }";
            }
            // Handle Linkit link fields
            elseif (str_contains($fieldClass, 'linkit') || str_contains($fieldClass, 'LinkIt') || str_contains($fieldClass, 'Linkit')) {
                $fields[] = "{$handle} { url text type target }";
            }
            // Skip complex field types
            elseif (
                $field instanceof \craft\fields\Matrix ||
                $field instanceof \craft\fields\Table ||
                $fieldClass === 'benf\\neo\\Field' ||
                str_contains($fieldClass, '\\neo\\') ||
                str_contains($fieldClass, 'CKEditor') ||
                str_contains($fieldClass, 'Freeform') ||
                str_contains($fieldClass, 'SuperTable') ||
                str_contains($fieldClass, 'seomatic')
            ) {
                continue;
            } else {
                // Plain fields (text, number, date, lightswitch, dropdown, etc.)
                $fields[] = $handle;
            }
        }

        return !empty($fields) ? ' ' . implode(' ', $fields) : '';
    }

    /**
     * Build GraphQL query string from arguments
     */
    private function buildGraphQLQuery(string $sectionHandle, array $args, array $params = []): string
    {
        // Validate section handle
        if (!$this->isValidHandle($sectionHandle)) {
            throw new \Exception("Invalid section handle: '{$sectionHandle}'", -32602);
        }

        // Validate and sanitize all arguments
        $args = $this->validateQueryArgs($args);

        $limit = min(100, max(1, (int) ($args['limit'] ?? 10)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        $filters = [
            "section: \"{$sectionHandle}\"",
            "limit: {$limit}",
            "offset: {$offset}",
        ];

        // Basic ID filters
        if (!empty($args['id']) && is_array($args['id'])) {
            $idsStr = implode(',', array_map('intval', $args['id']));
            $filters[] = "id: [{$idsStr}]";
        }

        if (!empty($args['uid']) && is_array($args['uid'])) {
            $uidsStr = implode(',', array_map(fn($uid) => '"' . $this->escapeGraphQLString($uid) . '"', $args['uid']));
            $filters[] = "uid: [{$uidsStr}]";
        }

        if (!empty($args['slug']) && is_array($args['slug'])) {
            $slugsStr = implode(',', array_map(fn($slug) => '"' . $this->escapeGraphQLString($slug) . '"', $args['slug']));
            $filters[] = "slug: [{$slugsStr}]";
        }

        if (!empty($args['uri']) && is_array($args['uri'])) {
            $urisStr = implode(',', array_map(fn($uri) => '"' . $this->escapeGraphQLString($uri) . '"', $args['uri']));
            $filters[] = "uri: [{$urisStr}]";
        }

        if (!empty($args['title'])) {
            $filters[] = 'title: "' . $this->escapeGraphQLString($args['title']) . '"';
        }

        // Entry type filters
        if (!empty($args['type']) && is_array($args['type'])) {
            $typesStr = implode(',', array_map(fn($t) => '"' . $this->escapeGraphQLString($t) . '"', $args['type']));
            $filters[] = "type: [{$typesStr}]";
        }

        if (!empty($args['typeId']) && is_array($args['typeId'])) {
            $typeIdsStr = implode(',', array_map('intval', $args['typeId']));
            $filters[] = "typeId: [{$typeIdsStr}]";
        }

        // Status filters
        if (!empty($args['status']) && is_array($args['status'])) {
            $statusStr = implode(',', array_map(fn($s) => '"' . $this->escapeGraphQLString($s) . '"', $args['status']));
            $filters[] = "status: [{$statusStr}]";
        }

        if (isset($args['archived'])) {
            $filters[] = 'archived: ' . ($args['archived'] ? 'true' : 'false');
        }

        if (isset($args['trashed'])) {
            $filters[] = 'trashed: ' . ($args['trashed'] ? 'true' : 'false');
        }

        // Date filters
        foreach (['dateCreated', 'dateUpdated', 'postDate', 'expiryDate', 'before', 'after'] as $dateField) {
            if (!empty($args[$dateField])) {
                $filters[] = "{$dateField}: \"" . $this->escapeGraphQLString($args[$dateField]) . '"';
            }
        }

        // Relationship filters
        if (!empty($args['relatedTo']) && is_array($args['relatedTo'])) {
            $relatedIdsStr = implode(',', array_map('intval', $args['relatedTo']));
            $filters[] = "relatedTo: [{$relatedIdsStr}]";
        }

        foreach (['ancestorOf', 'descendantOf', 'siblingOf', 'prevSiblingOf', 'nextSiblingOf', 'positionedAfter', 'positionedBefore'] as $relField) {
            if (!empty($args[$relField])) {
                $filters[] = "{$relField}: " . intval($args[$relField]);
            }
        }

        // Author filters
        if (!empty($args['authorId']) && is_array($args['authorId'])) {
            $authorIdsStr = implode(',', array_map('intval', $args['authorId']));
            $filters[] = "authorId: [{$authorIdsStr}]";
        }

        if (!empty($args['authorGroup']) && is_array($args['authorGroup'])) {
            $authorGroupsStr = implode(',', array_map(fn($g) => '"' . $this->escapeGraphQLString($g) . '"', $args['authorGroup']));
            $filters[] = "authorGroup: [{$authorGroupsStr}]";
        }

        // Search
        if (!empty($args['search'])) {
            $filters[] = 'search: "' . $this->escapeGraphQLString($args['search']) . '"';
        }

        // Ordering
        if (!empty($args['orderBy'])) {
            $filters[] = 'orderBy: "' . $this->escapeGraphQLString($args['orderBy']) . '"';
        }

        if (isset($args['inReverse'])) {
            $filters[] = 'inReverse: ' . ($args['inReverse'] ? 'true' : 'false');
        }

        if (isset($args['fixedOrder'])) {
            $filters[] = 'fixedOrder: ' . ($args['fixedOrder'] ? 'true' : 'false');
        }

        if (isset($args['unique'])) {
            $filters[] = 'unique: ' . ($args['unique'] ? 'true' : 'false');
        }

        // Multi-site
        if (!empty($args['site']) && is_array($args['site'])) {
            $sitesStr = implode(',', array_map(fn($s) => '"' . $this->escapeGraphQLString($s) . '"', $args['site']));
            $filters[] = "site: [{$sitesStr}]";
        }

        if (!empty($args['siteId']) && is_array($args['siteId'])) {
            $siteIdsStr = implode(',', array_map('intval', $args['siteId']));
            $filters[] = "siteId: [{$siteIdsStr}]";
        }

        // Get custom fields for this section
        $fieldsList = $this->getFieldsListForQuery($sectionHandle, $params);

        // Use section-specific GraphQL field (e.g., servicesBrowseEntries) instead of generic entries
        $sectionField = $sectionHandle . 'Entries';

        // Remove section filter since we're using section-specific field
        $filters = array_filter($filters, fn($f) => !str_contains($f, 'section:'));

        // If we have inline fragments (entry type specific fields), use them
        if (!empty($fieldsList)) {
            return sprintf(
                'query { %s(%s) { %s } }',
                $sectionField,
                implode(', ', $filters),
                $fieldsList  // This already includes "... on TypeName { id title slug ... }"
            );
        } else {
            // Fallback: Build basic inline fragments for sections without custom fields
            // This is needed because Craft GraphQL uses union types that require inline fragments
            // We need to check what types are actually in the GraphQL schema, not just Craft

            $fallbackFragments = [];

            try {
                $token = $this->getSchemaToken($params);
                $schema = $this->introspectGraphQLSchema($token);
                $availableTypes = array_column($schema['types'], 'name');

                // Check if there's a union type for this section
                $unionTypeName = $sectionHandle . 'SectionEntryUnion';
                if (in_array($unionTypeName, $availableTypes)) {
                    // Query the union type to get possible types
                    $gqlSchema = $this->resolveGqlSchema($token);
                    $gqlService = Craft::$app->getGql();

                    $introspectionQuery = sprintf(
                        '{ __type(name: "%s") { possibleTypes { name } } }',
                        $unionTypeName
                    );

                    $data = $gqlService->executeQuery($gqlSchema, $introspectionQuery);
                    $possibleTypes = $data['data']['__type']['possibleTypes'] ?? [];

                    foreach ($possibleTypes as $type) {
                        $typeName = $type['name'];
                        $fallbackFragments[] = "... on {$typeName} { id title slug uri url dateCreated dateUpdated }";
                    }

                    Craft::info("Built fallback fragments for {$sectionHandle} from union type: " . count($fallbackFragments) . " types", 'mcp-wrapper');
                }
            } catch (\Exception $e) {
                Craft::warning("Failed to build fallback fragments from GraphQL schema: {$e->getMessage()}", 'mcp-wrapper');
            }

            // If we couldn't get types from GraphQL, try from Craft as last resort
            if (empty($fallbackFragments)) {
                $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
                if ($section) {
                    foreach ($section->getEntryTypes() as $entryType) {
                        $typeName = $entryType->handle . '_Entry';
                        $fallbackFragments[] = "... on {$typeName} { id title slug uri url dateCreated dateUpdated }";
                    }
                }
            }

            if (!empty($fallbackFragments)) {
                return sprintf(
                    'query { %s(%s) { %s } }',
                    $sectionField,
                    implode(', ', $filters),
                    implode(' ', $fallbackFragments)
                );
            } else {
                // Ultimate fallback - try without inline fragments (will likely fail for union types)
                Craft::warning("No entry types found for section {$sectionHandle}, query may fail", 'mcp-wrapper');
                return sprintf(
                    'query { %s(%s) { id title slug uri url dateCreated dateUpdated } }',
                    $sectionField,
                    implode(', ', $filters)
                );
            }
        }
    }

    /**
     * Filter out sensitive fields from entry data
     *
     * @param array $entries Array of entry data from GraphQL
     * @return array Filtered entries without sensitive fields
     */
    private function filterSensitiveFields(array $entries): array
    {
        $sensitiveFields = [
            'formSubmissionNotificationEmail',
            'formSubmissionNotificationEmail2',
            'internalNotes',
            'internalComments',
            'adminNotes',
        ];

        foreach ($entries as &$entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach ($sensitiveFields as $field) {
                if (isset($entry[$field])) {
                    unset($entry[$field]);
                    Craft::info("Filtered sensitive field '{$field}' from entry {$entry['id']}", 'mcp-wrapper');
                }
            }
        }

        return $entries;
    }

    /**
     * Build fields list for GraphQL query based on section's field layout
     */
    private function getFieldsListForQuery(string $sectionHandle, array $params = []): string
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return '';
        }

        // Get available types from the current GraphQL schema to avoid querying unregistered types
        try {
            $token = $this->getSchemaToken($params);
        } catch (\Exception $e) {
            Craft::warning("Could not get schema token for field introspection: {$e->getMessage()}", 'mcp-wrapper');
            return '';
        }

        $schema = $this->introspectGraphQLSchema($token);
        $availableTypes = array_column($schema['types'], 'name');
        Craft::info("Available GraphQL types for {$sectionHandle}: " . implode(', ', array_filter($availableTypes, fn($t) => str_contains($t, 'Entry'))), 'mcp-wrapper');

        // Get the actual union type members for this section to avoid invalid fragment spreads
        // The union type contains only the entry types that belong to THIS section in GraphQL
        $unionTypeName = $sectionHandle . 'SectionEntryUnion';
        $unionMembers = [];
        if (in_array($unionTypeName, $availableTypes, true)) {
            try {
                $gqlSchema = $this->resolveGqlSchema($token);
                $gqlService = Craft::$app->getGql();
                $introspectionQuery = sprintf(
                    '{ __type(name: "%s") { possibleTypes { name } } }',
                    $unionTypeName
                );
                $data = $gqlService->executeQuery($gqlSchema, $introspectionQuery);
                $possibleTypes = $data['data']['__type']['possibleTypes'] ?? [];
                $unionMembers = array_column($possibleTypes, 'name');
                Craft::info("Union {$unionTypeName} has types: " . implode(', ', $unionMembers), 'mcp-wrapper');
            } catch (\Exception $e) {
                Craft::warning("Failed to introspect union type {$unionTypeName}: {$e->getMessage()}", 'mcp-wrapper');
            }
        }

        $entryTypeFragments = [];
        foreach ($section->getEntryTypes() as $entryType) {
            // GraphQL type name format is: typeHandle_Entry
            $typeName = $entryType->handle . '_Entry';

            // If we have union members, check against those (most accurate)
            // Otherwise fall back to checking available schema types
            if (!empty($unionMembers)) {
                if (!in_array($typeName, $unionMembers, true)) {
                    Craft::info("Skipping type not in union: {$typeName} (not in {$unionTypeName})", 'mcp-wrapper');
                    continue;
                }
            } elseif (!in_array($typeName, $availableTypes, true)) {
                Craft::info("Skipping unregistered type: {$typeName} (not in schema)", 'mcp-wrapper');
                continue;
            }

            // Include all entry types registered in GraphQL, regardless of entry count
            // This ensures queries work even if sections are empty or entries are disabled
            Craft::info("Including type: {$typeName} in query for {$sectionHandle}", 'mcp-wrapper');

            $fields = [];
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                $handle = $field->handle;
                $fieldClass = get_class($field);

                // Skip sensitive fields that shouldn't be exposed via MCP
                $sensitiveFields = [
                    'formSubmissionNotificationEmail',
                    'formSubmissionNotificationEmail2',
                    'internalNotes',
                    'internalComments',
                    'adminNotes',
                ];

                if (in_array($handle, $sensitiveFields, true)) {
                    Craft::info("Excluding sensitive field from MCP: {$handle}", 'mcp-wrapper');
                    continue;
                }

                // Handle relational fields
                if (
                    $field instanceof \craft\fields\Entries ||
                    $field instanceof \craft\fields\Categories ||
                    $field instanceof \craft\fields\Tags ||
                    $field instanceof \craft\fields\Users ||
                    $field instanceof \craft\fields\Assets
                ) {
                    $fields[] = "{$handle} { id title }";
                }
                // Handle Linkit link fields (require sub-selection for Linkit_Link type)
                elseif (str_contains($fieldClass, 'linkit') || str_contains($fieldClass, 'LinkIt') || str_contains($fieldClass, 'Linkit')) {
                    $fields[] = "{$handle} { url text type target }";
                }
                // Skip Matrix and other complex field types that require special handling
                elseif (
                    $field instanceof \craft\fields\Matrix ||
                    $field instanceof \craft\fields\Table ||
                    $fieldClass === 'benf\\neo\\Field' ||
                    str_contains($fieldClass, '\\neo\\') ||
                    str_contains($fieldClass, 'CKEditor') ||
                    str_contains($fieldClass, 'Freeform') ||
                    str_contains($fieldClass, 'SuperTable') ||
                    str_contains($fieldClass, 'seomatic')
                ) {
                    // Skip these fields - they require complex nested queries or special handling
                    Craft::info("Skipping complex field type: {$handle} ({$fieldClass})", 'mcp-wrapper');
                    continue;
                } else {
                    // Plain fields (text, number, date, lightswitch, dropdown, etc.)
                    $fields[] = $handle;
                }
            }

            // Build inline fragment for this entry type including basic fields
            $basicFields = ['id', 'title', 'slug', 'uri', 'url', 'dateCreated', 'dateUpdated'];
            $allFields = array_merge($basicFields, $fields);
            $fieldsStr = implode(' ', $allFields);
            $entryTypeFragments[] = "... on {$typeName} { {$fieldsStr} }";
        }

        return implode(' ', $entryTypeFragments);
    }

    /**
     * Send GraphQL request via Craft's internal GraphQL execution API
     */
    private function sendGraphQLRequest(string $token, string $query): array
    {
        try {
            $schema = $this->resolveGqlSchema($token);
            $gqlService = Craft::$app->getGql();

            return $gqlService->executeQuery($schema, $query);
        } catch (\Exception $e) {
            Craft::error("GraphQL execution failed: {$e->getMessage()}", 'mcp-wrapper');
            throw new \Exception('GraphQL request failed: ' . $e->getMessage(), -32603);
        }
    }

    /**
     * Handle prompts/list request
     * Returns all available MCP prompts
     */
    private function handlePromptsList(array $params): array
    {
        $promptRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('promptRegistry');
        $prompts = $promptRegistry->listPrompts();

        return ['prompts' => $prompts];
    }

    /**
     * Handle prompts/get request
     * Returns a specific prompt with its message content
     */
    private function handlePromptsGet(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (!$name) {
            throw new \Exception('Missing required parameter: name', -32602);
        }

        $promptRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('promptRegistry');

        try {
            $prompt = $promptRegistry->getPrompt($name, $arguments);
            return $prompt;
        } catch (\Exception $e) {
            throw new \Exception("Failed to get prompt '{$name}': {$e->getMessage()}", -32603);
        }
    }

    /**
     * Handle resources/list request
     * Returns all available MCP resources
     */
    private function handleResourcesList(array $params): array
    {
        $schemaHandle = $params['schemaHandle'] ?? null;

        if (!$schemaHandle) {
            throw new \Exception('Missing required parameter: schemaHandle', -32602);
        }

        $resourceRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('resourceRegistry');
        $resources = $resourceRegistry->listResources($schemaHandle);

        return ['resources' => $resources];
    }

    /**
     * Handle resources/read request
     * Reads a specific resource by URI
     */
    private function handleResourcesRead(array $params): array
    {
        $uri = $params['uri'] ?? null;
        $schemaHandle = $params['schemaHandle'] ?? null;

        if (!$uri) {
            throw new \Exception('Missing required parameter: uri', -32602);
        }

        if (!$schemaHandle) {
            throw new \Exception('Missing required parameter: schemaHandle', -32602);
        }

        $resourceRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('resourceRegistry');

        try {
            return $resourceRegistry->readResource($uri, $schemaHandle);
        } catch (\Exception $e) {
            throw new \Exception("Failed to read resource '{$uri}': {$e->getMessage()}", -32603);
        }
    }

    /**
     * Validate tool access based on security configuration
     *
     * @param string $toolName Tool name to validate
     * @throws \Exception if tool is disabled or dangerous tools are not enabled
     */
    private function validateToolAccess(string $toolName): void
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');

        // Check if tool is explicitly disabled
        $disabledTools = $config['security']['disabledTools'] ?? [];
        if (in_array($toolName, $disabledTools, true)) {
            Craft::warning("Access denied: Tool '{$toolName}' is disabled", 'mcp-wrapper');
            throw new \Exception("Tool '{$toolName}' is disabled", -32601);
        }

        // Check if tool is dangerous and if dangerous tools are enabled
        $enableDangerous = $config['security']['enableDangerousTools'] ?? false;

        if (!$enableDangerous) {
            // Get tool definition to check if it's dangerous
            $toolRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('toolRegistry');
            $manualTools = $toolRegistry->discoverManualTools();

            foreach ($manualTools as $tool) {
                if ($tool['name'] === $toolName && ($tool['dangerous'] ?? false)) {
                    Craft::warning("Access denied: Dangerous tool '{$toolName}' not enabled", 'mcp-wrapper');
                    throw new \Exception("Dangerous tools are not enabled. Set 'enableDangerousTools' to true in config.", -32601);
                }
            }
        }
    }

    /**
     * Filter tools based on security configuration
     * Removes disabled tools and dangerous tools if not enabled
     *
     * @param array $tools Array of tool definitions
     * @return array Filtered tools array
     */
    private function filterToolsBySecurityConfig(array $tools): array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $disabledTools = $config['security']['disabledTools'] ?? [];
        $enableDangerous = $config['security']['enableDangerousTools'] ?? false;

        return array_values(array_filter($tools, function ($tool) use ($disabledTools, $enableDangerous) {
            $toolName = $tool['name'] ?? '';

            // Filter out explicitly disabled tools
            if (in_array($toolName, $disabledTools, true)) {
                Craft::info("Filtering out disabled tool: {$toolName}", 'mcp-wrapper');
                return false;
            }

            // Filter out dangerous tools if not enabled
            if (!$enableDangerous && ($tool['dangerous'] ?? false)) {
                Craft::info("Filtering out dangerous tool: {$toolName} (dangerous tools not enabled)", 'mcp-wrapper');
                return false;
            }

            return true;
        }));
    }

    // ============================================================
    // Input Validation Methods
    // ============================================================

    /**
     * Maximum allowed items in array parameters to prevent DoS
     */
    private const MAX_ARRAY_ITEMS = 100;

    /**
     * Escape a string for safe use in GraphQL queries
     *
     * Delegates to GraphQLSanitizer for secure JSON-based escaping.
     *
     * @param string $value The string to escape
     * @return string The escaped string (without surrounding quotes)
     */
    private function escapeGraphQLString(string $value): string
    {
        return GraphQLSanitizer::escapeGraphQLString($value);
    }

    /**
     * Maximum string length for text parameters
     */
    private const MAX_STRING_LENGTH = 500;

    /**
     * Validate and sanitize query arguments
     * Applies limits and character validation to prevent injection/DoS
     *
     * @param array $args Raw arguments from request
     * @return array Validated and sanitized arguments
     * @throws \Exception If validation fails
     */
    private function validateQueryArgs(array $args): array
    {
        $validated = [];

        // Validate array parameters (limit length)
        $arrayParams = ['id', 'uid', 'slug', 'uri', 'type', 'typeId', 'status', 'relatedTo', 'authorId', 'authorGroup', 'site', 'siteId'];
        foreach ($arrayParams as $param) {
            if (isset($args[$param]) && is_array($args[$param])) {
                if (count($args[$param]) > self::MAX_ARRAY_ITEMS) {
                    throw new \Exception("Parameter '{$param}' exceeds maximum of " . self::MAX_ARRAY_ITEMS . " items", -32602);
                }
                $validated[$param] = array_slice($args[$param], 0, self::MAX_ARRAY_ITEMS);
            } elseif (isset($args[$param])) {
                $validated[$param] = $args[$param];
            }
        }

        // Validate string parameters (limit length, sanitize)
        $stringParams = ['title', 'search', 'orderBy', 'dateCreated', 'dateUpdated', 'postDate', 'expiryDate', 'before', 'after'];
        foreach ($stringParams as $param) {
            if (isset($args[$param]) && is_string($args[$param])) {
                $value = $args[$param];
                if (strlen($value) > self::MAX_STRING_LENGTH) {
                    throw new \Exception("Parameter '{$param}' exceeds maximum length of " . self::MAX_STRING_LENGTH, -32602);
                }
                $validated[$param] = $this->sanitizeStringInput($value);
            }
        }

        // Validate handle parameters (alphanumeric + underscore/hyphen only)
        if (isset($args['type']) && is_array($args['type'])) {
            foreach ($args['type'] as $type) {
                if (!$this->isValidHandle($type)) {
                    throw new \Exception("Invalid entry type handle: '{$type}'. Only alphanumeric characters, underscores, and hyphens are allowed.", -32602);
                }
            }
            $validated['type'] = $args['type'];
        }

        if (isset($args['status']) && is_array($args['status'])) {
            $allowedStatuses = ['live', 'pending', 'expired', 'disabled', 'enabled', 'archived'];
            foreach ($args['status'] as $status) {
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new \Exception("Invalid status: '{$status}'. Allowed: " . implode(', ', $allowedStatuses), -32602);
                }
            }
            $validated['status'] = $args['status'];
        }

        // Validate integer parameters
        $intParams = ['limit', 'offset', 'ancestorOf', 'descendantOf', 'siblingOf', 'prevSiblingOf', 'nextSiblingOf', 'positionedAfter', 'positionedBefore'];
        foreach ($intParams as $param) {
            if (isset($args[$param])) {
                $validated[$param] = (int) $args[$param];
            }
        }

        // Validate boolean parameters
        $boolParams = ['archived', 'trashed', 'inReverse', 'fixedOrder', 'unique'];
        foreach ($boolParams as $param) {
            if (isset($args[$param])) {
                $validated[$param] = (bool) $args[$param];
            }
        }

        return $validated;
    }

    /**
     * Check if a string is a valid Craft handle
     * Delegates to GraphQLSanitizer for validation.
     */
    private function isValidHandle(string $handle): bool
    {
        return GraphQLSanitizer::isValidHandle($handle);
    }

    /**
     * Sanitize string input for GraphQL queries
     *
     * Delegates to GraphQLSanitizer for whitelist-based validation.
     *
     * @param string $input The input string to sanitize
     * @return string The sanitized string
     * @throws \Exception if input contains disallowed characters
     */
    private function sanitizeStringInput(string $input): string
    {
        return GraphQLSanitizer::sanitizeStringInput($input, function ($message, $category) {
            Craft::warning($message, $category);
        });
    }
}
