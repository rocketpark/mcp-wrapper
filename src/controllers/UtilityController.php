<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UtilityController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    /**
     * Manifest manager page within the plugin CP section
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('mcp-wrapper:manageManifest');

        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $schemas = [];

        foreach (($config['schemas'] ?? []) as $handle => $token) {
            $path = Craft::getAlias("@storage/runtime/mcp/manifest-{$handle}.json");
            $exists = file_exists($path);
            $schemas[] = [
                'handle' => $handle,
                'exists' => $exists,
                'lastModified' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
                'urlView' => "/actions/mcp-wrapper/utility/view-manifest?schema={$handle}",
                'urlRebuild' => "/actions/mcp-wrapper/utility/rebuild-manifest?schema={$handle}",
            ];
        }

        return $this->renderTemplate('mcp-wrapper/manifest/index', [
            'schemas' => $schemas,
            'selectedSubnavItem' => 'manifest',
        ]);
    }

    public function actionViewManifest(string $schema): Response
    {
        $this->requirePermission('mcp-wrapper:manageManifest');

        // Validate schema against config to prevent path traversal
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        if (!isset($config['schemas'][$schema])) {
            throw new NotFoundHttpException("Unknown schema {$schema}");
        }

        $path = Craft::getAlias("@storage/runtime/mcp/manifest-{$schema}.json");
        if (!file_exists($path)) {
            throw new NotFoundHttpException("No manifest for {$schema}");
        }

        $json = file_get_contents($path);
        return $this->asJson(json_decode($json, true));
    }

    public function actionRebuildManifest(string $schema): Response
    {
        $this->requirePermission('mcp-wrapper:manageManifest');

        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $token  = $config['schemas'][$schema] ?? null;
        if (!$token) {
            throw new NotFoundHttpException("Unknown schema {$schema}");
        }

        $builder = Craft::$app->getModule('mcpwrapper')->get('manifestBuilder');
        $builder->buildManifest($token, $schema, true);

        return $this->asJson([
            'status' => 'success',
            'schema' => $schema,
            'lastModified' => date('Y-m-d H:i:s'),
        ]);
    }
}