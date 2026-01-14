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
use rocketpark\mcpwrapper\services\ToolRegistryService;
use rocketpark\mcpwrapper\tools\EntryTools;

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
                'toolRegistry' => ToolRegistryService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Set controller namespace for proper routing
        $this->controllerNamespace = 'rocketpark\\mcpwrapper\\controllers';

        self::$plugin = $this;

        // Register site URL rules for public manifest endpoint
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['mcp/manifest/<schemaHandle:[a-zA-Z0-9_-]+>'] = 'mcp-wrapper/manifest/index';
                $event->rules['mcp/<schemaHandle:[a-zA-Z0-9_-]+>'] = 'mcp-wrapper/mcp/index';
            }
        );

        // Register CP URL rules for utility
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['utilities/mcp-wrapper'] = 'mcp-wrapper/utility/index';
                $event->rules['utilities/mcp-wrapper/rebuild/<schema:[a-zA-Z0-9_-]+>'] = 'mcp-wrapper/utility/rebuild';
                // Allow standard action routes for utility controller
                $event->rules['actions/mcp-wrapper/utility/<action:\w+>'] = 'mcp-wrapper/utility/<action>';
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
        
        // Register manual tool classes
        $this->registerToolClasses();
        
        Craft::info('MCP Wrapper initialized', 'mcp-wrapper');
    }

    /**
     * Register manual tool classes with the ToolRegistry
     */
    private function registerToolClasses(): void
    {
        $toolRegistry = $this->get('toolRegistry');
        
        // Register built-in tool classes
        $toolRegistry->registerToolClass(EntryTools::class);
        
        // TODO: Add more tool classes here
        // $toolRegistry->registerToolClass(AssetTools::class);
        // $toolRegistry->registerToolClass(UserTools::class);
    }
}