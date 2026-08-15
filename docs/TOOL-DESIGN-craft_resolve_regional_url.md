# MCP Tool Design: `craft_resolve_regional_url`

**Status:** DESIGN — not yet implemented
**Source:** Research agent recommendation (May 11, 2026)
**Priority:** High — removes the entire 404-URL class of bugs

## Problem solved

Current bot has a static URL prefix table baked into instructions:
```
| Region        | URL prefix     | Example                              |
| europe        | /europe/       | /europe/services/fire-engineering-systems-design  ← 404
```

Real jensenhughes.com has **different service slugs per region**:
- Global: `fire-engineering-systems-design`
- Europe: `fire-engineering-consultancy`
- Pacific: `fire-engineering`
- Asia: `fire-engineering-systems-design`

Bot guesses based on global slug → emits 404 URLs.

## Solution

Add MCP tool that takes intent (region + service keyword) and returns the LIVE canonical URL plus availability + capability metadata. Bot calls this before emitting any service URL. Single source of truth = Craft CMS.

## Signature

```php
#[Tool(
    name: 'craft_resolve_regional_url',
    description: 'Resolve canonical regional URL + availability + capabilities for a service intent. Use BEFORE emitting any /services/* or /industries/* URL.',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'region' => [
                'type' => 'string',
                'enum' => ['global', 'americas', 'europe', 'pacific', 'asia', 'middle_east'],
                'description' => "User's region (from user.data.region)",
            ],
            'contentType' => [
                'type' => 'string',
                'enum' => ['services', 'industries'],
                'description' => 'Section to search',
            ],
            'intent' => [
                'type' => 'string',
                'description' => 'Service keyword/intent (e.g., "fire engineering", "forensic investigation", "accessibility")',
            ],
        ],
        'required' => ['region', 'contentType', 'intent'],
    ],
    outputSchema: [
        'type' => 'object',
        'properties' => [
            'available' => [
                'type' => 'boolean',
                'description' => 'Whether the service exists in this region',
            ],
            'url' => [
                'type' => 'string',
                'nullable' => true,
                'description' => 'Canonical URL if available, else regional services landing',
            ],
            'fallbackUrl' => [
                'type' => 'string',
                'description' => 'Region services landing page (always valid)',
            ],
            'matchedSlug' => [
                'type' => 'string',
                'nullable' => true,
                'description' => 'Slug that matched the intent',
            ],
            'matchedTitle' => [
                'type' => 'string',
                'nullable' => true,
            ],
            'description' => [
                'type' => 'string',
                'nullable' => true,
            ],
        ],
        'required' => ['available', 'fallbackUrl'],
    ],
    dangerous: false,
    costHint: 'low',
)]
```

## Implementation sketch

```php
// in src/tools/EntryTools.php

private const REGION_SITE_HANDLE = [
    'global'      => null,  // Use primary site
    'americas'    => 'jensenHughesDigital',
    'europe'      => 'jensenHughesEurope',
    'pacific'     => 'jensenHughesPacific',
    'asia'        => 'jensenHughesAsia',
    'middle_east' => 'jensenHughesMiddleEast',
];

private const REGION_URL_PREFIX = [
    'global'      => '',
    'americas'    => '',
    'europe'      => '/europe',
    'pacific'     => '/pacific',
    'asia'        => '/asia',
    'middle_east' => '/middle-east',
];

public function resolveRegionalUrl(string $region, string $contentType, string $intent): array
{
    return SafeExecution::run(function() use ($region, $contentType, $intent) {
        $siteHandle = self::REGION_SITE_HANDLE[$region] ?? null;
        $prefix     = self::REGION_URL_PREFIX[$region] ?? '';
        $fallbackUrl = 'https://www.jensenhughes.com' . $prefix . '/' . $contentType;
        
        // Find site
        $site = $siteHandle 
            ? Craft::$app->sites->getSiteByHandle($siteHandle)
            : Craft::$app->sites->getPrimarySite();
        
        if (!$site) {
            return Response::ok([
                'available'   => false,
                'fallbackUrl' => $fallbackUrl,
            ]);
        }
        
        // Search by title/keyword in the regional services section
        $entry = Entry::find()
            ->section($contentType)
            ->siteId($site->id)
            ->search($intent)
            ->orderBy(['score' => SORT_DESC])
            ->one();
        
        if (!$entry) {
            return Response::ok([
                'available'   => false,
                'fallbackUrl' => $fallbackUrl,
                'matchedSlug' => null,
            ]);
        }
        
        return Response::ok([
            'available'    => true,
            'url'          => $entry->url,
            'fallbackUrl'  => $fallbackUrl,
            'matchedSlug'  => $entry->slug,
            'matchedTitle' => $entry->title,
            'description'  => $entry->shortDescription ?? null,
        ]);
    });
}
```

## Bot instruction simplification (after tool exists)

Replace these prompt blocks (~1,200 chars) with one rule:

> Before emitting any URL to /services/* or /industries/*, call `craft_resolve_regional_url` with the user's region + service intent. If `available: true`, use `url`. If `false`, link `fallbackUrl` only — do NOT enumerate capabilities for unavailable services.

Removed blocks:
- Regional URL prefix table (4 rows of examples)
- Regional Service Restrictions matrix (Europe NOT-available, Asia NOT-available, etc.)
- Capability enumeration bullet I added today (becomes redundant — tool returns capabilities for available services only)

## Test plan

1. Register tool in `McpWrapper.php` → `registerToolClasses()`
2. Local Herd test: `php craft mcp/test resolveRegionalUrl '{"region":"europe","contentType":"services","intent":"fire engineering"}'`
   - Expect: `{available: true, url: '.../europe/services/fire-engineering-consultancy', ...}`
3. Test ME forensics: `{"region":"middle_east","contentType":"services","intent":"forensic"}`
   - Expect: `{available: false, fallbackUrl: '.../middle-east/services'}` (ME has no forensics service slug)
4. Deploy to Forge: `composer install --no-dev && curl .../mcp/manifest/MCPSchema?force=1`
5. Verify tool count: should be 18 (was 17)
6. In Botpress: add tool to AutonomousNode's Tools list

## Migration phasing

This tool replaces three brittle parts of the current prompt. Stage rollout:
1. Add tool. Keep current prompt unchanged. Verify tool returns correct data via emulator test.
2. Add new bot instruction: "If unsure about regional URL, call craft_resolve_regional_url first" — see if bot adopts.
3. Once verified, remove static URL prefix table from instructions.
4. Then remove service availability matrix.
5. Then remove capability enumeration bullet.

Each phase is reversible. Don't bundle changes — observe each effect.

## Edge cases

- **Search returns multiple matches** (e.g., Europe has `civil-structural-failure` AND `materials-failure-analysis` for "failure" intent): tool returns highest-scoring single match. Bot may need to call multiple times for disambiguation.
- **Craft search returns nothing meaningful**: bot falls back to landing URL. Acceptable degradation.
- **Region unknown**: default to `global` (no prefix).
- **Site exists but no entries in section**: return `available: false, fallbackUrl: ...`.

## References

- Research agent report (Task #22 transcript)
- Craft CMS Entry query docs: https://craftcms.com/docs/5.x/reference/element-types/entries.html
- Current `EntryTools.php` pattern (`getEntryBySlug`, `craft_search_entries`)
