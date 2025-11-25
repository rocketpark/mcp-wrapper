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

     /**
     * @var Plugin|null
     * @property-read Plugin $plugin
     */
    public static ?Plugin $plugin = null;

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'manifestBuilder' => ManifestBuilderService::class,
            'mcpServer' => McpServerService::class,
        ]);

        // Register Utility in the CP
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            fn(RegisterComponentTypesEvent $e) => $e->types[] = McpManifestUtility::class
        );

        // Auto-clear cache when project config or GQL schemas change
        Event::on(
            \craft\services\ProjectConfig::class,
            \craft\services\ProjectConfig::EVENT_REBUILD,
            fn() => $this->get('manifestBuilder')->clearCache()
        );

        Event::on(
            \craft\services\Gql::class,
            \craft\services\Gql::EVENT_AFTER_SAVE_SCHEMA,
            fn() => $this->get('manifestBuilder')->clearCache()
        );
    }
}