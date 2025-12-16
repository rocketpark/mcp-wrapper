<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * MCP Server Controller
 * 
 * Handles JSON-RPC 2.0 requests from MCP clients
 */
class McpController extends Controller
{
    protected array|int|bool $allowAnonymous = true;
    public $enableCsrfValidation = false; // MCP clients don't use CSRF tokens

    /**
     * Main MCP endpoint
     * Accepts JSON-RPC 2.0 requests
     */
    public function actionIndex(string $schemaHandle): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        
        try {
            // Parse JSON-RPC request
            $rawBody = Craft::$app->request->getRawBody();
            Craft::info("MCP request received for schema: {$schemaHandle}", 'mcp-wrapper');
            
            $jsonRpcRequest = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Parse error: ' . json_last_error_msg();
                Craft::error("JSON parse error: {$error}", 'mcp-wrapper');
                return $this->asJson([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => $error,
                    ],
                    'id' => null,
                ]);
            }

            // Inject schemaHandle into params for service layer
            if (!isset($jsonRpcRequest['params'])) {
                $jsonRpcRequest['params'] = [];
            }
            $jsonRpcRequest['params']['schemaHandle'] = $schemaHandle;

            // Handle request via service
            $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
            $response = $mcpServer->handleRequest($jsonRpcRequest);

            return $this->asJson($response);
        } catch (\Exception $e) {
            Craft::error("MCP controller error: {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            
            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
                'id' => null,
            ]);
        }
    }

    /**
     * SSE endpoint for Server-Sent Events streaming
     * 
     * Handles both GET (SSE stream) and POST (validation/commands) for Airia compatibility.
     * 
     * POST requests return JSON responses for validation.
     * GET requests establish SSE stream and send MCP events.
     */
    public function actionSse(string $schemaHandle)
    {
        $method = Craft::$app->request->getMethod();
        
        // Handle POST requests (validation, commands)
        if ($method === 'POST') {
            return $this->handleSsePost($schemaHandle);
        }
        
        // Handle GET requests (SSE stream)
        $this->handleSseStream($schemaHandle);
    }
    
    /**
     * Handle POST requests to SSE endpoint (for validation)
     */
    private function handleSsePost(string $schemaHandle): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        
        try {
            $rawBody = Craft::$app->request->getRawBody();
            $jsonRpcRequest = json_decode($rawBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->asJson([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: ' . json_last_error_msg(),
                    ],
                    'id' => null,
                ]);
            }
            
            // Inject schemaHandle
            if (!isset($jsonRpcRequest['params'])) {
                $jsonRpcRequest['params'] = [];
            }
            $jsonRpcRequest['params']['schemaHandle'] = $schemaHandle;
            
            // Handle request
            $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
            $response = $mcpServer->handleRequest($jsonRpcRequest);
            
            return $this->asJson($response);
            
        } catch (\Exception $e) {
            Craft::error("MCP SSE POST error: {$e->getMessage()}", 'mcp-wrapper');
            
            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
                'id' => null,
            ]);
        }
    }
    
    /**
     * Handle GET requests (SSE stream)
     */
    private function handleSseStream(string $schemaHandle): void
    {
        // Set SSE headers before any output
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');
        
        // Clean output buffer
        if (ob_get_level()) ob_end_clean();
        
        try {
            $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
            
            // Send initialize response
            $initRequest = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'schemaHandle' => $schemaHandle,
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => [
                        'name' => 'sse-client',
                        'version' => '1.0.0',
                    ],
                ],
            ];
            
            $initResponse = $mcpServer->handleRequest($initRequest);
            $this->sendSseEvent('message', $initResponse);
            
            // Send tools list
            $toolsRequest = [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
                'params' => ['schemaHandle' => $schemaHandle],
            ];
            
            $toolsResponse = $mcpServer->handleRequest($toolsRequest);
            $this->sendSseEvent('message', $toolsResponse);
            
            // Send ready event
            $this->sendSseEvent('ready', ['status' => 'connected']);
            
            // Keepalive
            echo ": keepalive\n\n";
            flush();
            
        } catch (\Exception $e) {
            Craft::error("MCP SSE stream error: {$e->getMessage()}", 'mcp-wrapper');
            
            $errorEvent = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
                'id' => null,
            ];
            
            $this->sendSseEvent('error', $errorEvent);
        }
        
        exit(0);
    }
    
    /**
     * Send an SSE event
     */
    private function sendSseEvent(string $eventType, array $data): void
    {
        echo "event: {$eventType}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }
}
