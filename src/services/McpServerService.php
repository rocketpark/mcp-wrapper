<?php
namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use yii\web\Response;

/**
 * MCP Server Service
 * 
 * Implements the Model Context Protocol JSON-RPC 2.0 interface
 * for exposing Craft CMS content as MCP Tools
 */
class McpServerService extends Component
{
    private const MCP_VERSION = '2025-06-18';
    private const SERVER_NAME = 'craft-cms-mcp';
    private const SERVER_VERSION = '1.0.0';

    /**
     * Handle incoming JSON-RPC 2.0 requests
     */
    public function handleRequest(array $jsonRpcRequest): array
    {
        $method = $jsonRpcRequest['method'] ?? null;
        $params = $jsonRpcRequest['params'] ?? [];
        $id = $jsonRpcRequest['id'] ?? null;

        try {
            Craft::info("MCP Request: {$method}", 'mcp-wrapper');
            $result = $this->dispatchMethod($method, $params);
            Craft::info("MCP Response: success for {$method}", 'mcp-wrapper');
            return $this->successResponse($id, $result);
        } catch (\Exception $e) {
            Craft::error("MCP Error ({$method}): {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            return $this->errorResponse($id, $e);
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
     */
    private function errorResponse(?int $id, \Exception $e): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $e->getCode() ?: -32603,
                'message' => $e->getMessage(),
            ],
        ];
    }

    /**
     * Handle MCP initialize request
     */
    private function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => self::MCP_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false, // Tools list is static per schema
                ],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'title' => 'Craft CMS MCP Server', // Human-readable display name
                'version' => self::SERVER_VERSION,
            ],
            'instructions' => 'Query Craft CMS content via GraphQL. Use tools/list to discover available content types, then tools/call to query entries.',
        ];
    }

    /**
     * List all available MCP tools (one per Craft section)
     */
    private function handleToolsList(array $params): array
    {
        $token = $this->getSchemaToken($params);
        $sections = $this->getSectionsForSchema($token);
        
        $tools = array_map(
            fn($section) => $this->buildToolDefinition($section),
            $sections
        );

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
     * Execute a tool call (query GraphQL)
     */
    private function handleToolCall(array $params): array
    {
        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];
        
        if (!$toolName) {
            throw new \Exception('Tool name required', -32602);
        }

        $sectionHandle = $this->extractSectionHandle($toolName);
        $token = $this->getSchemaToken($params);

        $result = $this->executeGraphQLQuery($token, $sectionHandle, $arguments);

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

        $config = Craft::$app->getConfig()->getConfigFromFile('mcp-wrapper');
        
        if (!isset($config['schemas'][$schemaHandle])) {
            throw new \Exception("Unknown schema: {$schemaHandle}", -32602);
        }
        
        return $config['schemas'][$schemaHandle];
    }

    /**
     * Get sections accessible via this schema
     */
    private function getSectionsForSchema(string $token): array
    {
        // For now, return all sections
        // In production, filter by GraphQL schema permissions
        return Craft::$app->getEntries()->getAllSections();
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
    private function executeGraphQLQuery(string $token, string $sectionHandle, array $args): array
    {
        try {
            $query = $this->buildGraphQLQuery($sectionHandle, $args);
            Craft::info("GraphQL Query for {$sectionHandle}: {$query}", 'mcp-wrapper');
            
            $data = $this->sendGraphQLRequest($token, $query);
            
            if (isset($data['errors'])) {
                $errorJson = json_encode($data['errors']);
                Craft::error("GraphQL errors: {$errorJson}", 'mcp-wrapper');
                throw new \Exception('GraphQL error: ' . $errorJson, -32603);
            }

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Craft::error("Failed to execute GraphQL query: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }

    /**
     * Build GraphQL query string from arguments
     */
    private function buildGraphQLQuery(string $sectionHandle, array $args): string
    {
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
            $uidsStr = implode(',', array_map(fn($uid) => '"' . addslashes($uid) . '"', $args['uid']));
            $filters[] = "uid: [{$uidsStr}]";
        }
        
        if (!empty($args['slug']) && is_array($args['slug'])) {
            $slugsStr = implode(',', array_map(fn($slug) => '"' . addslashes($slug) . '"', $args['slug']));
            $filters[] = "slug: [{$slugsStr}]";
        }
        
        if (!empty($args['uri']) && is_array($args['uri'])) {
            $urisStr = implode(',', array_map(fn($uri) => '"' . addslashes($uri) . '"', $args['uri']));
            $filters[] = "uri: [{$urisStr}]";
        }
        
        if (!empty($args['title'])) {
            $filters[] = 'title: "' . addslashes($args['title']) . '"';
        }
        
        // Entry type filters
        if (!empty($args['type']) && is_array($args['type'])) {
            $typesStr = implode(',', array_map(fn($t) => '"' . addslashes($t) . '"', $args['type']));
            $filters[] = "type: [{$typesStr}]";
        }
        
        if (!empty($args['typeId']) && is_array($args['typeId'])) {
            $typeIdsStr = implode(',', array_map('intval', $args['typeId']));
            $filters[] = "typeId: [{$typeIdsStr}]";
        }
        
        // Status filters
        if (!empty($args['status']) && is_array($args['status'])) {
            $statusStr = implode(',', array_map(fn($s) => '"' . addslashes($s) . '"', $args['status']));
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
                $filters[] = "{$dateField}: \"" . addslashes($args[$dateField]) . '"';
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
            $authorGroupsStr = implode(',', array_map(fn($g) => '"' . addslashes($g) . '"', $args['authorGroup']));
            $filters[] = "authorGroup: [{$authorGroupsStr}]";
        }
        
        // Search
        if (!empty($args['search'])) {
            $filters[] = 'search: "' . addslashes($args['search']) . '"';
        }
        
        // Ordering
        if (!empty($args['orderBy'])) {
            $filters[] = 'orderBy: "' . addslashes($args['orderBy']) . '"';
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
            $sitesStr = implode(',', array_map(fn($s) => '"' . addslashes($s) . '"', $args['site']));
            $filters[] = "site: [{$sitesStr}]";
        }
        
        if (!empty($args['siteId']) && is_array($args['siteId'])) {
            $siteIdsStr = implode(',', array_map('intval', $args['siteId']));
            $filters[] = "siteId: [{$siteIdsStr}]";
        }

        // Get custom fields for this section
        $fieldsList = $this->getFieldsListForQuery($sectionHandle);

        return sprintf(
            'query { entries(%s) { id title slug uri dateCreated dateUpdated %s } }',
            implode(', ', $filters),
            $fieldsList
        );
    }

    /**
     * Build fields list for GraphQL query based on section's field layout
     */
    private function getFieldsListForQuery(string $sectionHandle): string
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return '';
        }

        $entryTypeFragments = [];
        foreach ($section->getEntryTypes() as $entryType) {
            $fields = [];
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                $handle = $field->handle;

                // Handle relational fields
                if ($field instanceof \craft\fields\Entries ||
                    $field instanceof \craft\fields\Categories ||
                    $field instanceof \craft\fields\Tags ||
                    $field instanceof \craft\fields\Users ||
                    $field instanceof \craft\fields\Assets) {
                    $fields[] = "{$handle} { id title }";
                } else {
                    // Plain fields (text, number, etc.)
                    $fields[] = $handle;
                }
            }

            // Build inline fragment for this entry type
            if (!empty($fields)) {
                // GraphQL type name format is: sectionHandle_entryTypeHandle_Entry
                // But if entry type handle matches section handle, it's just: sectionHandle_Entry
                if ($entryType->handle === $sectionHandle) {
                    $typeName = $sectionHandle . '_Entry';
                } else {
                    $typeName = $sectionHandle . '_' . $entryType->handle . '_Entry';
                }
                $fieldsStr = implode(' ', $fields);
                $entryTypeFragments[] = "... on {$typeName} { {$fieldsStr} }";
            }
        }

        return implode(' ', $entryTypeFragments);
    }

    /**
     * Send GraphQL request to Craft API
     */
    private function sendGraphQLRequest(string $token, string $query): array
    {
        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => Craft::$app->request->getHostInfo(),
                'timeout' => 10,
            ]);

            $response = $client->post('/api', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['query' => $query],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Craft::error("GraphQL request failed: {$e->getMessage()}", 'mcp-wrapper');
            if ($e->hasResponse()) {
                $body = $e->getResponse()->getBody()->getContents();
                Craft::error("Response body: {$body}", 'mcp-wrapper');
            }
            throw new \Exception('GraphQL request failed: ' . $e->getMessage(), -32603);
        } catch (\Exception $e) {
            Craft::error("Unexpected error in GraphQL request: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }
}
