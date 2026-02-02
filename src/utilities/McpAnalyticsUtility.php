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
        // Redirect to analytics controller
        Craft::$app->getResponse()->redirect('mcp-wrapper/analytics')->send();
        return '';
    }
}
