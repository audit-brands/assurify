<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\OfflineSyncService;

class OfflineSyncServiceTest extends TestCase
{
    private OfflineSyncService $syncService;
    private string $testStoragePath;
    
    protected function setUp(): void
    {
        $this->syncService = new OfflineSyncService();
        
        // Use a test storage path
        $this->testStoragePath = __DIR__ . '/../../storage/test_offline';
        if (!is_dir($this->testStoragePath)) {
            mkdir($this->testStoragePath, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testStoragePath)) {
            $files = glob($this->testStoragePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testStoragePath);
        }
    }
    
    public function testQueueAction(): void
    {
        $actionType = 'create_comment';
        $actionData = [
            'story_id' => 123,
            'comment' => 'Test comment'
        ];
        $userId = 456;
        
        $result = $this->syncService->queueAction($actionType, $actionData, $userId);
        
        $this->assertTrue($result);
    }
    
    public function testStoreAndRetrieveOfflineData(): void
    {
        $key = 'test_data';
        $data = [
            'id' => 1,
            'title' => 'Test Story',
            'content' => 'Test content'
        ];
        $ttl = 3600;
        
        // Store data
        $stored = $this->syncService->storeOfflineData($key, $data, $ttl);
        $this->assertTrue($stored);
        
        // Retrieve data
        $retrieved = $this->syncService->getOfflineData($key);
        $this->assertEquals($data, $retrieved);
    }
    
    public function testGetExpiredOfflineData(): void
    {
        $key = 'expired_data';
        $data = ['test' => 'data'];
        $ttl = -1; // Already expired
        
        $this->syncService->storeOfflineData($key, $data, $ttl);
        
        // Should return null for expired data
        $retrieved = $this->syncService->getOfflineData($key);
        $this->assertNull($retrieved);
    }
    
    public function testCacheAndRetrieveStories(): void
    {
        $stories = [
            [
                'id' => 1,
                'title' => 'Story 1',
                'url' => 'https://example.com/1'
            ],
            [
                'id' => 2,
                'title' => 'Story 2',
                'url' => 'https://example.com/2'
            ]
        ];
        
        // Cache stories
        $cached = $this->syncService->cacheStories($stories);
        $this->assertTrue($cached);
        
        // Retrieve cached stories
        $retrieved = $this->syncService->getCachedStories();
        $this->assertEquals($stories, $retrieved);
    }
    
    public function testCacheAndRetrieveComments(): void
    {
        $storyId = 123;
        $comments = [
            [
                'id' => 1,
                'comment' => 'First comment',
                'user' => 'testuser1'
            ],
            [
                'id' => 2,
                'comment' => 'Second comment',
                'user' => 'testuser2'
            ]
        ];
        
        // Cache comments
        $cached = $this->syncService->cacheComments($storyId, $comments);
        $this->assertTrue($cached);
        
        // Retrieve cached comments
        $retrieved = $this->syncService->getCachedComments($storyId);
        $this->assertEquals($comments, $retrieved);
    }
    
    public function testCachedStoriesLimit(): void
    {
        $stories = [];
        for ($i = 1; $i <= 100; $i++) {
            $stories[] = [
                'id' => $i,
                'title' => "Story {$i}",
                'url' => "https://example.com/{$i}"
            ];
        }
        
        $this->syncService->cacheStories($stories);
        
        // Test limit functionality
        $limited = $this->syncService->getCachedStories(10);
        $this->assertCount(10, $limited);
        
        // Test default limit
        $default = $this->syncService->getCachedStories();
        $this->assertCount(50, $default); // Default limit is 50
    }
    
    public function testCleanupExpiredData(): void
    {
        // Store some data with short TTL
        $this->syncService->storeOfflineData('temp1', ['test' => 'data1'], -1);
        $this->syncService->storeOfflineData('temp2', ['test' => 'data2'], -1);
        $this->syncService->storeOfflineData('persistent', ['test' => 'data3'], 3600);
        
        // Cleanup expired data
        $cleaned = $this->syncService->cleanupExpiredData();
        
        $this->assertEquals(2, $cleaned);
        
        // Verify persistent data still exists
        $persistent = $this->syncService->getOfflineData('persistent');
        $this->assertNotNull($persistent);
        $this->assertEquals(['test' => 'data3'], $persistent);
    }
    
    public function testGetSyncStats(): void
    {
        // Queue some actions
        $this->syncService->queueAction('test_action1', ['data' => 1]);
        $this->syncService->queueAction('test_action2', ['data' => 2]);
        
        // Store some offline data
        $this->syncService->storeOfflineData('test_data', ['test' => 'value']);
        
        $stats = $this->syncService->getSyncStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending_actions', $stats);
        $this->assertArrayHasKey('failed_actions', $stats);
        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('cached_items', $stats);
        $this->assertArrayHasKey('last_sync', $stats);
        
        $this->assertGreaterThanOrEqual(2, $stats['total_actions']);
        $this->assertGreaterThanOrEqual(1, $stats['cached_items']);
    }
    
    public function testConflictResolution(): void
    {
        $localData = [
            'id' => 1,
            'title' => 'Local Title',
            'updated_at' => 1640995200 // 2022-01-01
        ];
        
        $serverData = [
            'id' => 1,
            'title' => 'Server Title',
            'updated_at' => 1640998800 // 2022-01-01 01:00:00
        ];
        
        // Test server wins strategy
        $resolved = $this->syncService->resolveConflict($localData, $serverData, 'server_wins');
        $this->assertEquals($serverData, $resolved);
        
        // Test local wins strategy
        $resolved = $this->syncService->resolveConflict($localData, $serverData, 'local_wins');
        $this->assertEquals($localData, $resolved);
        
        // Test merge strategy
        $resolved = $this->syncService->resolveConflict($localData, $serverData, 'merge');
        $expected = array_merge($serverData, $localData);
        $this->assertEquals($expected, $resolved);
        
        // Test latest timestamp strategy
        $resolved = $this->syncService->resolveConflict($localData, $serverData, 'latest_timestamp');
        $this->assertEquals($serverData, $resolved); // Server has later timestamp
    }
    
    public function testUpdateLastSyncTime(): void
    {
        $beforeTime = time();
        $this->syncService->updateLastSyncTime();
        $afterTime = time();
        
        $stats = $this->syncService->getSyncStats();
        $lastSync = $stats['last_sync'];
        
        $this->assertNotNull($lastSync);
        $this->assertGreaterThanOrEqual($beforeTime, $lastSync);
        $this->assertLessThanOrEqual($afterTime, $lastSync);
    }
    
    public function testSyncPendingActionsWithEmptyQueue(): void
    {
        // Create a fresh service instance to ensure no pending actions
        $freshService = new OfflineSyncService();
        
        // Test sync with no pending actions
        $results = $freshService->syncPendingActions();
        
        $this->assertIsArray($results);
        $this->assertIsInt($results['synced']);
        $this->assertIsInt($results['failed']);
        $this->assertIsArray($results['errors']);
    }
}