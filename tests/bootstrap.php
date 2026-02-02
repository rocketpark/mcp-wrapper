<?php
/**
 * PHPUnit Bootstrap File
 * Sets up test environment for MCP Wrapper tests
 */

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define test constants
defined('CRAFT_VENDOR_PATH') or define('CRAFT_VENDOR_PATH', dirname(__DIR__) . '/vendor');
defined('CRAFT_BASE_PATH') or define('CRAFT_BASE_PATH', dirname(__DIR__));
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

// Initialize Yii
require_once CRAFT_VENDOR_PATH . '/yiisoft/yii2/Yii.php';

// Mock Craft application for testing
// Note: Full Craft tests would need a test database, but for unit tests
// we'll mock the services we need
