<?php

namespace rocketpark\mcpwrapper\tools;

use Craft;
use craft\elements\Entry;
use rocketpark\mcpwrapper\attributes\Tool;
use rocketpark\mcpwrapper\support\Response;
use rocketpark\mcpwrapper\support\SafeExecution;

/**
 * Entry Tools
 * 
 * Direct Craft API access for entries, bypassing GraphQL schema permissions.
 * Useful for administrative tasks and debugging.
 */
class EntryTools
{
    /**
     * Get entry by ID with all fields
     */
    #[Tool(
        name: 'craft_get_entry_by_id',
        description: 'Get full entry data by ID (bypasses GraphQL permissions for admin access)',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Entry ID',
                ],
                'siteId' => [
                    'type' => 'integer',
                    'description' => 'Site ID (optional, defaults to primary site)',
                ],
            ],
            'required' => ['id'],
        ],
        dangerous: false,
    )]
    public function getEntryById(int $id, ?int $siteId = null): array
    {
        return SafeExecution::run(function() use ($id, $siteId) {
            $entry = Craft::$app->entries->getEntryById($id, $siteId);
            
            if (!$entry) {
                return Response::notFound("Entry with ID {$id} not found");
            }
            
            return Response::found('entry', $this->serializeEntry($entry));
        });
    }

    /**
     * Search entries across all sections
     */
    #[Tool(
        name: 'craft_search_entries',
        description: 'Search entries by title/slug across all sections with advanced filtering',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term for title/slug',
                ],
                'section' => [
                    'type' => 'string',
                    'description' => 'Limit to specific section handle (optional)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['live', 'pending', 'expired', 'disabled'],
                    'description' => 'Entry status filter (optional)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum entries to return (default: 20)',
                    'default' => 20,
                ],
            ],
            'required' => ['search'],
        ],
        dangerous: false,
    )]
    public function searchEntries(
        string $search,
        ?string $section = null,
        ?string $status = null,
        int $limit = 20
    ): array {
        return SafeExecution::run(function() use ($search, $section, $status, $limit) {
            $query = Entry::find()
                ->search($search)
                ->limit($limit);
            
            if ($section) {
                $query->section($section);
            }
            
            if ($status) {
                $query->status($status);
            }
            
            $entries = $query->all();
            
            return Response::list('entries', array_map(
                fn($entry) => $this->serializeEntry($entry),
                $entries
            ), [
                'searchTerm' => $search,
                'section' => $section,
                'status' => $status,
            ]);
        });
    }

    /**
     * Get entry by slug
     */
    #[Tool(
        name: 'craft_get_entry_by_slug',
        description: 'Get entry by section and slug (bypasses GraphQL permissions)',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'description' => 'Section handle',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Entry slug',
                ],
                'siteId' => [
                    'type' => 'integer',
                    'description' => 'Site ID (optional)',
                ],
            ],
            'required' => ['section', 'slug'],
        ],
        dangerous: false,
    )]
    public function getEntryBySlug(string $section, string $slug, ?int $siteId = null): array
    {
        return SafeExecution::run(function() use ($section, $slug, $siteId) {
            $query = Entry::find()
                ->section($section)
                ->slug($slug);
            
            if ($siteId) {
                $query->siteId($siteId);
            }
            
            $entry = $query->one();
            
            if (!$entry) {
                return Response::notFound("Entry not found in section '{$section}' with slug '{$slug}'");
            }
            
            return Response::found('entry', $this->serializeEntry($entry));
        });
    }

    /**
     * Serialize entry to array with all fields
     */
    private function serializeEntry(Entry $entry): array
    {
        $data = [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'uri' => $entry->uri,
            'url' => $entry->url,
            'section' => [
                'id' => $entry->section->id,
                'handle' => $entry->section->handle,
                'name' => $entry->section->name,
            ],
            'type' => [
                'id' => $entry->type->id,
                'handle' => $entry->type->handle,
                'name' => $entry->type->name,
            ],
            'status' => $entry->status,
            'enabled' => $entry->enabled,
            'postDate' => $entry->postDate?->format('Y-m-d H:i:s'),
            'expiryDate' => $entry->expiryDate?->format('Y-m-d H:i:s'),
            'dateCreated' => $entry->dateCreated->format('Y-m-d H:i:s'),
            'dateUpdated' => $entry->dateUpdated->format('Y-m-d H:i:s'),
        ];

        // Add custom fields
        $fieldLayout = $entry->getFieldLayout();
        if ($fieldLayout) {
            $customFields = [];
            foreach ($fieldLayout->getCustomFields() as $field) {
                $handle = $field->handle;
                $value = $entry->$handle;
                $customFields[$handle] = $this->serializeFieldValue($value);
            }
            $data['customFields'] = $customFields;
        }

        return $data;
    }

    /**
     * Serialize field value to JSON-compatible format
     */
    private function serializeFieldValue($value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle arrays
        if (is_array($value)) {
            return array_map(fn($v) => $this->serializeFieldValue($v), $value);
        }

        // Handle element queries (relations)
        if (method_exists($value, 'all')) {
            $elements = $value->all();
            return array_map(function($element) {
                return [
                    'id' => $element->id,
                    'title' => $element->title ?? $element->name ?? null,
                    'url' => $element->url ?? null,
                    'type' => get_class($element),
                ];
            }, $elements);
        }

        // Handle date/time
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        // Handle objects with __toString
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        // Handle other objects
        if (is_object($value)) {
            return get_class($value);
        }

        return $value;
    }
}
