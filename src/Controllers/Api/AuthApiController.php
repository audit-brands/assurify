<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\RateLimitService;

class AuthApiController extends BaseApiController
{
    private AuthService $authService;
    private JwtService $jwtService;
    private RateLimitService $rateLimitService;
    
    public function __construct(
        AuthService $authService,
        JwtService $jwtService,
        RateLimitService $rateLimitService
    ) {
        $this->authService = $authService;
        $this->jwtService = $jwtService;
        $this->rateLimitService = $rateLimitService;
    }
    
    public function login(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validate required fields
            $missing = $this->validateRequiredFields($data, ['email', 'password']);
            if (!empty($missing)) {
                return $this->errorResponse(
                    $response,
                    'Missing required fields',
                    400,
                    $missing,
                    'VALIDATION_ERROR'
                );
            }
            
            // Check rate limiting
            $email = $data['email'];
            if (!$this->rateLimitService->checkLoginAttempts($email)) {
                return $this->errorResponse(
                    $response,
                    'Too many login attempts. Please try again later.',
                    429,
                    [],
                    'RATE_LIMIT_EXCEEDED'
                );
            }
            
            // Attempt authentication
            $user = $this->authService->authenticate($email, $data['password']);
            
            if (!$user) {
                return $this->errorResponse(
                    $response,
                    'Invalid credentials',
                    401,
                    [],
                    'INVALID_CREDENTIALS'
                );
            }
            
            // Generate tokens
            $tokenPayload = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin'] ?? false,
                'is_moderator' => $user['is_moderator'] ?? false
            ];
            
            $accessToken = $this->jwtService->generateToken($tokenPayload);
            $refreshToken = $this->jwtService->generateRefreshToken($tokenPayload);
            
            $responseData = [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenExpirationTime(),
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'created_at' => $user['created_at'] ?? null
                ]
            ];
            
            return $this->successResponse($response, $responseData, 'Login successful');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Authentication failed',
                500,
                [],
                'AUTH_ERROR'
            );
        }
    }
    
    public function refresh(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (empty($data['refresh_token'])) {
                return $this->errorResponse(
                    $response,
                    'Refresh token is required',
                    400,
                    [],
                    'MISSING_REFRESH_TOKEN'
                );
            }
            
            $tokens = $this->jwtService->refreshToken($data['refresh_token']);
            
            return $this->successResponse($response, $tokens, 'Tokens refreshed successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                $e->getMessage(),
                401,
                [],
                'REFRESH_ERROR'
            );
        }
    }
    
    public function logout(Request $request, Response $response): Response
    {
        // In a production app, you might want to blacklist the token
        // For now, we'll just return a success response
        return $this->successResponse($response, [], 'Logged out successfully');
    }
    
    public function register(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validate required fields
            $missing = $this->validateRequiredFields($data, ['username', 'email', 'password']);
            if (!empty($missing)) {
                return $this->errorResponse(
                    $response,
                    'Missing required fields',
                    400,
                    $missing,
                    'VALIDATION_ERROR'
                );
            }
            
            // Check rate limiting
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!$this->rateLimitService->checkIpLimit('registration', 5, 3600)) {
                return $this->errorResponse(
                    $response,
                    'Too many registration attempts. Please try again later.',
                    429,
                    [],
                    'RATE_LIMIT_EXCEEDED'
                );
            }
            
            // Validate data
            $errors = [];
            
            if (strlen($data['password']) < 8) {
                $errors[] = 'Password must be at least 8 characters long';
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address';
            }
            
            if (!empty($errors)) {
                return $this->errorResponse(
                    $response,
                    'Validation failed',
                    400,
                    $errors,
                    'VALIDATION_ERROR'
                );
            }
            
            // Create user
            $userId = $this->authService->createUser([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$userId) {
                return $this->errorResponse(
                    $response,
                    'Registration failed',
                    400,
                    [],
                    'REGISTRATION_ERROR'
                );
            }
            
            // Generate tokens for the new user
            $tokenPayload = [
                'user_id' => $userId,
                'username' => $data['username'],
                'email' => $data['email'],
                'is_admin' => false,
                'is_moderator' => false
            ];
            
            $accessToken = $this->jwtService->generateToken($tokenPayload);
            $refreshToken = $this->jwtService->generateRefreshToken($tokenPayload);
            
            $responseData = [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenExpirationTime(),
                'user' => [
                    'id' => $userId,
                    'username' => $data['username'],
                    'email' => $data['email']
                ]
            ];
            
            return $this->successResponse($response, $responseData, 'Registration successful', 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Registration failed',
                500,
                [],
                'REGISTRATION_ERROR'
            );
        }
    }
    
    public function me(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->getUserFromToken($request);
        
        return $this->successResponse($response, ['user' => $user], 'User information retrieved');
    }
    
    public function createApiKey(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $data = $this->getRequestData($request);
            
            // Validate required fields
            $missing = $this->validateRequiredFields($data, ['name']);
            if (!empty($missing)) {
                return $this->errorResponse(
                    $response,
                    'API key name is required',
                    400,
                    $missing,
                    'VALIDATION_ERROR'
                );
            }
            
            $scopes = $data['scopes'] ?? [];
            $apiKey = $this->jwtService->createApiKey($user['user_id'], $data['name'], $scopes);
            
            $responseData = [
                'api_key' => $apiKey,
                'name' => $data['name'],
                'scopes' => $scopes,
                'created_at' => date('c')
            ];
            
            return $this->successResponse($response, $responseData, 'API key created successfully', 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to create API key',
                500,
                [],
                'API_KEY_ERROR'
            );
        }
    }
    
    public function getAvailableScopes(Request $request, Response $response): Response
    {
        $scopes = $this->jwtService->getAvailableScopes();
        
        return $this->successResponse($response, ['scopes' => $scopes], 'Available scopes retrieved');
    }
}