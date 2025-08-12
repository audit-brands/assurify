<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;
use App\Controllers\Api\BaseApiController;
use Slim\Psr7\Response;

class ApiBasicTest extends TestCase
{
    private JwtService $jwtService;
    
    protected function setUp(): void
    {
        $this->jwtService = new JwtService();
    }
    
    public function testJwtTokenGeneration(): void
    {
        $payload = [
            'user_id' => 123,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];
        
        $token = $this->jwtService->generateToken($payload);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Verify token can be decoded
        $decoded = $this->jwtService->verifyToken($token);
        $this->assertEquals(123, $decoded['user_id']);
        $this->assertEquals('testuser', $decoded['username']);
    }
    
    public function testJwtRefreshTokenFlow(): void
    {
        $payload = ['user_id' => 123];
        
        // Generate refresh token
        $refreshToken = $this->jwtService->generateRefreshToken($payload);
        $this->assertIsString($refreshToken);
        
        // Use refresh token to get new tokens
        $tokens = $this->jwtService->refreshToken($refreshToken);
        
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);
    }
    
    public function testApiKeyGeneration(): void
    {
        $userId = 123;
        $keyName = 'Test Key';
        $scopes = ['read', 'write'];
        
        $apiKey = $this->jwtService->createApiKey($userId, $keyName, $scopes);
        $this->assertIsString($apiKey);
        
        // Verify API key
        $keyData = $this->jwtService->verifyApiKey($apiKey);
        $this->assertEquals($userId, $keyData['user_id']);
        $this->assertEquals($keyName, $keyData['name']);
        $this->assertEquals($scopes, $keyData['scopes']);
    }
    
    public function testScopeValidation(): void
    {
        $tokenData = [
            'user_id' => 123,
            'scopes' => ['read', 'stories:write']
        ];
        
        // Should have required scope
        $this->assertTrue($this->jwtService->hasScope($tokenData, 'read'));
        $this->assertTrue($this->jwtService->hasScope($tokenData, 'stories:write'));
        
        // Should not have missing scope
        $this->assertFalse($this->jwtService->hasScope($tokenData, 'admin'));
        
        // Empty scopes should allow all
        $noScopesData = ['user_id' => 123];
        $this->assertTrue($this->jwtService->hasScope($noScopesData, 'admin'));
    }
    
    public function testBaseApiControllerResponses(): void
    {
        $controller = new class extends BaseApiController {
            public function testSuccessResponse(): Response
            {
                $response = new Response();
                return $this->successResponse($response, ['test' => 'data'], 'Test message');
            }
            
            public function testErrorResponse(): Response
            {
                $response = new Response();
                return $this->errorResponse($response, 'Error message', 400, ['field' => 'required'], 'ERROR_CODE');
            }
            
            public function testPaginatedResponse(): Response
            {
                $response = new Response();
                return $this->paginatedResponse($response, [1, 2, 3], 100, 2, 10, 'Paginated data');
            }
        };
        
        // Test success response
        $successResponse = $controller->testSuccessResponse();
        $successBody = json_decode((string) $successResponse->getBody(), true);
        
        $this->assertEquals(200, $successResponse->getStatusCode());
        $this->assertTrue($successBody['success']);
        $this->assertEquals('Test message', $successBody['message']);
        $this->assertEquals(['test' => 'data'], $successBody['data']);
        $this->assertEquals('v1', $successBody['version']);
        $this->assertArrayHasKey('timestamp', $successBody);
        
        // Test error response
        $errorResponse = $controller->testErrorResponse();
        $errorBody = json_decode((string) $errorResponse->getBody(), true);
        
        $this->assertEquals(400, $errorResponse->getStatusCode());
        $this->assertFalse($errorBody['success']);
        $this->assertEquals('Error message', $errorBody['message']);
        $this->assertEquals(['field' => 'required'], $errorBody['errors']);
        $this->assertEquals('ERROR_CODE', $errorBody['code']);
        
        // Test paginated response
        $paginatedResponse = $controller->testPaginatedResponse();
        $paginatedBody = json_decode((string) $paginatedResponse->getBody(), true);
        
        $this->assertEquals(200, $paginatedResponse->getStatusCode());
        $this->assertTrue($paginatedBody['success']);
        $this->assertEquals([1, 2, 3], $paginatedBody['data']);
        $this->assertArrayHasKey('pagination', $paginatedBody);
        $this->assertEquals(2, $paginatedBody['pagination']['current_page']);
        $this->assertEquals(10, $paginatedBody['pagination']['per_page']);
        $this->assertEquals(100, $paginatedBody['pagination']['total']);
        $this->assertEquals(10, $paginatedBody['pagination']['total_pages']);
        $this->assertTrue($paginatedBody['pagination']['has_next_page']);
        $this->assertTrue($paginatedBody['pagination']['has_prev_page']);
    }
    
    public function testFieldValidation(): void
    {
        $controller = new class extends BaseApiController {
            public function testValidation(array $data, array $required): array
            {
                return $this->validateRequiredFields($data, $required);
            }
        };
        
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => null
        ];
        
        $required = ['name', 'email', 'age', 'phone'];
        $missing = $controller->testValidation($data, $required);
        
        $this->assertContains('age', $missing);
        $this->assertContains('phone', $missing);
        $this->assertNotContains('name', $missing);
        $this->assertNotContains('email', $missing);
    }
    
    public function testTokenExpiration(): void
    {
        $payload = ['user_id' => 123];
        $token = $this->jwtService->generateToken($payload);
        
        // Fresh token should not be expired
        $this->assertFalse($this->jwtService->isTokenExpired($token));
        
        // Get token expiration time
        $expirationTime = $this->jwtService->getTokenExpirationTime();
        $this->assertIsInt($expirationTime);
        $this->assertGreaterThan(0, $expirationTime);
        
        // Refresh token expiration should be longer
        $refreshExpiration = $this->jwtService->getRefreshTokenExpirationTime();
        $this->assertGreaterThan($expirationTime, $refreshExpiration);
    }
    
    public function testAvailableScopes(): void
    {
        $scopes = $this->jwtService->getAvailableScopes();
        
        $this->assertIsArray($scopes);
        $this->assertArrayHasKey('read', $scopes);
        $this->assertArrayHasKey('write', $scopes);
        $this->assertArrayHasKey('stories:read', $scopes);
        $this->assertArrayHasKey('stories:write', $scopes);
        $this->assertArrayHasKey('votes', $scopes);
        $this->assertArrayHasKey('all', $scopes);
        
        // Each scope should have a description
        foreach ($scopes as $scope => $description) {
            $this->assertIsString($description);
            $this->assertNotEmpty($description);
        }
    }
}