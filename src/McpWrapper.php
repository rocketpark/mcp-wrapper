<?php
namespace rocketpark\mcpwrapper;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use rocketpark\mcpwrapper\utilities\McpManifestUtility;
use yii\base\Event;

use rocketpark\mcpwrapper\services\ManifestBuilderService;
use rocketpark\mcpwrapper\services\McpServerService;

class McpWrapper extends Plugin
{
    public string $schemaVersion = '1.0.0';

    /**
     * @var Plugin|null
     * @property-read Plugin $plugin
     */
    public static ?Plugin $plugin = null;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'manifestBuilder' => ManifestBuilderService::class,
                'mcpServer' => McpServerService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // Register Utility in the CP
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            fn(RegisterComponentTypesEvent $e) => $e->types[] = McpManifestUtility::class
        );

        // Auto-clear cache when project config changes
        Event::on(
            \craft\services\ProjectConfig::class,
            \craft\services\ProjectConfig::EVENT_REBUILD,
            fn() => $this->get('manifestBuilder')->clearCache()
        );
    }
}