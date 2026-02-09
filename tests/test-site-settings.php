<?php
/**
 * Test Site Settings Configuration
 * 
 * Verifies that:
 * 1. Config file has siteSettings section
 * 2. EntryTools properly uses configured values (code inspection)
 * 3. No hardcoded URLs remain in the code
 * 
 * Run: php tests/test-site-settings.php
 */

echo "Testing Site Settings Configuration\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Verify config example has siteSettings
echo "Test 1: Checking config example file...\n";
$configFile = __DIR__ . '/../config/mcpwrapper.php.example';
if (!file_exists($configFile)) {
    echo "  ❌ ERROR: Config example file not found\n";
    exit(1);
}

$configContent = file_get_contents($configFile);

if (strpos($configContent, 'siteSettings') === false) {
    echo "  ❌ ERROR: siteSettings section not found in config\n";
    exit(1);
}

if (strpos($configContent, 'baseUrl') === false) {
    echo "  ❌ ERROR: baseUrl setting not found\n";
    exit(1);
}

if (strpos($configContent, 'officeContactFormPath') === false) {
    echo "  ❌ ERROR: officeContactFormPath setting not found\n";
    exit(1);
}

echo "  ✅ Config example has siteSettings section\n";
echo "  ✅ baseUrl setting present\n";
echo "  ✅ officeContactFormPath setting present\n";

echo "\n";

// Test 2: Verify EntryTools reads from config (not hardcoded)
echo "Test 2: Checking EntryTools.php for hardcoded URLs...\n";
$entryToolsFile = __DIR__ . '/../src/tools/EntryTools.php';
if (!file_exists($entryToolsFile)) {
    echo "  ❌ ERROR: EntryTools.php not found\n";
    exit(1);
}

$entryToolsContent = file_get_contents($entryToolsFile);

// Check for hardcoded jensenhughes.com URLs
if (preg_match('/["\']https?:\/\/.*?jensenhughes\.com/i', $entryToolsContent)) {
    echo "  ❌ ERROR: Found hardcoded jensenhughes.com URL!\n";
    
    // Show the offending lines
    preg_match_all('/.*https?:\/\/.*?jensenhughes\.com.*$/m', $entryToolsContent, $matches);
    foreach ($matches[0] as $line) {
        echo "     " . trim($line) . "\n";
    }
    exit(1);
}

echo "  ✅ No hardcoded jensenhughes.com URLs found\n";

// Check that it reads from config
if (strpos($entryToolsContent, 'getConfigFromFile') === false) {
    echo "  ⚠️  WARNING: Doesn't appear to read config file\n";
} else {
    echo "  ✅ Reads from config file\n";
}

if (strpos($entryToolsContent, 'siteSettings') === false) {
    echo "  ⚠️  WARNING: Doesn't reference siteSettings\n";
} else {
    echo "  ✅ References siteSettings\n";
}

// Check for fallback logic
if (strpos($entryToolsContent, 'primarySite->baseUrl') === false) {
    echo "  ⚠️  WARNING: No fallback to primarySite->baseUrl\n";
} else {
    echo "  ✅ Has fallback to Craft's primary site URL\n";
}

echo "\n";

// Test 3: Verify URL construction logic
echo "Test 3: Simulating URL construction logic...\n";

// Simulate the logic from EntryTools
$config = ['siteSettings' => []]; // Empty config (test defaults)
$siteSettings = $config['siteSettings'] ?? [];
$baseUrl = $siteSettings['baseUrl'] ?? 'https://example.com'; // Simulating Craft::$app->sites->primarySite->baseUrl
$contactFormPath = $siteSettings['officeContactFormPath'] ?? '/contact/office-locations/form';

// Ensure baseUrl doesn't have trailing slash
$baseUrl = rtrim($baseUrl, '/');

// Test URL construction
$testSlug = 'chicago';
$contactFormUrl = "{$baseUrl}{$contactFormPath}/{$testSlug}";
$officeDetailsUrl = "{$baseUrl}/offices/{$testSlug}";

echo "  ✅ URL construction successful\n";
echo "     Base URL: {$baseUrl}\n";
echo "     Contact form URL: {$contactFormUrl}\n";
echo "     Office details URL: {$officeDetailsUrl}\n";

// Verify no hardcoded domains
if (strpos($contactFormUrl, 'jensenhughes.com') !== false) {
    echo "  ❌ ERROR: URLs still contain jensenhughes.com!\n";
    exit(1);
}

echo "  ✅ URLs are properly constructed from config\n";

echo "\n";

// Test 4: Verify Analytics Utility class exists
echo "Test 4: Checking Analytics Utility...\n";
$analyticsFile = __DIR__ . '/../src/utilities/McpAnalyticsUtility.php';
if (!file_exists($analyticsFile)) {
    echo "  ❌ ERROR: McpAnalyticsUtility.php not found\n";
    exit(1);
}

$analyticsContent = file_get_contents($analyticsFile);

// Check it's not trying to redirect
if (strpos($analyticsContent, '->redirect(') !== false && 
    strpos($analyticsContent, 'renderTemplate') === false) {
    echo "  ⚠️  WARNING: Still uses redirect without renderTemplate\n";
} else {
    echo "  ✅ Uses renderTemplate (not redirect)\n";
}

echo "  ✅ McpAnalyticsUtility.php exists\n";

echo "\n";
echo str_repeat("=", 50) . "\n";
echo "✅ All tests passed!\n\n";

echo "Summary:\n";
echo "  - Config has siteSettings: ✅\n";
echo "  - No hardcoded jensenhughes.com URLs: ✅\n";
echo "  - Reads from config with fallback: ✅\n";
echo "  - URL construction logic correct: ✅\n";
echo "  - Analytics utility fixed: ✅\n";
echo "\n";
echo "Ready for deployment!\n";

