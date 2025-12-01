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
            $result = match ($method) {
                'initialize' => $this->handleInitialize($params),
                'tools/list' => $this->handleToolsList($params),
                'tools/call' => $this->handleToolCall($params),
                'ping' => ['success' => true],
                default => throw new \Exception("Unknown method: {$method}", -32601)
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $e->getCode() ?: -32603,
                    'message' => $e->getMessage(),
                ],
            ];
        }
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
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    /**
     * List all available MCP tools (one per Craft section)
     */
    private function handleToolsList(array $params): array
    {
        $schemaHandle = $params['schemaHandle'] ?? null;
        if (!$schemaHandle) {
            throw new \Exception('schemaHandle parameter required', -32602);
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('mcp-wrapper');
        
        if (!isset($config['schemas'][$schemaHandle])) {
            throw new \Exception("Unknown schema: {$schemaHandle}", -32602);
        }
        
        $token = $config['schemas'][$schemaHandle];
        $sections = $this->getSectionsForSchema($token);
        
        $tools = [];
        foreach ($sections as $section) {
            $fields = $this->getFieldsForSection($section);
            
            $tools[] = [
                'name' => "query_{$section->handle}",
                'description' => "Query {$section->name} entries from Craft CMS. Returns entries with their fields and relationships.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of entries to return',
                            'default' => 10,
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Number of entries to skip',
                            'default' => 0,
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search query to filter entries',
                        ],
                        'id' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific entry IDs',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ];
        }

        return ['tools' => $tools];
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

        // Extract section handle from tool name (e.g., "query_news" -> "news")
        if (!preg_match('/^query_(.+)$/', $toolName, $matches)) {
            throw new \Exception("Invalid tool name: {$toolName}", -32602);
        }
        
        $sectionHandle = $matches[1];
        $schemaHandle = $params['schemaHandle'] ?? null;
        
        if (!$schemaHandle) {
            throw new \Exception('schemaHandle required', -32602);
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('mcp-wrapper');
        
        if (!isset($config['schemas'][$schemaHandle])) {
            throw new \Exception("Unknown schema: {$schemaHandle}", -32602);
        }
        
        $token = $config['schemas'][$schemaHandle];

        // Build and execute GraphQL query
        $result = $this->executeGraphQLQuery($token, $sectionHandle, $arguments);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
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
        $limit = $args['limit'] ?? 10;
        $offset = $args['offset'] ?? 0;
        $search = $args['search'] ?? null;
        $ids = $args['id'] ?? null;

        // Build GraphQL query dynamically
        $filters = ["section: \"{$sectionHandle}\"", "limit: {$limit}", "offset: {$offset}"];
        
        if ($search) {
            $filters[] = 'search: "' . addslashes($search) . '"';
        }
        
        if ($ids) {
            $idsStr = implode(',', array_map('intval', $ids));
            $filters[] = "id: [{$idsStr}]";
        }

        $query = sprintf(
            'query { entries(%s) { id title slug uri dateCreated dateUpdated } }',
            implode(', ', $filters)
        );

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

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (isset($data['errors'])) {
            throw new \Exception('GraphQL error: ' . json_encode($data['errors']), -32603);
        }

        return $data['data'] ?? [];
    }
}
