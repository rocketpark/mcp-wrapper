<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\mcpwrapper\support\IpValidator;
use rocketpark\mcpwrapper\support\RateLimiter;
use yii\web\ForbiddenHttpException;
use yii\web\TooManyRequestsHttpException;
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
     * Validate IP access and rate limiting before any action
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $remoteIp = Craft::$app->request->getUserIP();

        // Check IP whitelist
        $allowedIps = $config['security']['allowedIps'] ?? [];

        if (!empty($allowedIps)) {
            if (!IpValidator::isAllowed($remoteIp, $allowedIps)) {
                Craft::warning("MCP access denied for IP: {$remoteIp}", 'mcp-wrapper');
                throw new ForbiddenHttpException('Access denied: IP not whitelisted');
            }

            Craft::info("MCP access granted for IP: {$remoteIp}", 'mcp-wrapper');
        }

        // Check rate limiting (skip for SSE GET requests as they're long-lived)
        $rateLimitEnabled = $config['security']['enableRateLimit'] ?? true;

        if ($rateLimitEnabled && !($action->id === 'sse' && Craft::$app->request->isGet)) {
            $rateLimitResult = RateLimiter::check($remoteIp);

            // Add rate limit headers to response
            $limit = $config['security']['rateLimit'] ?? 100;
            $this->response->headers->set('X-RateLimit-Limit', (string) $limit);
            $this->response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining']);
            $this->response->headers->set('X-RateLimit-Reset', (string) $rateLimitResult['resetAt']);

            if (!$rateLimitResult['allowed']) {
                Craft::warning("Rate limit exceeded for IP: {$remoteIp}", 'mcp-wrapper');
                throw new TooManyRequestsHttpException('Rate limit exceeded. Please try again later.');
            }
        }

        return true;
    }

    /**
     * Main MCP endpoint
     * Accepts JSON-RPC 2.0 requests
     */
    public function actionIndex(string $schemaHandle): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        
        // Add security headers
        $this->response->headers->set('X-Content-Type-Options', 'nosniff');
        $this->response->headers->set('X-Frame-Options', 'DENY');
        $this->response->headers->set('X-XSS-Protection', '1; mode=block');
        
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
            $response = $mcpServer->handleRequest($jsonRpcRequest, $schemaHandle);

            return $this->asJson($response);
        } catch (\Exception $e) {
            Craft::error("MCP controller error: {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');

            // Sanitize error message for production - don't expose internal details
            $errorMessage = Craft::$app->config->general->devMode
                ? 'Internal error: ' . $e->getMessage()
                : 'An internal error occurred. Please try again later.';

            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $errorMessage,
                ],
                'id' => null,
            ]);
        }
    }

    /**
     * SSE endpoint for Server-Sent Events streaming (legacy transport)
     * 
     * Opens an SSE stream for server-to-client messages.
     * Client should POST to /messages endpoint for client-to-server messages.
     */
    public function actionSse(string $schemaHandle)
    {
        $method = Craft::$app->request->getMethod();
        
        // Handle POST - direct JSON-RPC requests to SSE endpoint
        if ($method === 'POST') {
            $this->response->format = Response::FORMAT_JSON;
            
            try {
                $jsonRpcRequest = Craft::$app->request->getBodyParams();
                if (!$jsonRpcRequest || !isset($jsonRpcRequest['method'])) {
                    $jsonRpcRequest = json_decode(Craft::$app->request->getRawBody(), true);
                }
                
                if (!$jsonRpcRequest) {
                    return $this->asJson([
                        'jsonrpc' => '2.0',
                        'error' => ['code' => -32700, 'message' => 'Parse error'],
                        'id' => null,
                    ]);
                }
                
                if (!isset($jsonRpcRequest['params'])) {
                    $jsonRpcRequest['params'] = [];
                }
                $jsonRpcRequest['params']['schemaHandle'] = $schemaHandle;
                
                $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
                $response = $mcpServer->handleRequest($jsonRpcRequest);
                
                return $this->asJson($response);
                
            } catch (\Exception $e) {
                Craft::error("MCP SSE POST error: {$e->getMessage()}", 'mcp-wrapper');

                $errorMessage = Craft::$app->config->general->devMode
                    ? 'Internal error: ' . $e->getMessage()
                    : 'An internal error occurred. Please try again later.';

                return $this->asJson([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32603, 'message' => $errorMessage],
                    'id' => null,
                ]);
            }
        }
        
        // Handle GET - SSE stream with endpoint event
        $sessionId = bin2hex(random_bytes(16));
        Craft::$app->cache->set("mcp_session_{$sessionId}", $schemaHandle, 3600);

        // SSE connection limits to prevent DoS
        $maxDuration = 3600; // 1 hour max connection time
        $keepaliveInterval = 15; // seconds between keepalives
        $startTime = time();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');

        if (ob_get_level()) ob_end_clean();

        echo "event: endpoint\n";
        echo "data: " . json_encode([
            'endpoint' => "/actions/mcp-wrapper/mcp/messages?sessionId={$sessionId}"
        ]) . "\n\n";
        flush();

        // Keep connection alive with timeout protection
        while (connection_status() === CONNECTION_NORMAL) {
            // Check if max duration exceeded
            if ((time() - $startTime) >= $maxDuration) {
                Craft::info("SSE connection timed out after {$maxDuration}s for session {$sessionId}", 'mcp-wrapper');
                break;
            }

            // Check if client disconnected
            if (connection_aborted()) {
                Craft::info("SSE client disconnected for session {$sessionId}", 'mcp-wrapper');
                break;
            }

            echo ": keepalive\n\n";
            flush();
            sleep($keepaliveInterval);
        }

        // Clean up session on disconnect
        Craft::$app->cache->delete("mcp_session_{$sessionId}");
        exit(0);
    }
    
    /**
     * Validate access to observability endpoints (metrics, health)
     * Uses the same IP allowlist as the main MCP endpoints
     *
     * @throws ForbiddenHttpException if IP not allowed
     */
    private function validateObservabilityAccess(): void
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $allowedIps = $config['security']['allowedIps'] ?? [];

        // If allowlist is configured, enforce it for observability endpoints
        if (!empty($allowedIps)) {
            $remoteIp = Craft::$app->request->getUserIP();
            if (!IpValidator::isAllowed($remoteIp, $allowedIps)) {
                Craft::warning("Observability access denied for IP: {$remoteIp}", 'mcp-wrapper');
                throw new ForbiddenHttpException('Access denied: IP not whitelisted');
            }
        }
    }

    /**
     * Metrics endpoint for monitoring (Prometheus-compatible format)
     * Returns basic MCP server metrics
     *
     * GET /mcp/metrics - Returns metrics in Prometheus text format
     *
     * Note: Access is restricted by IP allowlist when configured
     */
    public function actionMetrics(): Response
    {
        $this->validateObservabilityAccess();

        $this->response->format = Response::FORMAT_RAW;
        $this->response->headers->set('Content-Type', 'text/plain; version=0.0.4');

        $metrics = [];

        // Server info
        $metrics[] = '# HELP mcp_server_info MCP server information';
        $metrics[] = '# TYPE mcp_server_info gauge';
        $metrics[] = 'mcp_server_info{version="2.7.0",protocol="2025-11-25"} 1';

        // Rate limit status (if available)
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $rateLimit = $config['security']['rateLimit'] ?? 100;
        $metrics[] = '# HELP mcp_rate_limit_max Maximum requests per window';
        $metrics[] = '# TYPE mcp_rate_limit_max gauge';
        $metrics[] = "mcp_rate_limit_max {$rateLimit}";

        // Schema count
        $schemas = $config['schemas'] ?? [];
        $metrics[] = '# HELP mcp_schemas_total Total number of configured schemas';
        $metrics[] = '# TYPE mcp_schemas_total gauge';
        $metrics[] = 'mcp_schemas_total ' . count($schemas);

        // Tools count (if registry is available)
        try {
            $toolRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('toolRegistry');
            $manualTools = $toolRegistry->discoverManualTools();
            $metrics[] = '# HELP mcp_manual_tools_total Total number of manual tools registered';
            $metrics[] = '# TYPE mcp_manual_tools_total gauge';
            $metrics[] = 'mcp_manual_tools_total ' . count($manualTools);
        } catch (\Exception $e) {
            // Skip if unavailable
        }

        // Server uptime approximation (using PHP's process start time)
        $metrics[] = '# HELP mcp_server_start_time_seconds Unix timestamp of server start';
        $metrics[] = '# TYPE mcp_server_start_time_seconds gauge';
        $metrics[] = 'mcp_server_start_time_seconds ' . ($_SERVER['REQUEST_TIME'] ?? time());

        return $this->response->setData(implode("\n", $metrics) . "\n");
    }

    /**
     * Health check endpoint for monitoring
     * Returns server health status with optional detailed checks
     *
     * GET /mcp/health - Quick health check
     * GET /mcp/health?detailed=1 - Detailed health with component checks
     *
     * Note: Access is restricted by IP allowlist when configured
     */
    public function actionHealth(): Response
    {
        $this->validateObservabilityAccess();

        $this->response->format = Response::FORMAT_JSON;
        $detailed = (bool) Craft::$app->request->getQueryParam('detailed', false);

        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '2.7.0',
            'protocolVersion' => '2025-11-25',
        ];

        if ($detailed) {
            $checks = [];
            $overallHealthy = true;

            // Check 1: Cache availability
            try {
                $cacheKey = 'mcp_health_check_' . time();
                Craft::$app->cache->set($cacheKey, 'test', 5);
                $cacheValue = Craft::$app->cache->get($cacheKey);
                Craft::$app->cache->delete($cacheKey);
                $checks['cache'] = [
                    'status' => $cacheValue === 'test' ? 'healthy' : 'degraded',
                    'message' => $cacheValue === 'test' ? 'Cache operational' : 'Cache read/write mismatch',
                ];
            } catch (\Exception $e) {
                $checks['cache'] = ['status' => 'unhealthy', 'message' => 'Cache unavailable'];
                $overallHealthy = false;
            }

            // Check 2: Database connectivity
            try {
                Craft::$app->db->createCommand('SELECT 1')->queryScalar();
                $checks['database'] = ['status' => 'healthy', 'message' => 'Database connected'];
            } catch (\Exception $e) {
                $checks['database'] = ['status' => 'unhealthy', 'message' => 'Database unavailable'];
                $overallHealthy = false;
            }

            // Check 3: GraphQL schema availability
            try {
                $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
                $schemas = $config['schemas'] ?? [];
                $checks['schemas'] = [
                    'status' => count($schemas) > 0 ? 'healthy' : 'degraded',
                    'message' => count($schemas) . ' schema(s) configured',
                    'count' => count($schemas),
                ];
            } catch (\Exception $e) {
                $checks['schemas'] = ['status' => 'degraded', 'message' => 'Config unavailable'];
            }

            // Check 4: Tool registry
            try {
                $toolRegistry = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('toolRegistry');
                $tools = $toolRegistry->discoverManualTools();
                $checks['tools'] = [
                    'status' => 'healthy',
                    'message' => count($tools) . ' manual tool(s) registered',
                    'count' => count($tools),
                ];
            } catch (\Exception $e) {
                $checks['tools'] = ['status' => 'degraded', 'message' => 'Tool registry error'];
            }

            $health['checks'] = $checks;
            $health['status'] = $overallHealthy ? 'healthy' : 'degraded';
        }

        // Set appropriate status code
        $statusCode = match ($health['status']) {
            'healthy' => 200,
            'degraded' => 200, // Still operational
            'unhealthy' => 503,
            default => 200,
        };

        $this->response->statusCode = $statusCode;
        return $this->asJson($health);
    }

    /**
     * Messages endpoint for client-to-server communication in SSE transport
     */
    public function actionMessages()
    {
        $this->response->format = Response::FORMAT_JSON;
        
        $sessionId = Craft::$app->request->getQueryParam('sessionId');
        if (!$sessionId) {
            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Missing sessionId parameter',
                ],
                'id' => null,
            ]);
        }
        
        // Get schema handle from session
        $schemaHandle = Craft::$app->cache->get("mcp_session_{$sessionId}");
        if (!$schemaHandle) {
            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'Invalid or expired session',
                ],
                'id' => null,
            ]);
        }
        
        try {
            // Get JSON-RPC request from body
            $jsonRpcRequest = Craft::$app->request->getBodyParams();
            if (!$jsonRpcRequest || !isset($jsonRpcRequest['method'])) {
                $jsonRpcRequest = json_decode(Craft::$app->request->getRawBody(), true);
            }
            
            if (!$jsonRpcRequest) {
                return $this->asJson([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                    ],
                    'id' => null,
                ]);
            }
            
            // Add schema handle to params
            if (!isset($jsonRpcRequest['params'])) {
                $jsonRpcRequest['params'] = [];
            }
            $jsonRpcRequest['params']['schemaHandle'] = $schemaHandle;
            
            // Handle request
            $mcpServer = \rocketpark\mcpwrapper\McpWrapper::getInstance()->get('mcpServer');
            $response = $mcpServer->handleRequest($jsonRpcRequest);
            
            return $this->asJson($response);
            
        } catch (\Exception $e) {
            Craft::error("MCP messages endpoint error: {$e->getMessage()}", 'mcp-wrapper');

            $errorMessage = Craft::$app->config->general->devMode
                ? 'Internal error: ' . $e->getMessage()
                : 'An internal error occurred. Please try again later.';

            return $this->asJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $errorMessage,
                ],
                'id' => null,
            ]);
        }
    }
}
