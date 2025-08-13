<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\OfflineSyncService;

class OfflineSyncIntegrationTest extends TestCase
{
    private OfflineSyncService $syncService;
    
    protected function setUp(): void
    {
        $this->syncService = new OfflineSyncService();
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }
    
    private function cleanupTestData(): void
    {
        $storageDir = __DIR__ . '/../../storage/offline';
        if (is_dir($storageDir)) {
            $files = glob($storageDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    public function testCompleteOfflineWorkflow(): void
    {
        // 1. Test queueing multiple offline actions
        $commentAction = $this->syncService->queueAction('create_comment', [
            'story_id' => 123,
            'comment' => 'This is an offline comment',
            'parent_comment_id' => null
        ], 456);
        
        $voteAction = $this->syncService->queueAction('vote_story', [
            'story_id' => 123,
            'vote' => 'up'
        ], 456);
        
        $flagAction = $this->syncService->queueAction('flag_comment', [
            'comment_id' => 789,
            'reason' => 'Spam'
        ], 456);
        
        $this->assertTrue($commentAction);
        $this->assertTrue($voteAction);
        $this->assertTrue($flagAction);
        
        // 2. Test caching data for offline access
        $stories = [
            [
                'id' => 1,
                'title' => 'Offline Story 1',
                'url' => 'https://example.com/1',
                'score' => 10,
                'comment_count' => 5
            ],
            [
                'id' => 2,
                'title' => 'Offline Story 2',
                'url' => 'https://example.com/2',
                'score' => 8,
                'comment_count' => 3
            ]
        ];
        
        $storyCache = $this->syncService->cacheStories($stories);
        $this->assertTrue($storyCache);
        
        $comments = [
            [
                'id' => 1,
                'comment' => 'First offline comment',
                'username' => 'user1',
                'score' => 2
            ],
            [
                'id' => 2,
                'comment' => 'Second offline comment',
                'username' => 'user2',
                'score' => 1
            ]
        ];
        
        $commentCache = $this->syncService->cacheComments(1, $comments);
        $this->assertTrue($commentCache);
        
        // 3. Test retrieving cached data
        $cachedStories = $this->syncService->getCachedStories();
        $this->assertEquals($stories, $cachedStories);
        
        $cachedComments = $this->syncService->getCachedComments(1);
        $this->assertEquals($comments, $cachedComments);
        
        // 4. Test sync statistics
        $stats = $this->syncService->getSyncStats();
        $this->assertGreaterThanOrEqual(3, $stats['total_actions']);
        $this->assertGreaterThanOrEqual(2, $stats['cached_items']);
        
        // 5. Test custom data caching
        $customData = [
            'user_preferences' => [
                'theme' => 'dark',
                'notifications' => true
            ],
            'reading_list' => [1, 2, 3, 4, 5]
        ];
        
        $customCache = $this->syncService->storeOfflineData('user_data', $customData, 7200);
        $this->assertTrue($customCache);
        
        $retrievedCustomData = $this->syncService->getOfflineData('user_data');
        $this->assertEquals($customData, $retrievedCustomData);
        
        // 6. Test data expiration and cleanup
        $expiredData = $this->syncService->storeOfflineData('temp_data', ['temp' => 'value'], -1);
        $this->assertTrue($expiredData);
        
        $expiredRetrieve = $this->syncService->getOfflineData('temp_data');
        $this->assertNull($expiredRetrieve);
        
        $cleaned = $this->syncService->cleanupExpiredData();
        $this->assertGreaterThanOrEqual(0, $cleaned);
        
        // 7. Test conflict resolution scenarios
        $localData = [
            'id' => 1,
            'title' => 'Local Version',
            'content' => 'Modified locally',
            'updated_at' => 1640995200
        ];
        
        $serverData = [
            'id' => 1,
            'title' => 'Server Version',
            'content' => 'Modified on server',
            'updated_at' => 1640998800
        ];
        
        // Test different conflict resolution strategies
        $serverWins = $this->syncService->resolveConflict($localData, $serverData, 'server_wins');
        $this->assertEquals($serverData, $serverWins);
        
        $localWins = $this->syncService->resolveConflict($localData, $serverData, 'local_wins');
        $this->assertEquals($localData, $localWins);
        
        $merged = $this->syncService->resolveConflict($localData, $serverData, 'merge');
        $expected = array_merge($serverData, $localData);
        $this->assertEquals($expected, $merged);
        
        $latestTimestamp = $this->syncService->resolveConflict($localData, $serverData, 'latest_timestamp');
        $this->assertEquals($serverData, $latestTimestamp); // Server has later timestamp
        
        // 8. Test updating sync time
        $beforeSync = time();
        $this->syncService->updateLastSyncTime();
        $afterSync = time();
        
        $finalStats = $this->syncService->getSyncStats();
        $lastSync = $finalStats['last_sync'];
        
        $this->assertGreaterThanOrEqual($beforeSync, $lastSync);
        $this->assertLessThanOrEqual($afterSync, $lastSync);
    }
    
    public function testOfflineDataPersistence(): void
    {
        // Test that data persists across service instances
        $originalService = new OfflineSyncService();
        
        // Store data with first instance
        $testData = [
            'persistent_key' => 'persistent_value',
            'timestamp' => time()
        ];
        
        $stored = $originalService->storeOfflineData('persistence_test', $testData);
        $this->assertTrue($stored);
        
        // Create new instance and verify data persists
        $newService = new OfflineSyncService();
        $retrieved = $newService->getOfflineData('persistence_test');
        
        $this->assertEquals($testData, $retrieved);
    }
    
    public function testLargeDataCaching(): void
    {
        // Test caching large amounts of data
        $largeStorySet = [];
        for ($i = 1; $i <= 1000; $i++) {
            $largeStorySet[] = [
                'id' => $i,
                'title' => "Story Number {$i}",
                'url' => "https://example.com/story/{$i}",
                'score' => rand(1, 100),
                'comment_count' => rand(0, 50),
                'tags' => ['auditing', 'risk', 'jobs'],
                'description' => str_repeat("This is story {$i}. ", 10)
            ];
        }
        
        $cached = $this->syncService->cacheStories($largeStorySet);
        $this->assertTrue($cached);
        
        // Test retrieval with limit
        $limited = $this->syncService->getCachedStories(50);
        $this->assertCount(50, $limited);
        
        // Test full retrieval (default limit)
        $full = $this->syncService->getCachedStories();
        $this->assertCount(50, $full); // Default limit is 50
        
        // Test retrieval without limit
        $unlimited = $this->syncService->getCachedStories(1000);
        $this->assertCount(1000, $unlimited);
    }
    
    public function testConcurrentDataAccess(): void
    {
        // Test that data persists across service instances
        $service1 = new OfflineSyncService();
        
        // Store data from first service
        $data1 = ['source' => 'service1', 'value' => 100];
        $result = $service1->storeOfflineData('concurrent_test', $data1);
        $this->assertTrue($result);
        
        // Create second service and read data
        $service2 = new OfflineSyncService();
        $retrieved = $service2->getOfflineData('concurrent_test');
        $this->assertNotNull($retrieved);
        $this->assertEquals($data1, $retrieved);
        
        // Update data from second service
        $data2 = ['source' => 'service2', 'value' => 200];
        $updateResult = $service2->storeOfflineData('concurrent_test', $data2);
        $this->assertTrue($updateResult);
        
        // Verify update is visible to new instance
        $service3 = new OfflineSyncService();
        $updated = $service3->getOfflineData('concurrent_test');
        $this->assertNotNull($updated);
        $this->assertEquals($data2, $updated);
    }
    
    public function testOfflineActionTypes(): void
    {
        // Create a fresh service to avoid actions from previous tests
        $freshService = new OfflineSyncService();
        
        // Test all supported offline action types
        $actionTypes = [
            'create_comment' => [
                'story_id' => 1,
                'comment' => 'Test comment',
                'parent_comment_id' => null
            ],
            'vote_story' => [
                'story_id' => 1,
                'vote' => 'up'
            ],
            'vote_comment' => [
                'comment_id' => 1,
                'vote' => 'down'
            ],
            'flag_comment' => [
                'comment_id' => 2,
                'reason' => 'Inappropriate'
            ],
            'create_story' => [
                'title' => 'Test Story',
                'url' => 'https://example.com/test',
                'description' => 'Test description'
            ]
        ];
        
        foreach ($actionTypes as $type => $data) {
            $queued = $freshService->queueAction($type, $data, 123);
            $this->assertTrue($queued, "Failed to queue action type: {$type}");
        }
        
        $stats = $freshService->getSyncStats();
        $this->assertEquals(count($actionTypes), $stats['total_actions']);
    }
}