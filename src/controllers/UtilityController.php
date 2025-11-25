<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UtilityController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionViewManifest(string $schema): Response
    {
        $path = Craft::getAlias("@storage/runtime/mcp/manifest-{$schema}.json");
        if (!file_exists($path)) {
            throw new NotFoundHttpException("No manifest for {$schema}");
        }

        $json = file_get_contents($path);
        return $this->asJson(json_decode($json, true));
    }

    public function actionRebuildManifest(string $schema): Response
    {
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