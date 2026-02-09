<?php
/**
 * Test script for Tool Registry
 * Run: php tests/test-tool-registry.php
 */

// Manually include necessary files
require_once __DIR__ . '/../src/attributes/Tool.php';

use rocketpark\mcpwrapper\attributes\Tool;

echo "Testing Tool Attribute and Discovery...\n\n";

// Test 1: Create a test tool class
class TestTools {
    #[Tool(
        name: 'test_tool',
        description: 'A test tool',
        inputSchema: ['type' => 'object'],
        dangerous: false
    )]
    public function testMethod(string $param1, int $param2 = 10): array {
        return [
            'success' => true,
            'param1' => $param1,
            'param2' => $param2,
        ];
    }
}

// Test 2: Reflect and discover the tool
$reflection = new ReflectionClass(TestTools::class);
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

echo "Test 1 - Tool Discovery: ";
$found = false;
foreach ($methods as $method) {
    $attributes = $method->getAttributes(Tool::class);
    if (!empty($attributes)) {
        $found = true;
        $toolAttr = $attributes[0]->newInstance();
        
        if ($toolAttr->name === 'test_tool' && 
            $toolAttr->description === 'A test tool' && 
            $toolAttr->dangerous === false) {
            echo "✓ PASS\n";
        } else {
            echo "✗ FAIL (attribute values incorrect)\n";
        }
    }
}

if (!$found) {
    echo "✗ FAIL (no attributes found)\n";
}

// Test 3: Instantiate and call the tool
echo "Test 2 - Tool Execution: ";
$instance = new TestTools();
$result = $instance->testMethod('hello', 42);

if ($result['success'] === true && 
    $result['param1'] === 'hello' && 
    $result['param2'] === 42) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
    var_dump($result);
}

// Test 4: Test with default parameter
echo "Test 3 - Default Parameters: ";
$result = $instance->testMethod('world');

if ($result['success'] === true && 
    $result['param1'] === 'world' && 
    $result['param2'] === 10) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
    var_dump($result);
}

echo "\n✓ All tool registry tests passed!\n";
