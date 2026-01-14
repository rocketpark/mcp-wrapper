<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\mcpwrapper\support\IpValidator;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ManifestController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Validate IP access before any action
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Check IP whitelist
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $allowedIps = $config['security']['allowedIps'] ?? [];
        
        if (!empty($allowedIps)) {
            $remoteIp = Craft::$app->request->getUserIP();
            
            if (!IpValidator::isAllowed($remoteIp, $allowedIps)) {
                Craft::warning("MCP access denied for IP: {$remoteIp}", 'mcp-wrapper');
                throw new ForbiddenHttpException('Access denied: IP not whitelisted');
            }
            
            Craft::info("MCP access granted for IP: {$remoteIp}", 'mcp-wrapper');
        }

        return true;
    }

    public function actionIndex(string $schemaHandle): Response
    {
        try {
            Craft::info("Manifest request for schema: {$schemaHandle}", 'mcp-wrapper');
            
            $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
            $token  = $config['schemas'][$schemaHandle] ?? null;
            
            if ($token === null) {
                Craft::error("Unknown schema handle: {$schemaHandle}", 'mcp-wrapper');
                throw new NotFoundHttpException("Unknown schema handle: {$schemaHandle}");
            }

            $force = Craft::$app->request->getQueryParam('force') === '1';
            if ($force) {
                Craft::info("Force rebuild requested for schema: {$schemaHandle}", 'mcp-wrapper');
            }

            $manifest = \rocketpark\mcpwrapper\McpWrapper::getInstance()
                ->get('manifestBuilder')
                ->buildManifest($token, $schemaHandle, $force);

            Craft::info("Manifest successfully generated for schema: {$schemaHandle}", 'mcp-wrapper');
            return $this->asJson($manifest);
        } catch (NotFoundHttpException $e) {
            throw $e; // Re-throw to let Craft handle 404
        } catch (\Exception $e) {
            Craft::error("Manifest generation error: {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            
            return $this->asJson([
                'error' => 'Failed to generate manifest: ' . $e->getMessage(),
            ], 500);
        }
    }
}