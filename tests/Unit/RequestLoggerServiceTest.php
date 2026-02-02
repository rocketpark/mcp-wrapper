<?php

namespace rocketpark\mcpwrapper\tests\Unit;

use PHPUnit\Framework\TestCase;
use rocketpark\mcpwrapper\services\RequestLoggerService;

/**
 * RequestLoggerService Test Suite
 * 
 * Tests request logging functionality:
 * - Argument hashing (privacy)
 * - IP anonymization (privacy)
 * - Service instantiation
 * 
 * Note: Full integration tests (actual logging, analytics) require Craft CMS app context.
 * These unit tests focus on the private helper methods via reflection.
 */
class RequestLoggerServiceTest extends TestCase
{
    private RequestLoggerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RequestLoggerService();
    }

    /**
     * Test IP anonymization for IPv4
     */
    public function testIpv4Anonymization(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('anonymizeIp');
        $method->setAccessible(true);
        
        $testCases = [
            '192.168.1.100' => '192.168.**.**',
            '10.0.0.255' => '10.0.**.**',
            '172.16.254.1' => '172.16.**.**',
            '8.8.8.8' => '8.8.**.**',
        ];
        
        foreach ($testCases as $original => $expected) {
            $result = $method->invoke($this->service, $original);
            $this->assertEquals($expected, $result, "Failed to anonymize IPv4: $original");
        }
    }

    /**
     * Test IP anonymization for IPv6
     */
    public function testIpv6Anonymization(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('anonymizeIp');
        $method->setAccessible(true);
        
        $testCases = [
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334' => '2001:0db8:85a3:0000:****',
            '2001:db8::1' => '2001:db8::1:****',
            'fe80::1' => 'fe80::1:****',
        ];
        
        foreach ($testCases as $original => $expected) {
            $result = $method->invoke($this->service, $original);
            $this->assertEquals($expected, $result, "Failed to anonymize IPv6: $original");
        }
    }

    /**
     * Test IP anonymization handles unknown format
     */
    public function testUnknownIpFormat(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('anonymizeIp');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service, 'not-an-ip');
        $this->assertEquals('unknown', $result);
    }

    /**
     * Test argument hashing for privacy
     */
    public function testArgumentHashing(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $arguments = [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'apiKey' => 'abc-def-ghi'
        ];
        
        $hashed = $method->invoke($this->service, $arguments);
        
        // Should be 8-char MD5 prefix
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $hashed);
    }

    /**
     * Test argument hashing is consistent
     */
    public function testArgumentHashingConsistency(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $arguments = ['id' => 123, 'siteId' => 1];
        
        $hash1 = $method->invoke($this->service, $arguments);
        $hash2 = $method->invoke($this->service, $arguments);
        
        $this->assertEquals($hash1, $hash2);
    }

    /**
     * Test argument hashing handles empty args
     */
    public function testArgumentHashingEmpty(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service, []);
        $this->assertEquals('none', $result);
    }

    /**
     * Test argument order normalization (ksort)
     */
    public function testArgumentHashingNormalization(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $args1 = ['a' => 1, 'b' => 2, 'c' => 3];
        $args2 = ['c' => 3, 'a' => 1, 'b' => 2];
        $args3 = ['b' => 2, 'c' => 3, 'a' => 1];
        
        $hash1 = $method->invoke($this->service, $args1);
        $hash2 = $method->invoke($this->service, $args2);
        $hash3 = $method->invoke($this->service, $args3);
        
        // All should produce same hash (ksort normalizes)
        $this->assertEquals($hash1, $hash2);
        $this->assertEquals($hash1, $hash3);
    }

    /**
     * Test different arguments produce different hashes
     */
    public function testArgumentHashingUniqueness(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $hash1 = $method->invoke($this->service, ['id' => 123]);
        $hash2 = $method->invoke($this->service, ['id' => 456]);
        $hash3 = $method->invoke($this->service, ['id' => 123, 'siteId' => 1]);
        
        $this->assertNotEquals($hash1, $hash2);
        $this->assertNotEquals($hash1, $hash3);
    }

    /**
     * Test service instantiation doesn't require Craft app
     */
    public function testServiceInstantiation(): void
    {
        $service = new RequestLoggerService();
        $this->assertInstanceOf(RequestLoggerService::class, $service);
    }

    /**
     * Test complex nested arguments hash correctly
     */
    public function testComplexArgumentHashing(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashArguments');
        $method->setAccessible(true);
        
        $complexArgs = [
            'nested' => ['level1' => ['level2' => 'value']],
            'array' => [1, 2, 3],
            'string' => 'test'
        ];
        
        $hash = $method->invoke($this->service, $complexArgs);
        
        // Should produce valid 8-char hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $hash);
    }
}

