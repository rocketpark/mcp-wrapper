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
        
        // Parse JSON-RPC request
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

        // Inject schemaHandle into params for service layer
        if (!isset($jsonRpcRequest['params'])) {
            $jsonRpcRequest['params'] = [];
        }
        $jsonRpcRequest['params']['schemaHandle'] = $schemaHandle;

        // Handle request via service
        $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
        $response = $mcpServer->handleRequest($jsonRpcRequest);

        return $this->asJson($response);
    }

    /**
     * SSE endpoint for streaming (future enhancement)
     */
    public function actionStream(string $schemaHandle): Response
    {
        // TODO: Implement Server-Sent Events for streaming responses
        return $this->asJson([
            'error' => 'SSE not yet implemented',
        ]);
    }
}
