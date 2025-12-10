<?php
namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Entries;
use craft\fields\Tags;
use craft\fields\Users;
use GuzzleHttp\Client;

class ManifestBuilderService extends Component
{
    private string $cacheDir = '@storage/runtime/mcp';

    public function buildManifest(string $token, string $schemaHandle, bool $forceRebuild = false): array
    {
        try {
            $path = Craft::getAlias("{$this->cacheDir}/manifest-{$schemaHandle}.json");

            if (!$forceRebuild && file_exists($path)) {
                Craft::info("Loading cached manifest for schema: {$schemaHandle}", 'mcp-wrapper');
                $cached = json_decode(file_get_contents($path), true);
                if ($cached) return $cached;
            }

            Craft::info("Generating manifest for schema: {$schemaHandle}", 'mcp-wrapper');
            $manifest = $this->generateManifest($token, $schemaHandle);

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
                Craft::info("Created cache directory: " . dirname($path), 'mcp-wrapper');
            }
            
            file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Craft::info("Manifest cached at: {$path}", 'mcp-wrapper');

            return $manifest;
        } catch (\Exception $e) {
            Craft::error("Failed to build manifest: {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            throw $e;
        }
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
        try {
            // Try to use Craft services directly first (works in production without introspection)
            return $this->generateManifestFromCraftServices($schemaHandle);
        } catch (\Exception $e) {
            Craft::warning("Failed to generate manifest from Craft services, falling back to GraphQL introspection: {$e->getMessage()}", 'mcp-wrapper');
            
            // Fallback to GraphQL introspection
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
    }

    /**
     * Generate manifest directly from Craft services without GraphQL introspection
     * This works in production environments where introspection might be disabled
     */
    private function generateManifestFromCraftServices(string $schemaHandle): array
    {
        Craft::info("Generating manifest from Craft services for schema: {$schemaHandle}", 'mcp-wrapper');
        
        $sections = Craft::$app->getEntries()->getAllSections();
        $tools = [];
        
        foreach ($sections as $section) {
            $tool = $this->buildToolForSection($section->handle, $schemaHandle);
            if ($tool) {
                $tools[] = $tool;
            }
        }
        
        if (empty($tools)) {
            throw new \Exception("No sections found for manifest generation");
        }
        
        Craft::info("Generated " . count($tools) . " tools from Craft services", 'mcp-wrapper');
        
        return [
            'version' => '1.1',
            'schemaHandle' => $schemaHandle,
            'description' => "MCP manifest for GraphQL schema '{$schemaHandle}' (generated from Craft services).",
            'tools' => $tools,
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

    private function describeField(FieldInterface $field): array
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

    private function mapFieldType(FieldInterface $field): string
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
        try {
            $client = new Client([
                'base_uri' => Craft::$app->request->getHostInfo(),
                'timeout' => 10,
            ]);

            $query = <<<'GQL'
            { __schema { types { name fields { name type { name kind } } } } }
            GQL;

            Craft::info("Introspecting GraphQL schema", 'mcp-wrapper');
            
            $response = $client->post('/api', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => ['query' => $query],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['data']['__schema'])) {
                Craft::error("GraphQL introspection failed: missing __schema", 'mcp-wrapper');
                throw new \Exception('GraphQL introspection returned invalid data');
            }
            
            Craft::info("GraphQL introspection successful", 'mcp-wrapper');
            return $data['data']['__schema'];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Craft::error("GraphQL introspection request failed: {$e->getMessage()}", 'mcp-wrapper');
            if ($e->hasResponse()) {
                $body = $e->getResponse()->getBody()->getContents();
                Craft::error("Response body: {$body}", 'mcp-wrapper');
            }
            throw new \Exception('Failed to introspect GraphQL schema: ' . $e->getMessage());
        } catch (\Exception $e) {
            Craft::error("Unexpected error during GraphQL introspection: {$e->getMessage()}", 'mcp-wrapper');
            throw $e;
        }
    }
}