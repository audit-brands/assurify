<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\JwtService;
use App\Services\RateLimitService;
use Slim\Psr7\Response as SlimResponse;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private RateLimitService $rateLimitService;
    
    public function __construct(JwtService $jwtService, RateLimitService $rateLimitService)
    {
        $this->jwtService = $jwtService;
        $this->rateLimitService = $rateLimitService;
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Handle CORS preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->corsResponse();
        }
        
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header is required');
        }
        
        // Check for Bearer token
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Invalid authorization format. Use Bearer token');
        }
        
        $token = substr($authHeader, 7);
        
        try {
            // Try to verify as JWT token first
            $userData = $this->jwtService->verifyToken($token);
            $request = $request->withAttribute('user', $userData);
            
            // Check rate limiting for authenticated users
            if (isset($userData['user_id'])) {
                if (!$this->rateLimitService->checkApiRequests('user_' . $userData['user_id'])) {
                    return $this->rateLimitResponse();
                }
            }
            
        } catch (\Exception $e) {
            // Try to verify as API key
            try {
                $apiKeyData = $this->jwtService->verifyApiKey($token);
                $request = $request->withAttribute('user', $apiKeyData);
                $request = $request->withAttribute('api_key', true);
                
                // Check rate limiting for API keys
                if (!$this->rateLimitService->checkApiRequests($token)) {
                    return $this->rateLimitResponse();
                }
                
            } catch (\Exception $e) {
                return $this->unauthorizedResponse('Invalid or expired token');
            }
        }
        
        $response = $handler->handle($request);
        
        // Add CORS headers to all responses
        return $this->addCorsHeaders($response);
    }
    
    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response = $response->withStatus(401);
        $response = $response->withHeader('Content-Type', 'application/json');
        
        $body = json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED',
            'timestamp' => date('c')
        ]);
        
        $response->getBody()->write($body);
        
        return $this->addCorsHeaders($response);
    }
    
    private function rateLimitResponse(): Response
    {
        $response = new SlimResponse();
        $response = $response->withStatus(429);
        $response = $response->withHeader('Content-Type', 'application/json');
        
        $body = json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded. Please slow down your requests.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'timestamp' => date('c')
        ]);
        
        $response->getBody()->write($body);
        
        return $this->addCorsHeaders($response);
    }
    
    private function corsResponse(): Response
    {
        $response = new SlimResponse();
        $response = $response->withStatus(200);
        
        return $this->addCorsHeaders($response);
    }
    
    private function addCorsHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}