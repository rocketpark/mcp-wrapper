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
     * Query entries with full Craft parameter support
     */
    #[Tool(
        name: 'craft_search_entries',
        description: 'Query entries using any Craft entry query parameters including custom fields. Supports all parameters from https://craftcms.com/docs/5.x/reference/element-types/entries.html#parameters',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => ['string', 'array'],
                    'description' => 'Section handle(s) to query',
                ],
                'type' => [
                    'type' => ['string', 'array'],
                    'description' => 'Entry type handle(s)',
                ],
                'slug' => [
                    'type' => ['string', 'array'],
                    'description' => 'Entry slug(s)',
                ],
                'status' => [
                    'type' => ['string', 'array'],
                    'description' => 'Entry status (live, pending, expired, disabled, or any)',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Full-text search term (only searches fields marked as searchable)',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Filter by exact or partial title match',
                ],
                'id' => [
                    'type' => ['integer', 'array'],
                    'description' => 'Entry ID(s)',
                ],
                'authorId' => [
                    'type' => ['integer', 'array'],
                    'description' => 'Filter by author ID(s)',
                ],
                'postDate' => [
                    'type' => 'string',
                    'description' => 'Filter by post date (e.g., ">= 2024-01-01", "< now")',
                ],
                'before' => [
                    'type' => 'string',
                    'description' => 'Entries posted before this date',
                ],
                'after' => [
                    'type' => 'string',
                    'description' => 'Entries posted after this date',
                ],
                'expiryDate' => [
                    'type' => 'string',
                    'description' => 'Filter by expiry date',
                ],
                'site' => [
                    'type' => ['string', 'array'],
                    'description' => 'Site handle(s)',
                ],
                'siteId' => [
                    'type' => ['integer', 'array'],
                    'description' => 'Site ID(s)',
                ],
                'unique' => [
                    'type' => 'boolean',
                    'description' => 'Whether to return unique entries across sites',
                ],
                'orderBy' => [
                    'type' => 'string',
                    'description' => 'Order results (e.g., "title", "postDate desc", "RAND()")',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum entries to return (default: 20, use null for no limit)',
                    'default' => 20,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of entries to skip (for pagination)',
                ],
                'relatedTo' => [
                    'type' => ['integer', 'array', 'object'],
                    'description' => 'Filter by related elements (entry ID, array of IDs, or criteria object)',
                ],
                'ancestorOf' => [
                    'type' => 'integer',
                    'description' => 'Entries that are ancestors of this entry ID',
                ],
                'descendantOf' => [
                    'type' => 'integer',
                    'description' => 'Entries that are descendants of this entry ID',
                ],
                'level' => [
                    'type' => 'integer',
                    'description' => 'Structure level',
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Custom field filters as key-value pairs (e.g., {"myTextField": "value", "myNumberField": 42})',
                    'additionalProperties' => true,
                ],
            ],
            'additionalProperties' => false,
        ],
        dangerous: false,
    )]
    public function searchEntries(array $params = []): array
    {
        return SafeExecution::run(function() use ($params) {
            $query = Entry::find();
            
            // Set default limit if not specified
            if (!isset($params['limit'])) {
                $query->limit(20);
            }
            
            // Extract custom fields (they need special handling)
            $customFields = $params['fields'] ?? [];
            unset($params['fields']);
            
            // Apply all standard Craft parameters
            foreach ($params as $param => $value) {
                if ($value !== null) {
                    $query->{$param}($value);
                }
            }
            
            // Apply custom field filters
            foreach ($customFields as $fieldHandle => $fieldValue) {
                $query->{$fieldHandle}($fieldValue);
            }
            
            $entries = $query->all();
            
            // Build metadata about the query
            $metadata = array_filter([
                'totalResults' => count($entries),
                'limit' => $params['limit'] ?? 20,
                'offset' => $params['offset'] ?? 0,
                'section' => $params['section'] ?? null,
                'type' => $params['type'] ?? null,
                'orderBy' => $params['orderBy'] ?? null,
            ]);
            
            return Response::list('entries', array_map(
                fn($entry) => $this->serializeEntry($entry),
                $entries
            ), $metadata);
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
