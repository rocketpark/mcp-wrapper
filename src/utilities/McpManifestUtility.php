<?php
namespace rocketpark\mcpwrapper\utilities;

use Craft;
use craft\base\Utility;

class McpManifestUtility extends Utility
{
    public static function displayName(): string
    {
        return 'MCP Manifest Manager';
    }

    public static function id(): string
    {
        return 'mcp-manifest-manager';
    }

    public static function iconPath(): ?string
    {
        return Craft::getAlias('@app/icons/cogs.svg');
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        
        $html = $view->renderTemplate('mcp-wrapper/utility', [
            'schemas' => self::getSchemaInfo(),
        ]);
        
        $view->setTemplateMode($oldMode);
        return $html;
    }

    private static function getSchemaInfo(): array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $info = [];

        foreach ($config['schemas'] as $handle => $token) {
            $path = Craft::getAlias("@storage/runtime/mcp/manifest-{$handle}.json");
            $exists = file_exists($path);
            $info[] = [
                'handle' => $handle,
                'exists' => $exists,
                'lastModified' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
                'urlView' => "/actions/mcpwrapper/utility/view-manifest?schema={$handle}",
                'urlRebuild' => "/actions/mcpwrapper/utility/rebuild-manifest?schema={$handle}",
            ];
        }
        return $info;
    }
}