<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use DI\Container;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\RateLimitService;
use App\Services\CacheService;
use App\Services\LoggerService;

class AuthApiTest extends TestCase
{
    private App $app;
    private Container $container;
    
    protected function setUp(): void
    {
        $this->container = new Container();
        
        // Mock services
        $this->container->set(AuthService::class, $this->createMock(AuthService::class));
        $this->container->set(JwtService::class, new JwtService());
        $this->container->set(RateLimitService::class, new RateLimitService(
            new CacheService(),
            new LoggerService()
        ));
        
        $this->app = new App(new ResponseFactory(), $this->container);
        
        // Load routes - simplified for testing
        $this->app->post('/api/v1/auth/login', function ($request, $response) {
            $controller = new \App\Controllers\Api\AuthApiController(
                $this->container->get(AuthService::class),
                $this->container->get(JwtService::class),
                $this->container->get(RateLimitService::class)
            );
            return $controller->login($request, $response);
        });
        
        $this->app->post('/api/v1/auth/register', function ($request, $response) {
            $controller = new \App\Controllers\Api\AuthApiController(
                $this->container->get(AuthService::class),
                $this->container->get(JwtService::class),
                $this->container->get(RateLimitService::class)
            );
            return $controller->register($request, $response);
        });
        
        $this->app->get('/api/v1/auth/scopes', function ($request, $response) {
            $controller = new \App\Controllers\Api\AuthApiController(
                $this->container->get(AuthService::class),
                $this->container->get(JwtService::class),
                $this->container->get(RateLimitService::class)
            );
            return $controller->getAvailableScopes($request, $response);
        });
    }
    
    public function testLoginWithValidCredentials(): void
    {
        // Mock successful authentication
        $authService = $this->container->get(AuthService::class);
        $authService->method('authenticate')
            ->willReturn([
                'id' => 123,
                'username' => 'testuser',
                'email' => 'test@example.com'
            ]);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/login');
        $request = $request->withParsedBody([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Login successful', $body['message']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('refresh_token', $body['data']);
        $this->assertArrayHasKey('user', $body['data']);
    }
    
    public function testLoginWithInvalidCredentials(): void
    {
        // Mock failed authentication
        $authService = $this->container->get(AuthService::class);
        $authService->method('authenticate')
            ->willReturn(null);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/login');
        $request = $request->withParsedBody([
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Invalid credentials', $body['message']);
        $this->assertEquals('INVALID_CREDENTIALS', $body['code']);
    }
    
    public function testLoginWithMissingFields(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/login');
        $request = $request->withParsedBody([
            'email' => 'test@example.com'
            // Missing password
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Missing required fields', $body['message']);
        $this->assertEquals('VALIDATION_ERROR', $body['code']);
        $this->assertContains('password', $body['errors']);
    }
    
    public function testRegisterWithValidData(): void
    {
        // Mock successful user creation
        $authService = $this->container->get(AuthService::class);
        $authService->method('createUser')
            ->willReturn(123);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/register');
        $request = $request->withParsedBody([
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Registration successful', $body['message']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('user', $body['data']);
    }
    
    public function testRegisterWithInvalidEmail(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/register');
        $request = $request->withParsedBody([
            'username' => 'newuser',
            'email' => 'invalid-email',
            'password' => 'password123'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertEquals('Validation failed', $body['message']);
        $this->assertContains('Invalid email address', $body['errors']);
    }
    
    public function testRegisterWithShortPassword(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/auth/register');
        $request = $request->withParsedBody([
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => '123'
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertFalse($body['success']);
        $this->assertContains('Password must be at least 8 characters long', $body['errors']);
    }
    
    public function testGetAvailableScopes(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/scopes');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string) $response->getBody(), true);
        
        $this->assertTrue($body['success']);
        $this->assertEquals('Available scopes retrieved', $body['message']);
        $this->assertArrayHasKey('scopes', $body['data']);
        $this->assertIsArray($body['data']['scopes']);
        $this->assertArrayHasKey('read', $body['data']['scopes']);
        $this->assertArrayHasKey('write', $body['data']['scopes']);
        $this->assertArrayHasKey('all', $body['data']['scopes']);
    }
    
    public function testResponseFormat(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/scopes');
        
        $response = $this->app->handle($request);
        
        $body = json_decode((string) $response->getBody(), true);
        
        // Verify standard API response format
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('timestamp', $body);
        $this->assertArrayHasKey('version', $body);
        
        $this->assertIsBool($body['success']);
        $this->assertIsString($body['message']);
        $this->assertIsArray($body['data']);
        $this->assertEquals('v1', $body['version']);
        
        // Verify timestamp format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $body['timestamp']);
    }
    
    public function testCorsHeaders(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/auth/scopes');
        
        $response = $this->app->handle($request);
        
        // Note: CORS headers would be added by middleware in the full app
        // This test verifies the basic structure is in place
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('v1', $response->getHeaderLine('API-Version'));
    }
}