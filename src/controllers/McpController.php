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
     * This implements a simpler SSE approach where the client can:
     * 1. Send initial request via URL parameters or POST body
     * 2. Receive response via SSE stream
     * 
     * For Airia compatibility, this endpoint accepts an SSE connection
     * and streams MCP responses as events.
     */
    public function actionSse(string $schemaHandle): void
    {
        // Set SSE headers before any output
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        header('Access-Control-Allow-Origin: *'); // Allow CORS
        
        // Start output buffering to send events immediately
        if (ob_get_level()) ob_end_clean();
        
        try {
            // Get MCP server instance
            $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
            
            // For initial connection, send server info via initialize
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
                'params' => [
                    'schemaHandle' => $schemaHandle,
                ],
            ];
            
            $toolsResponse = $mcpServer->handleRequest($toolsRequest);
            $this->sendSseEvent('message', $toolsResponse);
            
            // Keep connection alive - in a real implementation,
            // you'd listen for client messages and respond
            // For now, just send periodic keepalives
            $this->sendSseEvent('ready', ['status' => 'connected']);
            
            // Send a comment to keep connection alive
            echo ": keepalive\n\n";
            flush();
            
        } catch (\Exception $e) {
            Craft::error("MCP SSE error: {$e->getMessage()}", 'mcp-wrapper');
            
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
        
        // Exit to prevent Yii from trying to send headers again
        Craft::$app->end();
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
