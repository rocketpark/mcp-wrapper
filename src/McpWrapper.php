<?php
namespace rocketpark\mcpwrapper;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Utilities;
use craft\web\UrlManager;
use rocketpark\mcpwrapper\utilities\McpManifestUtility;
use yii\base\Event;

use rocketpark\mcpwrapper\services\ManifestBuilderService;
use rocketpark\mcpwrapper\services\McpServerService;

class McpWrapper extends Plugin
{
    public string $schemaVersion = '1.0.0';

    /**
     * @var McpWrapper|null
     */
    public static ?McpWrapper $plugin = null;

    /**
     * Returns the plugin instance
     */
    public static function getInstance(): ?McpWrapper
    {
        return self::$plugin;
    }

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

        // Register site URL rules for public manifest endpoint
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['mcp/manifest/<schemaHandle:[a-zA-Z0-9_-]+>'] = 'mcp-wrapper/manifest/index';
            }
        );

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