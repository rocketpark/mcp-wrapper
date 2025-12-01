<?php
namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Entries;
use craft\fields\Tags;
use craft\fields\Users;
use craft\fields\BaseField;
use GuzzleHttp\Client;

class ManifestBuilderService extends Component
{
    private string $cacheDir = '@storage/runtime/mcp';

    public function buildManifest(string $token, string $schemaHandle, bool $forceRebuild = false): array
    {
        $path = Craft::getAlias("{$this->cacheDir}/manifest-{$schemaHandle}.json");

        if (!$forceRebuild && file_exists($path)) {
            $cached = json_decode(file_get_contents($path), true);
            if ($cached) return $cached;
        }

        $manifest = $this->generateManifest($token, $schemaHandle);

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifest;
    }

    public function clearCache(?string $schemaHandle = null): void
    {
        $pattern = $schemaHandle
            ? Craft::getAlias("{$this->cacheDir}/manifest-{$schemaHandle}.json")
            : Craft::getAlias("{$this->cacheDir}/manifest-*.json");

        foreach (glob($pattern) as $file) @unlink($file);
    }

    private function generateManifest(string $token, string $schemaHandle): array
    {
        $schemaData = $this->introspectGraphQL($token);
        $types = $schemaData['types'] ?? [];
        $entryTypes = array_filter($types, fn($t) =>
            isset($t['fields']) && str_contains(strtolower($t['name']), 'entry')
        );

        $tools = [];
        foreach ($entryTypes as $type) {
            $sectionHandle = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $type['name']));
            $tools[] = $this->buildToolForSection($sectionHandle, $schemaHandle);
        }

        return [
            'version' => '1.1',
            'schemaHandle' => $schemaHandle,
            'description' => "Auto-generated MCP manifest (with relationships) for GraphQL schema '{$schemaHandle}'.",
            'tools' => array_values(array_filter($tools)),
        ];
    }

    private function buildToolForSection(string $sectionHandle, string $schemaHandle): ?array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) return null;

        $fields = [];
        foreach ($section->getEntryTypes() as $entryType) {
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                $fields[] = $this->describeField($field);
            }
        }

        return [
            'name' => "query_{$sectionHandle}",
            'description' => "Query {$sectionHandle} entries (schema {$schemaHandle}) with relationships.",
            'endpoint' => '/api',
            'method' => 'POST',
            'parameters' => [
                'query' => "query { entries(section: \"{$sectionHandle}\") { " .
                    implode(' ', array_column($fields, 'handle')) . " } }",
                'variables' => [
                    'section' => $sectionHandle,
                    'limit' => 'integer',
                    'filters' => $this->generateFilterSchema($fields),
                ],
            ],
            'returns' => ['entries' => $fields],
        ];
    }

    private function describeField(BaseField $field): array
    {
        $base = [
            'handle' => $field->handle,
            'type' => $this->mapFieldType($field),
            'instructions' => strip_tags((string)$field->instructions),
        ];

        // Relationship metadata
        if ($field instanceof Entries)
            $base['relationTo'] = ['elementType' => 'entry', 'sources' => $this->extractSourceHandles($field->sources)];
        elseif ($field instanceof Categories)
            $base['relationTo'] = ['elementType' => 'category', 'sources' => $this->extractSourceHandles($field->sources)];
        elseif ($field instanceof Assets)
            $base['relationTo'] = ['elementType' => 'asset', 'volumes' => $this->extractSourceHandles($field->sources)];
        elseif ($field instanceof Users)
            $base['relationTo'] = ['elementType' => 'user'];
        elseif ($field instanceof Tags)
            $base['relationTo'] = ['elementType' => 'tag'];

        return $base;
    }

    private function extractSourceHandles($sources): array
    {
        if (!$sources) return [];
        $handles = [];
        foreach ($sources as $src) {
            if ($src === '*') $handles[] = 'all';
            elseif (str_starts_with($src, 'section:')) $handles[] = substr($src, 8);
            elseif (str_starts_with($src, 'volume:')) $handles[] = substr($src, 7);
            elseif (str_starts_with($src, 'group:')) $handles[] = substr($src, 6);
        }
        return $handles;
    }

    private function mapFieldType(BaseField $field): string
    {
        $type = (new \ReflectionClass($field))->getShortName();

        return match ($type) {
            'PlainText' => 'string',
            'Lightswitch' => 'boolean',
            'Date' => 'datetime',
            'Number' => 'number',
            'Dropdown', 'RadioButtons' => 'enum',
            'Entries', 'Assets', 'Categories', 'Users', 'Tags' => 'relation',
            default => 'string',
        };
    }

    private function generateFilterSchema(array $fields): array
    {
        $filters = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['string', 'number', 'datetime'])) {
                $filters[$field['handle']] = ['eq' => $field['type']];
            }
            if ($field['type'] === 'relation' && !empty($field['relationTo'])) {
                $filters[$field['handle']] = [
                    'relatedTo' => $field['relationTo']['elementType'],
                    'byHandle' => 'string',
                ];
            }
        }
        return $filters;
    }

    private function introspectGraphQL(string $token): array
    {
        $client = new Client([
            'base_uri' => Craft::$app->request->getHostInfo(),
            'timeout' => 10,
        ]);

        $query = <<<'GQL'
        { __schema { types { name fields { name type { name kind } } } } }
        GQL;

        $response = $client->post('/api', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => ['query' => $query],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['data']['__schema'] ?? [];
    }
}