<?php
/**
 * Test Jensen Hughes Production Config
 * 
 * Verifies the jensenhughes config file is properly configured
 * Run: php tests/test-jh-config.php
 */

echo "Testing Jensen Hughes Config\n";
echo str_repeat("=", 50) . "\n\n";

$configPath = '/Users/elizabethstein/Herd/jensenhughes/config/mcpwrapper.php';

if (!file_exists($configPath)) {
    echo "❌ ERROR: Config file not found at {$configPath}\n";
    exit(1);
}

echo "✅ Config file exists\n\n";

// Load the config
$config = require $configPath;

echo "Test 1: Basic structure...\n";
if (!is_array($config)) {
    echo "  ❌ ERROR: Config is not an array\n";
    exit(1);
}
echo "  ✅ Config is valid array\n";

// Test schemas
echo "\nTest 2: Schemas configuration...\n";
if (!isset($config['schemas'])) {
    echo "  ❌ ERROR: schemas section missing\n";
    exit(1);
}
echo "  ✅ schemas section present\n";
echo "     Configured schemas: " . implode(', ', array_keys($config['schemas'])) . "\n";

// Test security
echo "\nTest 3: Security configuration...\n";
if (!isset($config['security'])) {
    echo "  ⚠️  WARNING: security section missing\n";
} else {
    echo "  ✅ security section present\n";
    $dangerousTools = $config['security']['enableDangerousTools'] ?? true;
    echo "     Dangerous tools enabled: " . ($dangerousTools ? 'YES ⚠️' : 'NO ✅') . "\n";
    
    if ($dangerousTools) {
        echo "  ⚠️  WARNING: Dangerous tools are enabled (should be false in production)\n";
    }
}

// Test siteSettings
echo "\nTest 4: Site settings configuration...\n";
if (!isset($config['siteSettings'])) {
    echo "  ⚠️  WARNING: siteSettings section missing (will use defaults)\n";
    echo "     This is OK - will fall back to Craft's primary site URL\n";
} else {
    echo "  ✅ siteSettings section present\n";
    
    $baseUrl = $config['siteSettings']['baseUrl'] ?? null;
    $contactFormPath = $config['siteSettings']['officeContactFormPath'] ?? null;
    
    if ($baseUrl) {
        echo "     baseUrl: {$baseUrl}\n";
        
        // Check if it's an env var or actual URL
        if (strpos($baseUrl, 'getenv') !== false) {
            echo "     (from environment variable)\n";
        }
        
        // Verify it's not the example.com placeholder
        if (strpos($baseUrl, 'example.com') !== false) {
            echo "  ⚠️  WARNING: baseUrl still contains example.com\n";
        }
    } else {
        echo "     baseUrl: not set (will use Craft's primary site URL) ✅\n";
    }
    
    if ($contactFormPath) {
        echo "     contactFormPath: {$contactFormPath}\n";
    } else {
        echo "     contactFormPath: not set (will use default) ✅\n";
    }
    
    // Test URL construction
    echo "\n  Simulating URL construction:\n";
    $effectiveBaseUrl = $baseUrl ?: 'https://www.jensenhughes.com';
    $effectiveContactFormPath = $contactFormPath ?: '/contact/office-locations/form';
    $effectiveBaseUrl = rtrim($effectiveBaseUrl, '/');
    
    $testSlug = 'chicago';
    $testContactUrl = "{$effectiveBaseUrl}{$effectiveContactFormPath}/{$testSlug}";
    $testOfficeUrl = "{$effectiveBaseUrl}/offices/{$testSlug}";
    
    echo "     Example contact URL: {$testContactUrl}\n";
    echo "     Example office URL: {$testOfficeUrl}\n";
    
    // Verify URLs look correct
    if (strpos($testContactUrl, 'jensenhughes.com') !== false || 
        strpos($testContactUrl, 'example.com') !== false) {
        echo "  ✅ URLs properly constructed\n";
    } else {
        echo "  ⚠️  WARNING: URLs might not be correct\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Config validation complete!\n\n";

echo "Production Readiness:\n";
echo "  - Config file valid: ✅\n";
echo "  - Schemas configured: ✅\n";
echo "  - Site settings present: " . (isset($config['siteSettings']) ? '✅' : '⚠️ (using defaults)') . "\n";
echo "  - Dangerous tools disabled: " . (!($config['security']['enableDangerousTools'] ?? true) ? '✅' : '❌') . "\n";
echo "\n";
