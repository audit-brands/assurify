<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\CacheService;

class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;

    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->cacheService->flush(); // Start with clean cache
    }

    protected function tearDown(): void
    {
        $this->cacheService->flush(); // Clean up after tests
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->assertTrue($this->cacheService->set($key, $value));
        $this->assertEquals($value, $this->cacheService->get($key));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cacheService->get('non_existent_key'));
    }

    public function testSetWithTtl(): void
    {
        $key = 'ttl_test';
        $value = 'test_value';
        $ttl = 1; // 1 second
        
        $this->assertTrue($this->cacheService->set($key, $value, $ttl));
        $this->assertEquals($value, $this->cacheService->get($key));
        
        // Sleep to let cache expire
        sleep(2);
        $this->assertNull($this->cacheService->get($key));
    }

    public function testDelete(): void
    {
        $key = 'delete_test';
        $value = 'test_value';
        
        $this->cacheService->set($key, $value);
        $this->assertEquals($value, $this->cacheService->get($key));
        
        $this->assertTrue($this->cacheService->delete($key));
        $this->assertNull($this->cacheService->get($key));
    }

    public function testFlush(): void
    {
        $this->cacheService->set('key1', 'value1');
        $this->cacheService->set('key2', 'value2');
        
        $this->assertEquals('value1', $this->cacheService->get('key1'));
        $this->assertEquals('value2', $this->cacheService->get('key2'));
        
        $this->assertTrue($this->cacheService->flush());
        
        $this->assertNull($this->cacheService->get('key1'));
        $this->assertNull($this->cacheService->get('key2'));
    }

    public function testRemember(): void
    {
        $key = 'remember_test';
        $expectedValue = 'computed_value';
        $callCount = 0;
        
        $callback = function() use ($expectedValue, &$callCount) {
            $callCount++;
            return $expectedValue;
        };
        
        // First call should execute callback
        $result1 = $this->cacheService->remember($key, $callback);
        $this->assertEquals($expectedValue, $result1);
        $this->assertEquals(1, $callCount);
        
        // Second call should return cached value
        $result2 = $this->cacheService->remember($key, $callback);
        $this->assertEquals($expectedValue, $result2);
        $this->assertEquals(1, $callCount); // Callback not called again
    }

    public function testGetStats(): void
    {
        $this->cacheService->set('test1', 'value1');
        $this->cacheService->set('test2', 'value2');
        
        $stats = $this->cacheService->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('memory_cache_size', $stats);
        $this->assertArrayHasKey('file_cache_size', $stats);
        $this->assertArrayHasKey('cache_directory', $stats);
        
        $this->assertGreaterThanOrEqual(2, $stats['memory_cache_size']);
    }

    public function testCleanup(): void
    {
        // Set some items with short TTL
        $this->cacheService->set('expire1', 'value1', 1);
        $this->cacheService->set('expire2', 'value2', 1);
        $this->cacheService->set('keep', 'value3', 3600);
        
        // Wait for items to expire
        sleep(2);
        
        $cleaned = $this->cacheService->cleanup();
        
        $this->assertGreaterThanOrEqual(0, $cleaned);
        $this->assertNull($this->cacheService->get('expire1'));
        $this->assertNull($this->cacheService->get('expire2'));
        $this->assertEquals('value3', $this->cacheService->get('keep'));
    }

    public function testCacheComplexData(): void
    {
        $complexData = [
            'array' => [1, 2, 3],
            'object' => (object) ['prop' => 'value'],
            'null' => null,
            'boolean' => true,
            'number' => 42
        ];
        
        $this->assertTrue($this->cacheService->set('complex', $complexData));
        $retrieved = $this->cacheService->get('complex');
        
        $this->assertEquals($complexData['array'], $retrieved['array']);
        $this->assertEquals($complexData['null'], $retrieved['null']);
        $this->assertEquals($complexData['boolean'], $retrieved['boolean']);
        $this->assertEquals($complexData['number'], $retrieved['number']);
    }

    public function testInvalidateStory(): void
    {
        $storyId = 123;
        
        // Set up some cached data
        $this->cacheService->set("story:{$storyId}", 'story_data');
        $this->cacheService->set("comments:story:{$storyId}", 'comments_data');
        $this->cacheService->set('stories:newest', 'stories_list');
        
        // Invalidate story cache
        $this->cacheService->invalidateStory($storyId);
        
        // Story-specific caches should be cleared
        $this->assertNull($this->cacheService->get("story:{$storyId}"));
        $this->assertNull($this->cacheService->get("comments:story:{$storyId}"));
    }
}