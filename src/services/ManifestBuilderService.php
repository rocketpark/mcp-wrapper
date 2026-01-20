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
            
            // Find the Query type to get actual section query fields
            $queryType = null;
            foreach ($types as $type) {
                if ($type['name'] === 'Query') {
                    $queryType = $type;
                    break;
                }
            }
            
            if (!$queryType || !isset($queryType['fields'])) {
                throw new \Exception('Could not find Query type in GraphQL schema');
            }
            
            $tools = [];
            foreach ($queryType['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                
                // Look for section query fields (e.g., "servicesEntries", "ourTeamEntries")
                if (str_ends_with($fieldName, 'Entries')) {
                    // Extract section handle (e.g., "servicesEntries" -> "services")
                    $sectionHandle = substr($fieldName, 0, -7); // Remove "Entries" suffix
                    
                    Craft::info("Processing GraphQL query field '{$fieldName}' -> section handle '{$sectionHandle}'", 'mcp-wrapper');
                    
                    $tool = $this->buildToolForSection($sectionHandle, $schemaHandle);
                    if ($tool) {
                        $tools[] = $tool;
                        Craft::info("Successfully built tool: {$tool['name']}", 'mcp-wrapper');
                    } else {
                        Craft::warning("Failed to build tool for section '{$sectionHandle}' (from GraphQL query field '{$fieldName}')", 'mcp-wrapper');
                    }
                }
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
        
        // Get all tools (GraphQL + manual)
        $toolRegistry = Craft::$app->getModule('mcp-wrapper')->get('toolRegistry');
        $tools = $toolRegistry->getAllTools($schemaHandle);
        
        if (empty($tools)) {
            throw new \Exception("No tools available for manifest generation");
        }
        
        // Apply security filters
        $tools = $this->applySecurityFilters($tools);
        
        Craft::info("Generated manifest with " . count($tools) . " tools", 'mcp-wrapper');
        
        return [
            'version' => '1.2',
            'schemaHandle' => $schemaHandle,
            'description' => "MCP manifest for GraphQL schema '{$schemaHandle}' with manual and auto-generated tools.",
            'tools' => $tools,
        ];
    }

    /**
     * Get only the GraphQL auto-generated tools (for ToolRegistry)
     * 
     * @param string $schemaHandle GraphQL schema handle
     * @return array Array of GraphQL tool definitions
     */
    public function getGeneratedTools(string $schemaHandle): array
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        $tools = [];
        
        foreach ($sections as $section) {
            $tool = $this->buildToolForSection($section->handle, $schemaHandle);
            if ($tool) {
                $tools[] = $tool;
            }
        }
        
        return $tools;
    }

    /**
     * Apply security filters to tools based on config
     */
    private function applySecurityFilters(array $tools): array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $security = $config['security'] ?? [];
        
        $enableDangerousTools = $security['enableDangerousTools'] ?? true;
        $disabledTools = $security['disabledTools'] ?? [];
        
        return array_values(array_filter($tools, function($tool) use ($enableDangerousTools, $disabledTools) {
            // Filter disabled tools
            if (in_array($tool['name'], $disabledTools)) {
                Craft::info("Tool disabled by config: {$tool['name']}", 'mcp-wrapper');
                return false;
            }
            
            // Filter dangerous tools if not enabled
            $isDangerous = isset($tool['dangerous']) && $tool['dangerous'];
            if ($isDangerous && !$enableDangerousTools) {
                Craft::info("Dangerous tool filtered out: {$tool['name']}", 'mcp-wrapper');
                return false;
            }
            
            return true;
        }));
    }

    private function buildToolForSection(string $sectionHandle, string $schemaHandle): ?array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            Craft::warning("Section not found: {$sectionHandle}", 'mcp-wrapper');
            return null;
        }

        Craft::info("Found section: {$sectionHandle}, getting entry types...", 'mcp-wrapper');
        $entryTypes = $section->getEntryTypes();
        Craft::info("Entry types type: " . gettype($entryTypes) . ", value: " . json_encode($entryTypes), 'mcp-wrapper');
        
        $fields = [];
        foreach ($entryTypes as $entryType) {
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                $fields[] = $this->describeField($field);
            }
        }

        return [
            'name' => "query_{$sectionHandle}",
            'description' => "Query {$sectionHandle} entries (schema {$schemaHandle}) with relationships.",
            'endpoint' => '/api',
            'method' => 'POST',
            'dangerous' => false, // GraphQL queries are read-only
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
        if (!is_array($sources)) {
            Craft::warning("extractSourceHandles received non-array sources: " . gettype($sources) . " = " . json_encode($sources), 'mcp-wrapper');
            return [];
        }
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
            
            // Log all Entry types found
            $entryTypes = array_filter($data['data']['__schema']['types'], function($type) {
                return isset($type['name']) && str_ends_with($type['name'], '_Entry');
            });
            $entryTypeNames = array_map(fn($t) => $t['name'], $entryTypes);
            Craft::info("GraphQL Entry types found: " . implode(', ', $entryTypeNames), 'mcp-wrapper');
            
            // Log full schema data for debugging
            Craft::info("Full GraphQL schema data: " . json_encode($data['data']['__schema'], JSON_PRETTY_PRINT), 'mcp-wrapper');
            
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