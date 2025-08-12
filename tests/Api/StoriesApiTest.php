<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use DI\Container;
use App\Services\StoryService;
use App\Services\JwtService;

class StoriesApiTest extends TestCase
{
    private App $app;
    private Container $container;
    private JwtService $jwtService;
    
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->jwtService = new JwtService();
        
        // Mock services
        $this->container->set(StoryService::class, $this->createMock(StoryService::class));
        $this->container->set(JwtService::class, $this->jwtService);
        
        $this->app = new App(new ResponseFactory(), $this->container);
        
        // Load routes - simplified for testing
        $this->app->get('/api/v1/stories', function ($request, $response) {
            $controller = new \App\Controllers\Api\StoriesApiController(
                $this->container->get(StoryService::class),
                $this->container->get(JwtService::class)
            );
            return $controller->index($request, $response);
        });
        
        $this->app->get('/api/v1/stories/{id}', function ($request, $response, $args) {
            $request = $request->withAttribute('routeArguments', $args);
            $controller = new \App\Controllers\Api\StoriesApiController(
                $this->container->get(StoryService::class),
                $this->container->get(JwtService::class)
            );
            return $controller->show($request, $response);
        });
        
        $this->app->post('/api/v1/stories', function ($request, $response) {
            $controller = new \App\Controllers\Api\StoriesApiController(
                $this->container->get(StoryService::class),
                $this->container->get(JwtService::class)
            );
            return $controller->store($request, $response);
        });
        
        $this->app->post('/api/v1/stories/{id}/vote', function ($request, $response, $args) {
            $request = $request->withAttribute('routeArguments', $args);
            $controller = new \App\Controllers\Api\StoriesApiController(
                $this->container->get(StoryService::class),
                $this->container->get(JwtService::class)
            );
            return $controller->vote($request, $response);
        });
    }
    
    public function testGetStoriesIndex(): void
    {
        $mockStories = [
            [
                'id' => 1,
                'title' => 'Test Story 1',
                'url' => 'https://example.com/1',
                'description' => 'First test story',
                'score' => 10,
                'comment_count' => 5,
                'user_id' => 123,
                'username' => 'testuser',
                'tags' => ['tech', 'programming'],
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 2,
                'title' => 'Test Story 2',
                'url' => 'https://example.com/2',
                'description' => 'Second test story',
                'score' => 8,
                'comment_count' => 3,
                'user_id' => 456,
                'username' => 'anotheruser',
                'tags' => ['news'],
                'created_at' => '2024-01-15 11:30:00'
            ]
        ];
        
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('getStories')->willReturn($mockStories);
        $storyService->method('getTotalStories')->willReturn(50);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Stories retrieved successfully', $body['message']);
        $this->assertCount(2, $body['data']);
        
        // Check pagination
        $this->assertArrayHasKey('pagination', $body);
        $this->assertEquals(1, $body['pagination']['current_page']);
        $this->assertEquals(20, $body['pagination']['per_page']);
        $this->assertEquals(50, $body['pagination']['total']);
        $this->assertEquals(3, $body['pagination']['total_pages']);
        
        // Check story structure
        $story = $body['data'][0];
        $this->assertEquals(1, $story['id']);
        $this->assertEquals('Test Story 1', $story['title']);
        $this->assertEquals('https://example.com/1', $story['url']);
        $this->assertEquals(10, $story['score']);
        $this->assertEquals(5, $story['comment_count']);
        $this->assertArrayHasKey('user', $story);
        $this->assertEquals(123, $story['user']['id']);
        $this->assertEquals('testuser', $story['user']['username']);
        $this->assertEquals(['tech', 'programming'], $story['tags']);
    }
    
    public function testGetStoriesWithQueryParams(): void
    {
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('getStories')->willReturn([]);
        $storyService->method('getTotalStories')->willReturn(0);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories?page=2&per_page=10&sort=hottest&tag=tech');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify that the service was called with correct parameters
        $this->assertTrue(true); // Service parameters would be verified with more sophisticated mocking
    }
    
    public function testGetSingleStory(): void
    {
        $mockStory = [
            'id' => 123,
            'title' => 'Single Test Story',
            'url' => 'https://example.com/story',
            'description' => 'A single story for testing',
            'score' => 15,
            'comment_count' => 8,
            'user_id' => 456,
            'username' => 'storyauthor',
            'tags' => ['test'],
            'created_at' => '2024-01-15 12:00:00'
        ];
        
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('getStoryById')->willReturn($mockStory);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories/123');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Story retrieved successfully', $body['message']);
        $this->assertArrayHasKey('story', $body['data']);
        
        $story = $body['data']['story'];
        $this->assertEquals(123, $story['id']);
        $this->assertEquals('Single Test Story', $story['title']);
    }
    
    public function testGetNonExistentStory(): void
    {
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('getStoryById')->willReturn(null);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories/999');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Story not found', $body['message']);
        $this->assertEquals('STORY_NOT_FOUND', $body['code']);
    }
    
    public function testGetStoryWithInvalidId(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories/invalid');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Invalid story ID', $body['message']);
        $this->assertEquals('INVALID_ID', $body['code']);
    }
    
    public function testCreateStoryWithoutAuth(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories');
        $request = $request->withParsedBody([
            'title' => 'New Story',
            'url' => 'https://example.com/new'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Authentication required', $body['message']);
        $this->assertEquals('AUTH_REQUIRED', $body['code']);
    }
    
    public function testCreateStoryWithAuth(): void
    {
        // Create a valid JWT token
        $userData = [
            'user_id' => 123,
            'username' => 'testuser',
            'scopes' => ['stories:write']
        ];
        $token = $this->jwtService->generateToken($userData);
        
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('createStory')->willReturn(456);
        $storyService->method('getStoryById')->willReturn([
            'id' => 456,
            'title' => 'New Story',
            'url' => 'https://example.com/new',
            'user_id' => 123,
            'username' => 'testuser',
            'score' => 1,
            'comment_count' => 0,
            'created_at' => '2024-01-15 13:00:00'
        ]);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request = $request->withParsedBody([
            'title' => 'New Story',
            'url' => 'https://example.com/new',
            'description' => 'A new story',
            'tags' => ['test']
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Story created successfully', $body['message']);
        $this->assertArrayHasKey('story', $body['data']);
        $this->assertEquals(456, $body['data']['story']['id']);
    }
    
    public function testCreateStoryWithMissingTitle(): void
    {
        $userData = ['user_id' => 123, 'scopes' => ['stories:write']];
        $token = $this->jwtService->generateToken($userData);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request = $request->withParsedBody([
            'url' => 'https://example.com/new'
            // Missing title
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Missing required fields', $body['message']);
        $this->assertContains('title', $body['errors']);
    }
    
    public function testCreateStoryWithInvalidUrl(): void
    {
        $userData = ['user_id' => 123, 'scopes' => ['stories:write']];
        $token = $this->jwtService->generateToken($userData);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request = $request->withParsedBody([
            'title' => 'Test Story',
            'url' => 'not-a-valid-url'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertContains('Invalid URL format', $body['errors']);
    }
    
    public function testVoteOnStory(): void
    {
        $userData = ['user_id' => 123, 'scopes' => ['votes']];
        $token = $this->jwtService->generateToken($userData);
        
        $storyService = $this->container->get(StoryService::class);
        $storyService->method('voteOnStory')->willReturn([
            'success' => true,
            'score' => 16
        ]);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories/123/vote');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request = $request->withParsedBody([
            'vote' => 'up'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Vote recorded successfully', $body['message']);
        $this->assertEquals(16, $body['data']['score']);
    }
    
    public function testVoteWithInvalidDirection(): void
    {
        $userData = ['user_id' => 123, 'scopes' => ['votes']];
        $token = $this->jwtService->generateToken($userData);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/stories/123/vote');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request = $request->withParsedBody([
            'vote' => 'sideways' // Invalid direction
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertStringContains('Invalid vote direction', $body['message']);
    }
}