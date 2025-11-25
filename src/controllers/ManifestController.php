<?php
namespace rocketpark\mcpwrapper\controllers;

use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ManifestController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(string $schemaHandle): Response
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $token  = $config['schemas'][$schemaHandle] ?? null;
        if (!$token) {
            throw new NotFoundHttpException("Unknown schema handle: {$schemaHandle}");
        }

        $force = Craft::$app->request->getQueryParam('force') === '1';

        $manifest = Craft::$app->getModule('mcpwrapper')
            ->get('manifestBuilder')
            ->buildManifest($token, $schemaHandle, $force);

        return $this->asJson($manifest);
    }
}