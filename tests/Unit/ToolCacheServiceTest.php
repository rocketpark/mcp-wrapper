<?php

namespace rocketpark\mcpwrapper\tests\Unit;

use PHPUnit\Framework\TestCase;
use rocketpark\mcpwrapper\services\ToolCacheService;

/**
 * ToolCacheService Test Suite
 * 
 * Tests caching functionality including:
 * - Cache key generation (MD5 hashing)
 * - Key consistency and uniqueness
 * - Argument normalization
 * 
 * Note: Full integration tests (TTL, exclusions, stats) require Craft CMS app context.
 * These unit tests focus on the public getCacheKey method and logic.
 */
class ToolCacheServiceTest extends TestCase
{
    private ToolCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ToolCacheService();
    }

    /**
     * Test cache key generation via reflection
     * Since getCacheKey is private, we test it via reflection
     */
    public function testCacheKeyGenerationUsesMd5(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'craft_get_entry_by_id';
        $arguments = ['id' => 123, 'siteId' => 1];
        
        $cacheKey = $method->invoke($this->service, $toolName, $arguments);
        
        // Key format: mcp_tool_result_{toolName}_{md5hash}
        $this->assertStringStartsWith('mcp_tool_result_craft_get_entry_by_id_', $cacheKey);
        
        // Extract hash portion (last 32 chars)
        $parts = explode('_', $cacheKey);
        $hash = end($parts);
        
        // Verify it's an MD5 hash (32 chars, hex)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
    }

    /**
     * Test same args produce same cache key
     */
    public function testConsistentCacheKeys(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'test_tool';
        $arguments = ['id' => 123, 'siteId' => 1];
        
        $key1 = $method->invoke($this->service, $toolName, $arguments);
        $key2 = $method->invoke($this->service, $toolName, $arguments);
        
        $this->assertEquals($key1, $key2);
    }

    /**
     * Test different args produce different cache keys
     */
    public function testUniqueCacheKeys(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'test_tool';
        
        $key1 = $method->invoke($this->service, $toolName, ['id' => 123]);
        $key2 = $method->invoke($this->service, $toolName, ['id' => 456]);
        $key3 = $method->invoke($this->service, $toolName, ['id' => 123, 'siteId' => 1]);
        
        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertNotEquals($key2, $key3);
    }

    /**
     * Test argument order doesn't affect cache key (ksort normalization)
     */
    public function testArgumentOrderNormalization(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'test_tool';
        $args1 = ['a' => 1, 'b' => 2, 'c' => 3];
        $args2 = ['c' => 3, 'a' => 1, 'b' => 2];
        $args3 = ['b' => 2, 'c' => 3, 'a' => 1];
        
        $key1 = $method->invoke($this->service, $toolName, $args1);
        $key2 = $method->invoke($this->service, $toolName, $args2);
        $key3 = $method->invoke($this->service, $toolName, $args3);
        
        // All should produce same key (ksort normalizes order)
        $this->assertEquals($key1, $key2);
        $this->assertEquals($key1, $key3);
    }

    /**
     * Test cache key handles complex nested arguments
     */
    public function testComplexArgumentHandling(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'test_tool';
        $complexArgs = [
            'nested' => [
                'level1' => [
                    'level2' => 'deep value'
                ]
            ],
            'array' => [1, 2, 3],
            'string' => 'test',
            'number' => 42,
            'bool' => true
        ];
        
        // Should not throw exception
        $key = $method->invoke($this->service, $toolName, $complexArgs);
        
        $this->assertIsString($key);
        $this->assertStringStartsWith('mcp_tool_result_test_tool_', $key);
    }

    /**
     * Test cache key handles empty arguments
     */
    public function testEmptyArgumentsHandling(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $toolName = 'test_tool';
        $emptyArgs = [];
        
        $key = $method->invoke($this->service, $toolName, $emptyArgs);
        
        $this->assertIsString($key);
        $this->assertStringStartsWith('mcp_tool_result_test_tool_', $key);
    }

    /**
     * Test different tool names produce different keys (even with same args)
     */
    public function testToolNameDifferentiation(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $arguments = ['id' => 123];
        
        $key1 = $method->invoke($this->service, 'tool_a', $arguments);
        $key2 = $method->invoke($this->service, 'tool_b', $arguments);
        
        $this->assertNotEquals($key1, $key2);
        $this->assertStringContainsString('tool_a', $key1);
        $this->assertStringContainsString('tool_b', $key2);
    }

    /**
     * Test service instantiation doesn't require Craft app
     */
    public function testServiceInstantiation(): void
    {
        $service = new ToolCacheService();
        $this->assertInstanceOf(ToolCacheService::class, $service);
    }
}

