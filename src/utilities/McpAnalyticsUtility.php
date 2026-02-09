<?php

namespace rocketpark\mcpwrapper\utilities;

use Craft;
use craft\base\Utility;

/**
 * MCP Analytics Utility
 * 
 * Provides access to the MCP performance analytics dashboard
 * 
 * @author Rocket Park <hello@rocketpark.com>
 * @since 2.6.0
 */
class McpAnalyticsUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('mcp-wrapper', 'MCP Analytics');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'mcp-analytics';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        // Render analytics dashboard inline
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $schemas = array_keys($config['schemas'] ?? []);
        $selectedSchema = $schemas[0] ?? null;
        
        return Craft::$app->getView()->renderTemplate('mcp-wrapper/analytics/index', [
            'schemas' => $schemas,
            'selectedSchema' => $selectedSchema,
            'days' => 7,
        ]);
    }
}
