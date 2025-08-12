<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;
    
    protected function setUp(): void
    {
        $this->jwtService = new JwtService();
    }
    
    public function testGenerateToken(): void
    {
        $payload = [
            'user_id' => 123,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];
        
        $token = $this->jwtService->generateToken($payload);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertCount(3, explode('.', $token)); // JWT has 3 parts
    }
    
    public function testVerifyToken(): void
    {
        $payload = [
            'user_id' => 123,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];
        
        $token = $this->jwtService->generateToken($payload);
        $verified = $this->jwtService->verifyToken($token);
        
        $this->assertIsArray($verified);
        $this->assertEquals(123, $verified['user_id']);
        $this->assertEquals('testuser', $verified['username']);
        $this->assertEquals('test@example.com', $verified['email']);
    }
    
    public function testVerifyInvalidToken(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid token');
        
        $this->jwtService->verifyToken('invalid.token.here');
    }
    
    public function testGenerateRefreshToken(): void
    {
        $payload = ['user_id' => 123];
        $refreshToken = $this->jwtService->generateRefreshToken($payload);
        
        $this->assertIsString($refreshToken);
        $this->assertNotEmpty($refreshToken);
    }
    
    public function testVerifyRefreshToken(): void
    {
        $payload = ['user_id' => 123];
        $refreshToken = $this->jwtService->generateRefreshToken($payload);
        
        $verified = $this->jwtService->verifyRefreshToken($refreshToken);
        
        $this->assertIsArray($verified);
        $this->assertEquals(123, $verified['user_id']);
    }
    
    public function testRefreshToken(): void
    {
        $payload = ['user_id' => 123];
        $refreshToken = $this->jwtService->generateRefreshToken($payload);
        
        $tokens = $this->jwtService->refreshToken($refreshToken);
        
        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        
        $this->assertEquals('Bearer', $tokens['token_type']);
        $this->assertIsString($tokens['access_token']);
        $this->assertIsString($tokens['refresh_token']);
    }
    
    public function testGetTokenPayload(): void
    {
        $payload = [
            'user_id' => 123,
            'username' => 'testuser'
        ];
        
        $token = $this->jwtService->generateToken($payload);
        $tokenPayload = $this->jwtService->getTokenPayload($token);
        
        $this->assertIsArray($tokenPayload);
        $this->assertArrayHasKey('data', $tokenPayload);
        $this->assertArrayHasKey('iat', $tokenPayload);
        $this->assertArrayHasKey('exp', $tokenPayload);
        $this->assertArrayHasKey('iss', $tokenPayload);
        
        $this->assertEquals(123, $tokenPayload['data']['user_id']);
        $this->assertEquals('testuser', $tokenPayload['data']['username']);
    }
    
    public function testIsTokenExpired(): void
    {
        $payload = ['user_id' => 123];
        $token = $this->jwtService->generateToken($payload);
        
        // Fresh token should not be expired
        $this->assertFalse($this->jwtService->isTokenExpired($token));
        
        // Invalid token should be considered expired
        $this->assertTrue($this->jwtService->isTokenExpired('invalid.token.here'));
    }
    
    public function testCreateApiKey(): void
    {
        $userId = 123;
        $name = 'Test API Key';
        $scopes = ['read', 'write'];
        
        $apiKey = $this->jwtService->createApiKey($userId, $name, $scopes);
        
        $this->assertIsString($apiKey);
        $this->assertNotEmpty($apiKey);
    }
    
    public function testVerifyApiKey(): void
    {
        $userId = 123;
        $name = 'Test API Key';
        $scopes = ['read', 'write'];
        
        $apiKey = $this->jwtService->createApiKey($userId, $name, $scopes);
        $verified = $this->jwtService->verifyApiKey($apiKey);
        
        $this->assertIsArray($verified);
        $this->assertEquals($userId, $verified['user_id']);
        $this->assertEquals($name, $verified['name']);
        $this->assertEquals($scopes, $verified['scopes']);
        $this->assertTrue($verified['api_key']);
    }
    
    public function testHasScope(): void
    {
        $tokenData = [
            'user_id' => 123,
            'scopes' => ['read', 'stories:write']
        ];
        
        // Should have required scope
        $this->assertTrue($this->jwtService->hasScope($tokenData, 'read'));
        $this->assertTrue($this->jwtService->hasScope($tokenData, 'stories:write'));
        
        // Should not have scope not in list
        $this->assertFalse($this->jwtService->hasScope($tokenData, 'admin'));
        
        // Token with no scopes should allow all operations
        $noScopesToken = ['user_id' => 123];
        $this->assertTrue($this->jwtService->hasScope($noScopesToken, 'admin'));
        
        // Token with 'all' scope should allow everything
        $allScopesToken = ['user_id' => 123, 'scopes' => ['all']];
        $this->assertTrue($this->jwtService->hasScope($allScopesToken, 'admin'));
    }
    
    public function testGetAvailableScopes(): void
    {
        $scopes = $this->jwtService->getAvailableScopes();
        
        $this->assertIsArray($scopes);
        $this->assertArrayHasKey('read', $scopes);
        $this->assertArrayHasKey('write', $scopes);
        $this->assertArrayHasKey('delete', $scopes);
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
    
    public function testGetTokenExpirationTime(): void
    {
        $expiration = $this->jwtService->getTokenExpirationTime();
        
        $this->assertIsInt($expiration);
        $this->assertGreaterThan(0, $expiration);
    }
    
    public function testGetRefreshTokenExpirationTime(): void
    {
        $expiration = $this->jwtService->getRefreshTokenExpirationTime();
        
        $this->assertIsInt($expiration);
        $this->assertGreaterThan(0, $expiration);
        $this->assertGreaterThan($this->jwtService->getTokenExpirationTime(), $expiration);
    }
}