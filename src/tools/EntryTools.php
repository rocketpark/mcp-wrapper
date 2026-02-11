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
     * Get office contact information (phone, address, etc.) in one call
     */
    #[Tool(
        name: 'craft_get_office_contact_info',
        description: 'Get complete contact information for an office location including phone number, address, and contact form URL. This does the nested lookups automatically.',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Office location slug (e.g., "roseville", "syracuse", "oakland")',
                ],
            ],
            'required' => ['slug'],
        ],
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'contactFormUrl' => ['type' => 'string'],
                    ],
                ],
            ],
            'required' => ['success'],
        ],
        dangerous: false,
        costHint: 'low',
        confidentialityHint: 'high',
    )]
    public function getOfficeContactInfo(string $slug): array
    {
        return SafeExecution::run(function() use ($slug) {
            // Step 1: Get the office entry
            $office = Entry::find()
                ->section('officeLocations')
                ->slug($slug)
                ->one();
            
            if (!$office) {
                return Response::notFound("Office location '{$slug}' not found");
            }

            // Get site-specific settings from config
            $config = Craft::$app->getConfig()->getConfigFromFile('mcpwrapper');
            $siteSettings = $config['siteSettings'] ?? [];
            $baseUrl = $siteSettings['baseUrl'] ?? Craft::$app->sites->primarySite->baseUrl;
            $contactFormPath = $siteSettings['officeContactFormPath'] ?? '/contact/office-locations/form';
            
            // Ensure baseUrl doesn't have trailing slash
            $baseUrl = rtrim($baseUrl, '/');
            
            $contactInfo = [
                'slug' => $slug,
                'title' => $office->title,
                'uri' => $office->uri,
                'phone' => null,
                'address' => null,
                'addressLine1' => null,
                'addressLine2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
                'country' => null,
                'latitude' => null,
                'longitude' => null,
                'googleMapsUrl' => null,
                // Use configured contact form path with slug
                'contactFormUrl' => "{$baseUrl}{$contactFormPath}/{$slug}",
                // Prefer Craft's native URL, fall back to constructed URL
                'officeDetailsUrl' => $office->url ?? "{$baseUrl}/{$office->uri}",
                'officeSummary' => $office->officeSummary ?? null,
            ];

            // Step 2: Get address details (if available)
            $addressEntries = $office->address->all() ?? [];
            if (count($addressEntries) > 0) {
                $addressEntry = $addressEntries[0] ?? null;
                
                if ($addressEntry) {
                    $contactInfo['addressLine1'] = $addressEntry->addressLine1 ?? null;
                    $contactInfo['addressLine2'] = $addressEntry->addressLine2 ?? null;
                    $contactInfo['city'] = $addressEntry->city ?? null;
                    $contactInfo['state'] = $addressEntry->state ?? null;
                    $contactInfo['zip'] = $addressEntry->zip ?? null;
                    $contactInfo['country'] = $addressEntry->country ?? null;
                    $contactInfo['latitude'] = $addressEntry->latitude ?? null;
                    $contactInfo['longitude'] = $addressEntry->longitude ?? null;

                    // Build formatted address
                    $addressParts = array_filter([
                        $addressEntry->addressLine1 ?? null,
                        $addressEntry->addressLine2 ?? null,
                        trim(($addressEntry->city ?? '') . ', ' . ($addressEntry->state ?? '') . ' ' . ($addressEntry->zip ?? '')),
                        $addressEntry->country ?? null,
                    ]);
                    $contactInfo['address'] = implode("\n", $addressParts);

                    // Build Google Maps URL from lat/long
                    if ($addressEntry->latitude && $addressEntry->longitude) {
                        $contactInfo['googleMapsUrl'] = "https://www.google.com/maps?q={$addressEntry->latitude},{$addressEntry->longitude}";
                    }
                }
            }

            // Step 3: Get phone number from contactLinks (if available)
            $contactLinksEntries = $office->contactLinks->all() ?? [];
            if (count($contactLinksEntries) > 0) {
                $contactLinksEntry = $contactLinksEntries[0] ?? null;

                if ($contactLinksEntry && isset($contactLinksEntry->contactDetails)) {
                    // Extract phone from HTML using DOMDocument for safe parsing
                    $html = $contactLinksEntry->contactDetails;
                    $phone = $this->extractPhoneFromHtml($html);
                    if ($phone !== null) {
                        $contactInfo['phone'] = $phone;
                    }
                }
            }

            return Response::found('contactInfo', $contactInfo);
        });
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

    /**
     * Safely extract phone number from HTML using DOMDocument
     *
     * @param string $html The HTML containing the phone link
     * @return string|null The extracted and validated phone number, or null if not found/invalid
     */
    private function extractPhoneFromHtml(string $html): ?string
    {
        if (empty($html)) {
            return null;
        }

        // Use DOMDocument for safe HTML parsing
        $doc = new \DOMDocument();
        // Suppress warnings for malformed HTML, wrap in UTF-8 encoding declaration
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $links = $doc->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && str_starts_with($href, 'tel:')) {
                $phone = trim($link->textContent);
                // Validate phone format: only allow digits, spaces, dashes, parens, plus sign
                if (preg_match('/^\+?[\d\s\-()]+$/', $phone) && strlen($phone) >= 7) {
                    return $phone;
                }
            }
        }

        return null;
    }
}
