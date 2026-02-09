<?php

namespace rocketpark\mcpwrapper\tests\Unit;

use PHPUnit\Framework\TestCase;
use rocketpark\mcpwrapper\attributes\Tool;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tool Attribute Test Suite
 * 
 * Tests the #[Tool] attribute functionality:
 * - Attribute application to methods
 * - Reflection and discovery
 * - Parameter validation
 * - Enhanced annotations (output schemas, cost/confidentiality hints)
 */
class ToolAttributeTest extends TestCase
{
    /**
     * Test Tool attribute can be applied to methods
     */
    public function testToolAttributeApplication(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $method = $reflection->getMethod('basicTool');
        $attributes = $method->getAttributes(Tool::class);
        
        $this->assertCount(1, $attributes);
    }

    /**
     * Test Tool attribute contains correct properties
     */
    public function testToolAttributeProperties(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $method = $reflection->getMethod('basicTool');
        $attributes = $method->getAttributes(Tool::class);
        
        $attribute = $attributes[0]->newInstance();
        
        $this->assertEquals('test_basic_tool', $attribute->name);
        $this->assertEquals('A basic test tool', $attribute->description);
        $this->assertIsArray($attribute->inputSchema);
        $this->assertEquals('object', $attribute->inputSchema['type']);
    }

    /**
     * Test Tool attribute with enhanced annotations
     */
    public function testEnhancedAnnotations(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $method = $reflection->getMethod('enhancedTool');
        $attributes = $method->getAttributes(Tool::class);
        
        $attribute = $attributes[0]->newInstance();
        
        $this->assertEquals('test_enhanced_tool', $attribute->name);
        $this->assertIsArray($attribute->outputSchema);
        $this->assertEquals('low', $attribute->costHint);
        $this->assertEquals('medium', $attribute->confidentialityHint);
    }

    /**
     * Test Tool attribute with dangerous flag
     */
    public function testDangerousFlag(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $method = $reflection->getMethod('dangerousTool');
        $attributes = $method->getAttributes(Tool::class);
        
        $attribute = $attributes[0]->newInstance();
        
        $this->assertTrue($attribute->dangerous);
        $this->assertEquals('high', $attribute->costHint);
    }

    /**
     * Test output schema structure
     */
    public function testOutputSchemaStructure(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $method = $reflection->getMethod('enhancedTool');
        $attributes = $method->getAttributes(Tool::class);
        
        $attribute = $attributes[0]->newInstance();
        $schema = $attribute->outputSchema;
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('success', $schema['properties']);
        $this->assertArrayHasKey('data', $schema['properties']);
    }

    /**
     * Test multiple tools can be discovered from a class
     */
    public function testMultipleToolDiscovery(): void
    {
        $reflection = new ReflectionClass(TestToolClass::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $toolCount = 0;
        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Tool::class);
            if (!empty($attributes)) {
                $toolCount++;
            }
        }
        
        $this->assertEquals(3, $toolCount);
    }

    /**
     * Test Tool attribute handles all cost hint values
     */
    public function testCostHintValues(): void
    {
        $validCostHints = ['low', 'medium', 'high'];
        
        // basicTool = default 'low'
        // enhancedTool = explicit 'low'
        // dangerousTool = explicit 'high'
        
        $reflection = new ReflectionClass(TestToolClass::class);
        
        $basic = $reflection->getMethod('basicTool')->getAttributes(Tool::class)[0]->newInstance();
        $this->assertEquals('low', $basic->costHint); // Default value
        
        $enhanced = $reflection->getMethod('enhancedTool')->getAttributes(Tool::class)[0]->newInstance();
        $this->assertContains($enhanced->costHint, $validCostHints);
        
        $dangerous = $reflection->getMethod('dangerousTool')->getAttributes(Tool::class)[0]->newInstance();
        $this->assertContains($dangerous->costHint, $validCostHints);
    }

    /**
     * Test Tool attribute handles all confidentiality hint values
     */
    public function testConfidentialityHintValues(): void
    {
        $validHints = ['none', 'low', 'medium', 'high'];
        
        $reflection = new ReflectionClass(TestToolClass::class);
        
        $enhanced = $reflection->getMethod('enhancedTool')->getAttributes(Tool::class)[0]->newInstance();
        $this->assertContains($enhanced->confidentialityHint, $validHints);
        
        $dangerous = $reflection->getMethod('dangerousTool')->getAttributes(Tool::class)[0]->newInstance();
        $this->assertContains($dangerous->confidentialityHint, $validHints);
    }
}

/**
 * Test class with Tool attributes for testing
 */
class TestToolClass
{
    #[Tool(
        name: 'test_basic_tool',
        description: 'A basic test tool',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ],
        dangerous: false
    )]
    public function basicTool(string $param): array
    {
        return ['success' => true, 'param' => $param];
    }

    #[Tool(
        name: 'test_enhanced_tool',
        description: 'A tool with enhanced annotations',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer']
            ]
        ],
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => ['type' => 'object']
            ]
        ],
        costHint: 'low',
        confidentialityHint: 'medium',
        dangerous: false
    )]
    public function enhancedTool(int $id): array
    {
        return ['success' => true, 'data' => ['id' => $id]];
    }

    #[Tool(
        name: 'test_dangerous_tool',
        description: 'A dangerous tool that modifies data',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string']
            ]
        ],
        outputSchema: [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean']
            ]
        ],
        costHint: 'high',
        confidentialityHint: 'none',
        dangerous: true
    )]
    public function dangerousTool(string $action): array
    {
        return ['success' => true];
    }
}
