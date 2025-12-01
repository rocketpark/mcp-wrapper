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
            $result = $this->dispatchMethod($method, $params);
            return $this->successResponse($id, $result);
        } catch (\Exception $e) {
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
     */
    private function getToolInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
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
                'search' => [
                    'type' => 'string',
                    'description' => 'Full-text search query to filter entries',
                ],
                'id' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Filter by specific entry IDs',
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
        $query = $this->buildGraphQLQuery($sectionHandle, $args);
        $data = $this->sendGraphQLRequest($token, $query);
        
        if (isset($data['errors'])) {
            throw new \Exception('GraphQL error: ' . json_encode($data['errors']), -32603);
        }

        return $data['data'] ?? [];
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
        
        if (!empty($args['search'])) {
            $filters[] = 'search: "' . addslashes($args['search']) . '"';
        }
        
        if (!empty($args['id']) && is_array($args['id'])) {
            $idsStr = implode(',', array_map('intval', $args['id']));
            $filters[] = "id: [{$idsStr}]";
        }

        return sprintf(
            'query { entries(%s) { id title slug uri dateCreated dateUpdated } }',
            implode(', ', $filters)
        );
    }

    /**
     * Send GraphQL request to Craft API
     */
    private function sendGraphQLRequest(string $token, string $query): array
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => Craft::$app->request->getHostInfo(),
            'timeout' => 10,
        ]);

        $response = $client->post('/api', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => ['query' => $query],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
