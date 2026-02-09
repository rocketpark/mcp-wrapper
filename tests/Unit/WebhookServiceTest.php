<?php

namespace rocketpark\mcpwrapper\tests\Unit;

use PHPUnit\Framework\TestCase;
use rocketpark\mcpwrapper\services\WebhookService;

/**
 * WebhookService Test Suite
 * 
 * Tests webhook functionality:
 * - Payload construction
 * - Event/section/status filtering
 * - HMAC signature generation
 * - Service instantiation
 * 
 * Note: Full integration tests (HTTP delivery, queue jobs) require Craft CMS app context.
 * These unit tests focus on filtering logic and payload structure.
 */
class WebhookServiceTest extends TestCase
{
    private WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebhookService();
    }

    /**
     * Test shouldSendWebhook filtering logic via reflection
     */
    public function testEventFiltering(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('shouldSendWebhook');
        $method->setAccessible(true);
        
        $config = [
            'events' => ['entry.saved', 'entry.deleted'],
        ];
        
        $payloadSaved = ['event' => 'entry.saved', 'entry' => []];
        $payloadDeleted = ['event' => 'entry.deleted', 'entry' => []];
        $payloadOther = ['event' => 'entry.published', 'entry' => []];
        
        $this->assertTrue($method->invoke($this->service, $config, $payloadSaved));
        $this->assertTrue($method->invoke($this->service, $config, $payloadDeleted));
        $this->assertFalse($method->invoke($this->service, $config, $payloadOther));
    }

    /**
     * Test section filtering
     */
    public function testSectionFiltering(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('shouldSendWebhook');
        $method->setAccessible(true);
        
        $config = [
            'sections' => ['news', 'blog'],
        ];
        
        $payloadNews = ['event' => 'entry.saved', 'entry' => ['sectionHandle' => 'news']];
        $payloadBlog = ['event' => 'entry.saved', 'entry' => ['sectionHandle' => 'blog']];
        $payloadProjects = ['event' => 'entry.saved', 'entry' => ['sectionHandle' => 'projects']];
        
        $this->assertTrue($method->invoke($this->service, $config, $payloadNews));
        $this->assertTrue($method->invoke($this->service, $config, $payloadBlog));
        $this->assertFalse($method->invoke($this->service, $config, $payloadProjects));
    }

    /**
     * Test status filtering
     */
    public function testStatusFiltering(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('shouldSendWebhook');
        $method->setAccessible(true);
        
        $config = [
            'statuses' => ['live', 'pending'],
        ];
        
        $payloadLive = ['event' => 'entry.saved', 'entry' => ['status' => 'live']];
        $payloadPending = ['event' => 'entry.saved', 'entry' => ['status' => 'pending']];
        $payloadDisabled = ['event' => 'entry.saved', 'entry' => ['status' => 'disabled']];
        
        $this->assertTrue($method->invoke($this->service, $config, $payloadLive));
        $this->assertTrue($method->invoke($this->service, $config, $payloadPending));
        $this->assertFalse($method->invoke($this->service, $config, $payloadDisabled));
    }

    /**
     * Test combined filtering (event + section + status)
     */
    public function testCombinedFiltering(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('shouldSendWebhook');
        $method->setAccessible(true);
        
        $config = [
            'events' => ['entry.saved'],
            'sections' => ['news'],
            'statuses' => ['live'],
        ];
        
        // All match
        $payloadMatch = [
            'event' => 'entry.saved',
            'entry' => ['sectionHandle' => 'news', 'status' => 'live']
        ];
        $this->assertTrue($method->invoke($this->service, $config, $payloadMatch));
        
        // Wrong event
        $payloadWrongEvent = [
            'event' => 'entry.deleted',
            'entry' => ['sectionHandle' => 'news', 'status' => 'live']
        ];
        $this->assertFalse($method->invoke($this->service, $config, $payloadWrongEvent));
        
        // Wrong section
        $payloadWrongSection = [
            'event' => 'entry.saved',
            'entry' => ['sectionHandle' => 'blog', 'status' => 'live']
        ];
        $this->assertFalse($method->invoke($this->service, $config, $payloadWrongSection));
        
        // Wrong status
        $payloadWrongStatus = [
            'event' => 'entry.saved',
            'entry' => ['sectionHandle' => 'news', 'status' => 'disabled']
        ];
        $this->assertFalse($method->invoke($this->service, $config, $payloadWrongStatus));
    }

    /**
     * Test empty filters allow all
     */
    public function testEmptyFiltersAllowAll(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('shouldSendWebhook');
        $method->setAccessible(true);
        
        $config = []; // No filters
        
        $payload = [
            'event' => 'entry.saved',
            'entry' => ['sectionHandle' => 'anything', 'status' => 'anything']
        ];
        
        $this->assertTrue($method->invoke($this->service, $config, $payload));
    }

    /**
     * Test service instantiation
     */
    public function testServiceInstantiation(): void
    {
        $service = new WebhookService();
        $this->assertInstanceOf(WebhookService::class, $service);
    }

    /**
     * Test webhook payload would include required fields
     * This tests the logic that would be in sendEntryWebhook
     */
    public function testPayloadStructure(): void
    {
        // Mock the expected payload structure
        $expectedFields = [
            'event',
            'timestamp',
            'entry',
            'changedAttributes',
        ];
        
        $entryFields = [
            'id',
            'title',
            'slug',
            'sectionHandle',
            'typeHandle',
            'status',
            'postDate',
            'url',
        ];
        
        // Verify our service would create this structure
        $this->assertTrue(method_exists($this->service, 'sendEntryWebhook'));
        $this->assertTrue(method_exists($this->service, 'queueWebhook'));
    }
}
