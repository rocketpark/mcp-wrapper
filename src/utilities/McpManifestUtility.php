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
        $templatesPath = Craft::getAlias('@rocketpark/mcpwrapper/templates');
        return Craft::$app->view->renderTemplate($templatesPath . '/utility.twig', [
            'schemas' => self::getSchemaInfo(),
        ]);
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