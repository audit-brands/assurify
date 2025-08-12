<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $expirationTime;
    private int $refreshExpirationTime;
    private string $issuer;
    
    public function __construct()
    {
        $this->secretKey = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
        $this->algorithm = 'HS256';
        $this->expirationTime = (int) ($_ENV['JWT_EXPIRATION'] ?? 3600); // 1 hour
        $this->refreshExpirationTime = (int) ($_ENV['JWT_REFRESH_EXPIRATION'] ?? 604800); // 7 days
        $this->issuer = $_ENV['JWT_ISSUER'] ?? 'lobsters-api';
    }
    
    public function generateToken(array $payload): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->expirationTime;
        
        $token = [
            'iss' => $this->issuer,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'data' => $payload
        ];
        
        return JWT::encode($token, $this->secretKey, $this->algorithm);
    }
    
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->refreshExpirationTime;
        
        $token = [
            'iss' => $this->issuer,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'type' => 'refresh',
            'data' => $payload
        ];
        
        return JWT::encode($token, $this->secretKey, $this->algorithm);
    }
    
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array) $decoded->data;
        } catch (ExpiredException $e) {
            throw new \Exception('Token has expired', 401);
        } catch (SignatureInvalidException $e) {
            throw new \Exception('Invalid token signature', 401);
        } catch (\Exception $e) {
            throw new \Exception('Invalid token', 401);
        }
    }
    
    public function verifyRefreshToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            
            if (!isset($decoded->type) || $decoded->type !== 'refresh') {
                throw new \Exception('Invalid refresh token', 401);
            }
            
            return (array) $decoded->data;
        } catch (ExpiredException $e) {
            throw new \Exception('Refresh token has expired', 401);
        } catch (SignatureInvalidException $e) {
            throw new \Exception('Invalid refresh token signature', 401);
        } catch (\Exception $e) {
            throw new \Exception('Invalid refresh token', 401);
        }
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $payload = $this->verifyRefreshToken($refreshToken);
        
        $newAccessToken = $this->generateToken($payload);
        $newRefreshToken = $this->generateRefreshToken($payload);
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expirationTime
        ];
    }
    
    public function getTokenPayload(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return [
                'data' => (array) $decoded->data,
                'iat' => $decoded->iat,
                'exp' => $decoded->exp,
                'iss' => $decoded->iss
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function isTokenExpired(string $token): bool
    {
        try {
            $payload = $this->getTokenPayload($token);
            return !$payload || $payload['exp'] < time();
        } catch (\Exception $e) {
            return true;
        }
    }
    
    public function getTokenExpirationTime(): int
    {
        return $this->expirationTime;
    }
    
    public function getRefreshTokenExpirationTime(): int
    {
        return $this->refreshExpirationTime;
    }
    
    public function createApiKey(int $userId, string $name, array $scopes = []): string
    {
        $payload = [
            'user_id' => $userId,
            'api_key' => true,
            'name' => $name,
            'scopes' => $scopes,
            'created_at' => time()
        ];
        
        // API keys don't expire by default
        $issuedAt = time();
        $token = [
            'iss' => $this->issuer,
            'iat' => $issuedAt,
            'type' => 'api_key',
            'data' => $payload
        ];
        
        return JWT::encode($token, $this->secretKey, $this->algorithm);
    }
    
    public function verifyApiKey(string $apiKey): ?array
    {
        try {
            $decoded = JWT::decode($apiKey, new Key($this->secretKey, $this->algorithm));
            
            if (!isset($decoded->type) || $decoded->type !== 'api_key') {
                throw new \Exception('Invalid API key', 401);
            }
            
            return (array) $decoded->data;
        } catch (\Exception $e) {
            throw new \Exception('Invalid API key', 401);
        }
    }
    
    public function hasScope(array $tokenData, string $requiredScope): bool
    {
        $scopes = $tokenData['scopes'] ?? [];
        
        // If no scopes are defined, allow all operations
        if (empty($scopes)) {
            return true;
        }
        
        // Check if user has the required scope or 'all' scope
        return in_array($requiredScope, $scopes) || in_array('all', $scopes);
    }
    
    public function getAvailableScopes(): array
    {
        return [
            'read' => 'Read access to resources',
            'write' => 'Write access to resources',
            'delete' => 'Delete access to resources',
            'admin' => 'Administrative access',
            'stories:read' => 'Read stories',
            'stories:write' => 'Create and edit stories',
            'comments:read' => 'Read comments',
            'comments:write' => 'Create and edit comments',
            'votes' => 'Vote on stories and comments',
            'users:read' => 'Read user information',
            'users:write' => 'Edit user information',
            'moderation' => 'Moderation capabilities',
            'all' => 'Full access to all resources'
        ];
    }
}