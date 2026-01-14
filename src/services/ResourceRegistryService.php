<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use rocketpark\mcpwrapper\McpWrapper;

/**
 * Resource Registry Service
 * 
 * Manages MCP Resources - URI-based read-only data access for AI assistants
 * Resources provide structured access to Craft CMS content and configuration
 */
class ResourceRegistryService extends Component
{
    /**
     * List all available resources for a schema
     * 
     * @param string $schemaHandle GraphQL schema handle
     * @return array Array of resource definitions
     */
    public function listResources(string $schemaHandle): array
    {
        return [
            [
                'uri' => "craft://{$schemaHandle}/schema",
                'name' => 'GraphQL Schema',
                'description' => 'Complete GraphQL schema definition (SDL)',
                'mimeType' => 'text/plain',
            ],
            [
                'uri' => "craft://{$schemaHandle}/sections",
                'name' => 'Content Sections',
                'description' => 'List of all content sections with their handles and types',
                'mimeType' => 'application/json',
            ],
            [
                'uriTemplate' => "craft://{$schemaHandle}/sections/{handle}",
                'name' => 'Section Details',
                'description' => 'Detailed field layout and configuration for a specific section',
                'mimeType' => 'application/json',
            ],
            [
                'uriTemplate' => "craft://{$schemaHandle}/entries/{section}",
                'name' => 'Section Entries',
                'description' => 'Recent entries from a section (limit 50, ordered by dateCreated DESC)',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => "craft://{$schemaHandle}/volumes",
                'name' => 'Asset Volumes',
                'description' => 'List of all asset volumes',
                'mimeType' => 'application/json',
            ],
        ];
    }
    
    /**
     * Read a specific resource by URI
     * 
     * @param string $uri Resource URI (e.g., craft://ai/schema)
     * @param string $schemaHandle GraphQL schema handle
     * @return array Resource content
     */
    public function readResource(string $uri, string $schemaHandle): array
    {
        // Parse URI: craft://{schema}/{type}/{param}
        $parts = parse_url($uri);
        $path = trim($parts['path'] ?? '', '/');
        $segments = explode('/', $path);
        
        // Validate schema handle matches
        if (($segments[0] ?? null) !== $schemaHandle) {
            throw new \Exception("Schema handle mismatch in URI: {$uri}");
        }
        
        $resourceType = $segments[1] ?? null;
        $resourceParam = $segments[2] ?? null;
        
        return match($resourceType) {
            'schema' => $this->getGraphQLSchema($schemaHandle),
            'sections' => $resourceParam 
                ? $this->getSectionDetails($resourceParam, $schemaHandle)
                : $this->listSections($schemaHandle),
            'entries' => $this->listSectionEntries($resourceParam, $schemaHandle),
            'volumes' => $this->listVolumes($schemaHandle),
            default => throw new \Exception("Unknown resource type: {$resourceType}"),
        };
    }
    
    /**
     * Get GraphQL schema SDL
     */
    private function getGraphQLSchema(string $schemaHandle): array
    {
        $token = $this->getAccessToken($schemaHandle);
        $schema = Craft::$app->getGql()->getSchemaByAccessToken($token);
        
        if (!$schema) {
            throw new \Exception("GraphQL schema not found for handle: {$schemaHandle}");
        }
        
        $sdl = Craft::$app->getGql()->getSchemaDef($schema);
        
        return [
            'contents' => [
                [
                    'uri' => "craft://{$schemaHandle}/schema",
                    'mimeType' => 'text/plain',
                    'text' => $sdl,
                ]
            ]
        ];
    }
    
    /**
     * List all sections
     */
    private function listSections(string $schemaHandle): array
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $data = array_map(fn($s) => [
            'handle' => $s->handle,
            'name' => $s->name,
            'type' => $s->type,
            'uri' => "craft://{$schemaHandle}/sections/{$s->handle}",
            'entriesUri' => "craft://{$schemaHandle}/entries/{$s->handle}",
        ], $sections);
        
        return [
            'contents' => [
                [
                    'uri' => "craft://{$schemaHandle}/sections",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]
            ]
        ];
    }
    
    /**
     * Get section details with field layout
     */
    private function getSectionDetails(string $handle, string $schemaHandle): array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($handle);
        
        if (!$section) {
            throw new \Exception("Section not found: {$handle}");
        }
        
        $entryTypes = $section->getEntryTypes();
        if (empty($entryTypes)) {
            throw new \Exception("No entry types found for section: {$handle}");
        }
        
        $entryType = $entryTypes[0];
        $fieldLayout = $entryType->getFieldLayout();
        
        $data = [
            'handle' => $section->handle,
            'name' => $section->name,
            'type' => $section->type,
            'entryTypes' => array_map(fn($et) => [
                'handle' => $et->handle,
                'name' => $et->name,
            ], $entryTypes),
            'fields' => array_map(fn($f) => [
                'handle' => $f->handle,
                'name' => $f->name,
                'type' => (new \ReflectionClass($f))->getShortName(),
                'required' => $f->required ?? false,
            ], $fieldLayout->getCustomFields()),
        ];
        
        return [
            'contents' => [
                [
                    'uri' => "craft://{$schemaHandle}/sections/{$handle}",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]
            ]
        ];
    }
    
    /**
     * List recent entries from a section
     */
    private function listSectionEntries(?string $section, string $schemaHandle): array
    {
        if (!$section) {
            throw new \Exception('Section parameter required in URI');
        }
        
        $entries = Entry::find()
            ->section($section)
            ->limit(50)
            ->orderBy('dateCreated DESC')
            ->all();
        
        $data = array_map(fn($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'status' => $e->status,
            'section' => $e->section->handle,
            'dateCreated' => $e->dateCreated?->format('Y-m-d H:i:s'),
            'dateUpdated' => $e->dateUpdated?->format('Y-m-d H:i:s'),
        ], $entries);
        
        return [
            'contents' => [
                [
                    'uri' => "craft://{$schemaHandle}/entries/{$section}",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]
            ]
        ];
    }
    
    /**
     * List all asset volumes
     */
    private function listVolumes(string $schemaHandle): array
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        
        $data = array_map(fn($v) => [
            'handle' => $v->handle,
            'name' => $v->name,
            'hasUrls' => $v->getFs()->hasUrls,
        ], $volumes);
        
        return [
            'contents' => [
                [
                    'uri' => "craft://{$schemaHandle}/volumes",
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]
            ]
        ];
    }
    
    /**
     * Get GraphQL access token for schema
     */
    private function getAccessToken(string $schemaHandle): string
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
        $token = $config['schemas'][$schemaHandle] ?? null;
        
        if (!$token) {
            throw new \Exception("No GraphQL token found for schema: {$schemaHandle}");
        }
        
        return $token;
    }
}
