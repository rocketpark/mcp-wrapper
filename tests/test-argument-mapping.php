<?php
/**
 * Test script for argument mapping
 * Run: php tests/test-argument-mapping.php
 */

echo "Testing Argument Mapping...\n\n";

// Test tool class
class TestTools {
    public function testMethod(string $param1, int $param2 = 10, ?string $param3 = null): array {
        return [
            'param1' => $param1,
            'param2' => $param2,
            'param3' => $param3,
        ];
    }
}

// Simulate JSON-RPC named arguments
$arguments = [
    'param1' => 'hello',
    'param2' => 42,
];

// Map named arguments to positional
$method = new ReflectionMethod(TestTools::class, 'testMethod');
$params = $method->getParameters();
$orderedArgs = [];

foreach ($params as $param) {
    $paramName = $param->getName();
    
    if (array_key_exists($paramName, $arguments)) {
        $orderedArgs[] = $arguments[$paramName];
    } elseif ($param->isDefaultValueAvailable()) {
        $orderedArgs[] = $param->getDefaultValue();
    } else {
        throw new Exception("Missing required parameter: {$paramName}");
    }
}

echo "Test 1 - Named to Positional: ";
if (count($orderedArgs) === 3 && 
    $orderedArgs[0] === 'hello' && 
    $orderedArgs[1] === 42 && 
    $orderedArgs[2] === null) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
    var_dump($orderedArgs);
}

// Execute the method
$instance = new TestTools();
$result = $instance->testMethod(...$orderedArgs);

echo "Test 2 - Method Execution: ";
if ($result['param1'] === 'hello' && 
    $result['param2'] === 42 && 
    $result['param3'] === null) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
    var_dump($result);
}

// Test with only required args
$arguments2 = ['param1' => 'world'];
$orderedArgs2 = [];

foreach ($params as $param) {
    $paramName = $param->getName();
    
    if (array_key_exists($paramName, $arguments2)) {
        $orderedArgs2[] = $arguments2[$paramName];
    } elseif ($param->isDefaultValueAvailable()) {
        $orderedArgs2[] = $param->getDefaultValue();
    } else {
        throw new Exception("Missing required parameter: {$paramName}");
    }
}

$result2 = $instance->testMethod(...$orderedArgs2);

echo "Test 3 - Default Values: ";
if ($result2['param1'] === 'world' && 
    $result2['param2'] === 10 && 
    $result2['param3'] === null) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
    var_dump($result2);
}

echo "\n✓ All argument mapping tests passed!\n";
