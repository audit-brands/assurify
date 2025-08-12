<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Controllers\Api\SyncController;
use App\Services\OfflineSyncService;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class SyncControllerTest extends TestCase
{
    private SyncController $controller;
    private OfflineSyncService $syncService;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;
    
    protected function setUp(): void
    {
        $this->syncService = new OfflineSyncService();
        $this->controller = new SyncController($this->syncService);
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
    }
    
    public function testQueueActionWithValidData(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/queue');
        $request = $request->withParsedBody([
            'type' => 'create_comment',
            'data' => [
                'story_id' => 123,
                'comment' => 'Test comment'
            ]
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->queueAction($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = (string) $result->getBody();
        $this->assertStringContainsString('success', $body);
        $this->assertStringContainsString('queued', $body);
    }
    
    public function testQueueActionWithMissingData(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/queue');
        $request = $request->withParsedBody([
            'type' => 'create_comment'
            // Missing 'data' field
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->queueAction($request, $response);
        
        $this->assertEquals(400, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Missing required fields', $body['error']);
    }
    
    public function testGetSyncStatus(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/api/v1/sync/status');
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->getSyncStatus($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('sync_status', $body['data']);
        $this->assertArrayHasKey('is_online', $body['data']);
    }
    
    public function testCacheDataWithValidInput(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/cache');
        $request = $request->withParsedBody([
            'key' => 'test_data',
            'data' => ['test' => 'value'],
            'ttl' => 1800
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->cacheData($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['cached']);
        $this->assertEquals('test_data', $body['data']['key']);
        $this->assertEquals(1800, $body['data']['ttl']);
    }
    
    public function testCacheDataWithMissingFields(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/cache');
        $request = $request->withParsedBody([
            'key' => 'test_data'
            // Missing 'data' field
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->cacheData($request, $response);
        
        $this->assertEquals(400, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Missing required fields', $body['error']);
    }
    
    public function testGetCachedDataWithValidKey(): void
    {
        // First cache some data
        $this->syncService->storeOfflineData('test_key', ['cached' => 'value']);
        
        $request = $this->requestFactory->createServerRequest('GET', '/api/v1/sync/cache/test_key');
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->getCachedData($request, $response, ['key' => 'test_key']);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(['cached' => 'value'], $body['data']['cached_data']);
        $this->assertEquals('test_key', $body['data']['key']);
    }
    
    public function testGetCachedDataWithInvalidKey(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/api/v1/sync/cache/nonexistent');
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->getCachedData($request, $response, ['key' => 'nonexistent']);
        
        $this->assertEquals(404, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('not found or expired', $body['error']);
    }
    
    public function testCacheStories(): void
    {
        $stories = [
            ['id' => 1, 'title' => 'Story 1'],
            ['id' => 2, 'title' => 'Story 2']
        ];
        
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/stories/cache');
        $request = $request->withParsedBody(['stories' => $stories]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->cacheStories($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['cached']);
        $this->assertEquals(2, $body['data']['story_count']);
    }
    
    public function testGetCachedStories(): void
    {
        // Cache some stories first
        $stories = [
            ['id' => 1, 'title' => 'Cached Story 1'],
            ['id' => 2, 'title' => 'Cached Story 2']
        ];
        $this->syncService->cacheStories($stories);
        
        $request = $this->requestFactory->createServerRequest('GET', '/api/v1/sync/stories/cached');
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->getCachedStories($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals($stories, $body['data']['stories']);
        $this->assertEquals(2, $body['data']['count']);
        $this->assertTrue($body['data']['is_cached']);
    }
    
    public function testCacheComments(): void
    {
        $storyId = 123;
        $comments = [
            ['id' => 1, 'comment' => 'Comment 1'],
            ['id' => 2, 'comment' => 'Comment 2']
        ];
        
        $request = $this->requestFactory->createServerRequest('POST', "/api/v1/sync/stories/{$storyId}/comments/cache");
        $request = $request->withParsedBody(['comments' => $comments]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->cacheComments($request, $response, ['storyId' => (string)$storyId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['cached']);
        $this->assertEquals($storyId, $body['data']['story_id']);
        $this->assertEquals(2, $body['data']['comment_count']);
    }
    
    public function testGetCachedComments(): void
    {
        $storyId = 123;
        $comments = [
            ['id' => 1, 'comment' => 'Cached Comment 1'],
            ['id' => 2, 'comment' => 'Cached Comment 2']
        ];
        
        // Cache comments first
        $this->syncService->cacheComments($storyId, $comments);
        
        $request = $this->requestFactory->createServerRequest('GET', "/api/v1/sync/stories/{$storyId}/comments/cached");
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->getCachedComments($request, $response, ['storyId' => (string)$storyId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals($comments, $body['data']['comments']);
        $this->assertEquals($storyId, $body['data']['story_id']);
        $this->assertEquals(2, $body['data']['count']);
        $this->assertTrue($body['data']['is_cached']);
    }
    
    public function testCleanupExpiredData(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/cleanup');
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->cleanupExpiredData($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('cleaned_items', $body['data']);
        $this->assertIsInt($body['data']['cleaned_items']);
    }
    
    public function testResolveConflictWithValidData(): void
    {
        $localData = ['id' => 1, 'title' => 'Local'];
        $serverData = ['id' => 1, 'title' => 'Server'];
        
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/resolve-conflict');
        $request = $request->withParsedBody([
            'local_data' => $localData,
            'server_data' => $serverData,
            'strategy' => 'server_wins'
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->resolveConflict($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals($serverData, $body['data']['resolved_data']);
        $this->assertEquals('server_wins', $body['data']['strategy_used']);
    }
    
    public function testResolveConflictWithMissingData(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/api/v1/sync/resolve-conflict');
        $request = $request->withParsedBody([
            'local_data' => ['id' => 1]
            // Missing 'server_data'
        ]);
        
        $response = $this->responseFactory->createResponse();
        
        $result = $this->controller->resolveConflict($request, $response);
        
        $this->assertEquals(400, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Missing required field', $body['error']);
    }
}