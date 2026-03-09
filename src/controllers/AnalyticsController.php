<?php

namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\mcpwrapper\services\RequestLoggerService;
use yii\web\Response;

/**
 * Analytics Dashboard Controller
 * 
 * Provides analytics visualization for MCP request data.
 * 
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.6.0
 */
class AnalyticsController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;
    
    /**
     * Dashboard view
     * 
     * @return Response
     */
    public function actionIndex(?string $schemaHandle = null, int $days = 7): Response
    {
        $this->requirePermission('mcp-wrapper:viewAnalytics');
        
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $schemas = array_keys($config['schemas'] ?? []);
        
        // Default to first schema if none specified
        if (!$schemaHandle && !empty($schemas)) {
            $schemaHandle = $schemas[0];
        }
        
        $canExport = Craft::$app->getUser()->checkPermission('mcp-wrapper:exportAnalytics');
        
        return $this->renderTemplate('mcp-wrapper/analytics/index', [
            'schemas' => $schemas,
            'selectedSchema' => $schemaHandle,
            'days' => $days,
            'canExport' => $canExport,
            'selectedSubnavItem' => 'analytics',
        ]);
    }
    
    /**
     * Get analytics data as JSON
     * 
     * @param string|null $schemaHandle Schema to analyze (null = all)
     * @param int $days Number of days to analyze
     * @return Response
     */
    public function actionData(?string $schemaHandle = null, int $days = 7): Response
    {
        $this->requirePermission('mcp-wrapper:viewAnalytics');
        $this->requireAcceptsJson();
        
        /** @var RequestLoggerService $logger */
        $logger = Craft::$app->getModule('mcp-wrapper')->get('requestLogger');
        
        $analytics = $logger->getAnalytics($days, $schemaHandle);
        
        return $this->asJson([
            'success' => true,
            'data' => $analytics,
            'schema' => $schemaHandle ?? 'all',
            'days' => $days,
            'generatedAt' => date('c'),
        ]);
    }
    
    /**
     * Export analytics data as CSV
     * 
     * @param string|null $schemaHandle Schema to analyze
     * @param int $days Number of days to analyze
     * @return Response
     */
    public function actionExport(?string $schemaHandle = null, int $days = 7): Response
    {
        $this->requirePermission('mcp-wrapper:exportAnalytics');
        
        /** @var RequestLoggerService $logger */
        $logger = Craft::$app->getModule('mcp-wrapper')->get('requestLogger');
        
        $analytics = $logger->getAnalytics($days, $schemaHandle);
        
        // Build CSV
        $csv = [];
        $csv[] = ['Metric', 'Value'];
        $csv[] = ['Total Requests', $analytics['total_requests'] ?? 0];
        $csv[] = ['Success Rate', ($analytics['success_rate'] ?? 0) . '%'];
        $csv[] = ['Average Duration (ms)', $analytics['avg_duration_ms'] ?? 0];
        $csv[] = [''];
        $csv[] = ['Top Tools', 'Count', 'Avg Duration (ms)'];
        
        foreach (($analytics['top_tools'] ?? []) as $tool) {
            $csv[] = [
                $tool['tool'] ?? 'unknown',
                $tool['count'] ?? 0,
                $tool['avg_duration_ms'] ?? 0
            ];
        }
        
        $csv[] = [''];
        $csv[] = ['Slowest Requests', 'Tool', 'Duration (ms)', 'Timestamp'];
        
        foreach (($analytics['slowest_requests'] ?? []) as $request) {
            $csv[] = [
                '',
                $request['tool'] ?? 'unknown',
                $request['duration_ms'] ?? 0,
                $request['timestamp'] ?? ''
            ];
        }
        
        // Convert to CSV string
        $output = fopen('php://temp', 'w');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        $filename = 'mcp-analytics-' . ($schemaHandle ?? 'all') . '-' . date('Y-m-d') . '.csv';
        
        return $this->response
            ->sendContentAsFile($csvContent, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }
}
