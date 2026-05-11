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
     * Fields that should NEVER be exposed via API for privacy protection
     * These fields may contain personal email addresses or sensitive data
     */
    private const BLOCKED_FIELDS = [
        'formSubmissionNotificationEmail',
        'contactEmails',
        'privateEmail',
        'personalEmail',
        'internalEmail',
        'notificationEmail',
    ];

    /**
     * Valid parameters for Entry::find() query
     * Only these parameters will be applied to prevent method call errors
     */
    private const VALID_QUERY_PARAMS = [
        'section', 'type', 'slug', 'status', 'search', 'title', 'id',
        'authorId', 'postDate', 'before', 'after', 'expiryDate',
        'site', 'siteId', 'unique', 'orderBy', 'limit', 'offset',
        'relatedTo', 'ancestorOf', 'descendantOf', 'level',
    ];
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
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'entry' => ['type' => 'object'],
                    ],
                ],
                'message' => ['type' => 'string'],
            ],
            'required' => ['success'],
        ],
        dangerous: false,
        costHint: 'low',
        confidentialityHint: 'medium',
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
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'entries' => ['type' => 'array'],
                        'count' => ['type' => 'integer'],
                    ],
                ],
            ],
            'required' => ['success'],
        ],
        costHint: 'medium',
        confidentialityHint: 'low',
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
                'query' => [
                    'type' => 'string',
                    'description' => 'Alias for "search" - full-text search term',
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

            // Extract custom fields (they need special handling)
            $customFields = $params['fields'] ?? [];
            unset($params['fields']);

            // Handle 'query' as an alias for 'search' (common user mistake)
            if (isset($params['query']) && !isset($params['search'])) {
                $params['search'] = $params['query'];
                unset($params['query']);
            }

            // Set default limit if not specified
            if (!isset($params['limit'])) {
                $params['limit'] = 20;
            }

            // Validate and apply only known Craft query parameters
            // This prevents errors from invalid method calls like $query->query()
            $invalidParams = [];
            foreach ($params as $param => $value) {
                if ($value !== null) {
                    if (in_array($param, self::VALID_QUERY_PARAMS, true)) {
                        $query->{$param}($value);
                    } else {
                        $invalidParams[] = $param;
                    }
                }
            }

            // Warn about invalid parameters but continue execution
            if (!empty($invalidParams)) {
                Craft::warning(
                    'craft_search_entries received invalid parameters: ' . implode(', ', $invalidParams) .
                    '. Valid parameters are: ' . implode(', ', self::VALID_QUERY_PARAMS),
                    'mcp-wrapper'
                );
            }
            
            // Apply custom field filters with validation
            if (!empty($customFields)) {
                // Get valid field handles for the section (if specified)
                $validFieldHandles = $this->getValidFieldHandles($params['section'] ?? null);

                foreach ($customFields as $fieldHandle => $fieldValue) {
                    // Validate field handle format (alphanumeric + underscore, doesn't start with number)
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldHandle)) {
                        Craft::warning("Invalid custom field handle format: {$fieldHandle}", 'mcp-wrapper');
                        continue;
                    }

                    // If we have a valid fields list (section was specified), validate against it
                    if (!empty($validFieldHandles) && !in_array($fieldHandle, $validFieldHandles, true)) {
                        Craft::warning("Unknown custom field: {$fieldHandle}", 'mcp-wrapper');
                        continue;
                    }

                    // Verify the method exists on the query object (extra safety)
                    if (method_exists($query, $fieldHandle) || property_exists($query, $fieldHandle)) {
                        $query->{$fieldHandle}($fieldValue);
                    } else {
                        Craft::warning("Field method not found on query: {$fieldHandle}", 'mcp-wrapper');
                    }
                }
            }
            
            // Get total count before limiting
            $totalCount = $query->count();
            
            $entries = $query->all();
            
            // Build metadata about the query
            $metadata = array_filter([
                'totalResults' => $totalCount,
                'limit' => $params['limit'] ?? 20,
                'offset' => $params['offset'] ?? 0,
                'section' => $params['section'] ?? null,
                'type' => $params['type'] ?? null,
                'orderBy' => $params['orderBy'] ?? null,
            ]);
            
            return Response::list('entries', array_values(array_filter(array_map(
                fn($entry) => $this->serializeEntry($entry),
                $entries
            ))), $metadata);
        });
    }

    /**
     * Map region handles passed by the webchat (user.data.region) to the
     * canonical Craft site handle for that regional site.
     */
    private const REGION_SITE_HANDLE = [
        'global'      => 'default',
        'americas'    => 'default',
        'europe'      => 'jensenHughesEurope',
        'pacific'     => 'jensenHughesPacific',
        'asia'        => 'jensenHughesAsia',
        'middle_east' => 'jensenHughesMiddleEast',
    ];

    /**
     * URL prefix on jensenhughes.com per region. Used to build the fallback
     * landing URL when no specific entry matches.
     */
    private const REGION_URL_PREFIX = [
        'global'      => '',
        'americas'    => '',
        'europe'      => '/europe',
        'pacific'     => '/pacific',
        'asia'        => '/asia',
        'middle_east' => '/middle-east',
    ];

    /**
     * Resolve canonical regional URL + availability for a service or industry intent
     *
     * Used by Botpress AI Helper to verify a URL exists in the user's region before
     * emitting it in a response. Replaces the previous static URL prefix table baked
     * into the bot's instructions (which produced 404s for region-specific slugs).
     */
    #[Tool(
        name: 'craft_resolve_regional_url',
        description: 'Resolve canonical regional URL + availability for a service or industry intent. Call BEFORE emitting any /services/* or /industries/* URL in a bot response. Args use wrapper-compatible names: search=intent, title=region, slug=contentType. Returns {available, url, fallbackUrl, matchedSlug, matchedTitle} so the bot can avoid 404 deep links and skip enumerating capabilities for services that do not exist in the region.',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Service keyword/intent (e.g., "fire engineering", "forensic investigation", "accessibility"). Aliased as intent.',
                ],
                'title' => [
                    'type' => 'string',
                    'enum' => ['global', 'americas', 'europe', 'pacific', 'asia', 'middle_east'],
                    'description' => "User's region code from user.data.region. Aliased as region. Wrapper-compatible field name.",
                ],
                'slug' => [
                    'type' => 'string',
                    'enum' => ['services', 'industries'],
                    'description' => 'Section to search. Aliased as contentType. Wrapper-compatible field name.',
                ],
            ],
            'required' => ['search', 'title', 'slug'],
        ],
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'available' => ['type' => 'boolean', 'description' => 'Whether a matching entry was found in this region'],
                'url' => ['type' => ['string', 'null'], 'description' => 'Canonical URL if available'],
                'fallbackUrl' => ['type' => 'string', 'description' => 'Region services/industries landing page (always valid)'],
                'matchedSlug' => ['type' => ['string', 'null'], 'description' => 'Slug that matched the intent'],
                'matchedTitle' => ['type' => ['string', 'null'], 'description' => 'Title of the matched entry'],
                'region' => ['type' => 'string', 'description' => 'Echo of region for traceability'],
            ],
            'required' => ['available', 'fallbackUrl', 'region'],
        ],
        dangerous: false,
        costHint: 'low',
        confidentialityHint: 'low',
    )]
    public function resolveRegionalUrl(array $args): array
    {
        return SafeExecution::run(function() use ($args) {
            // Accept both wrapper-compatible names (search/title/slug) and
            // semantic names (intent/region/contentType) for direct MCP callers
            // (sync scripts, curl, future integrations). Wrapper-compat names win
            // if both supplied because Botpress queryContent only emits those.
            $intent      = $args['search']      ?? $args['intent']      ?? '';
            $region      = $args['title']       ?? $args['region']      ?? '';
            $contentType = $args['slug']        ?? $args['contentType'] ?? '';

            if ($intent === '' || $region === '' || $contentType === '') {
                return Response::error("Missing required args. Need search (intent), title (region), and slug (contentType).", 400);
            }

            $region = strtolower(trim($region));
            $contentType = strtolower(trim($contentType));

            if (!isset(self::REGION_SITE_HANDLE[$region])) {
                return Response::error("Unknown region '{$region}'. Expected one of: " . implode(', ', array_keys(self::REGION_SITE_HANDLE)), 400);
            }
            if (!in_array($contentType, ['services', 'industries'], true)) {
                return Response::error("Unknown contentType '{$contentType}'. Expected 'services' or 'industries'.", 400);
            }

            $siteHandle  = self::REGION_SITE_HANDLE[$region];
            $prefix      = self::REGION_URL_PREFIX[$region];
            $fallbackUrl = 'https://www.jensenhughes.com' . $prefix . '/' . $contentType;

            $site = Craft::$app->sites->getSiteByHandle($siteHandle);
            if (!$site) {
                Craft::warning("resolveRegionalUrl: site handle '{$siteHandle}' not found for region '{$region}'", 'mcp-wrapper');
                return Response::success([
                    'available'    => false,
                    'url'          => null,
                    'fallbackUrl'  => $fallbackUrl,
                    'matchedSlug'  => null,
                    'matchedTitle' => null,
                    'region'       => $region,
                ]);
            }

            $entry = Entry::find()
                ->section($contentType)
                ->siteId($site->id)
                ->search($intent)
                ->status(['live'])
                ->orderBy(['score' => SORT_DESC])
                ->one();

            if (!$entry) {
                return Response::success([
                    'available'    => false,
                    'url'          => null,
                    'fallbackUrl'  => $fallbackUrl,
                    'matchedSlug'  => null,
                    'matchedTitle' => null,
                    'region'       => $region,
                ]);
            }

            return Response::success([
                'available'    => true,
                'url'          => $entry->url,
                'fallbackUrl'  => $fallbackUrl,
                'matchedSlug'  => $entry->slug,
                'matchedTitle' => $entry->title,
                'region'       => $region,
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
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'entry' => ['type' => 'object'],
                    ],
                ],
                'message' => ['type' => 'string'],
            ],
            'required' => ['success'],
        ],
        dangerous: false,
        costHint: 'low',
        confidentialityHint: 'medium',
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
    private function serializeEntry(?Entry $entry): ?array
    {
        if (!$entry) {
            return null;
        }
        
        $data = [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'uri' => $entry->uri,
            'url' => $entry->url,
            'section' => $entry->section ? [
                'id' => $entry->section->id,
                'handle' => $entry->section->handle,
                'name' => $entry->section->name,
            ] : null,
            'type' => $entry->type ? [
                'id' => $entry->type->id,
                'handle' => $entry->type->handle,
                'name' => $entry->type->name,
            ] : null,
            'status' => $entry->status,
            'enabled' => $entry->enabled,
            'postDate' => $entry->postDate?->format('Y-m-d H:i:s'),
            'expiryDate' => $entry->expiryDate?->format('Y-m-d H:i:s'),
            'dateCreated' => $entry->dateCreated->format('Y-m-d H:i:s'),
            'dateUpdated' => $entry->dateUpdated->format('Y-m-d H:i:s'),
        ];

        // Add custom fields (excluding blocked fields for privacy)
        $fieldLayout = $entry->getFieldLayout();
        if ($fieldLayout) {
            $customFields = [];
            foreach ($fieldLayout->getCustomFields() as $field) {
                $handle = $field->handle;
                // Skip blocked fields to protect privacy (e.g., personal emails)
                if (in_array($handle, self::BLOCKED_FIELDS, true)) {
                    continue;
                }
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
        
        // Handle scalar types (bool, int, float, string)
        if (is_scalar($value)) {
            return $value;
        }

        // Handle arrays
        if (is_array($value)) {
            return array_map(fn($v) => $this->serializeFieldValue($v), $value);
        }

        // Handle element queries (relations)
        if (is_object($value) && method_exists($value, 'all')) {
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

    /**
     * Get valid field handles for a section
     *
     * @param string|null $sectionHandle The section handle, or null for all fields
     * @return array Array of valid field handles
     */
    private function getValidFieldHandles(?string $sectionHandle): array
    {
        $validFields = [];

        if ($sectionHandle !== null) {
            // Get fields from the specific section's entry types
            $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
            if ($section) {
                foreach ($section->getEntryTypes() as $entryType) {
                    $fieldLayout = $entryType->getFieldLayout();
                    if ($fieldLayout) {
                        foreach ($fieldLayout->getCustomFields() as $field) {
                            $validFields[] = $field->handle;
                        }
                    }
                }
            }
        }

        // Return unique field handles
        return array_unique($validFields);
    }
}
