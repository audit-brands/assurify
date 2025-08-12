<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;

abstract class BaseApiController
{
    protected const API_VERSION = 'v1';
    
    protected function jsonResponse(
        Response $response,
        array $data,
        int $status = 200,
        array $headers = []
    ): Response {
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        $response = $response->withStatus($status);
        $response->getBody()->write($payload);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('API-Version', self::API_VERSION);
        
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        
        return $response;
    }
    
    protected function successResponse(
        Response $response,
        array $data = [],
        string $message = 'Success',
        int $status = 200
    ): Response {
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
            'version' => self::API_VERSION
        ], $status);
    }
    
    protected function errorResponse(
        Response $response,
        string $message = 'An error occurred',
        int $status = 400,
        array $errors = [],
        ?string $code = null
    ): Response {
        return $this->jsonResponse($response, [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
            'timestamp' => date('c'),
            'version' => self::API_VERSION
        ], $status);
    }
    
    protected function paginatedResponse(
        Response $response,
        array $data,
        int $total,
        int $page = 1,
        int $perPage = 20,
        string $message = 'Success'
    ): Response {
        $totalPages = (int) ceil($total / $perPage);
        
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'has_prev_page' => $page > 1
            ],
            'timestamp' => date('c'),
            'version' => self::API_VERSION
        ]);
    }
    
    protected function validateRequiredFields(array $data, array $required): array
    {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    protected function getRequestData(Request $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        
        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            return json_decode($body, true) ?? [];
        }
        
        return $request->getParsedBody() ?? [];
    }
    
    protected function getQueryParams(Request $request): array
    {
        return $request->getQueryParams();
    }
    
    protected function getPathParam(Request $request, string $name): ?string
    {
        $args = $request->getAttribute('routeArguments', []);
        return $args[$name] ?? null;
    }
    
    protected function getUserFromToken(Request $request): ?array
    {
        // Check if user data is already attached by middleware
        $user = $request->getAttribute('user');
        if ($user) {
            return $user;
        }
        
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        
        // If no middleware attached user, return null for now
        // In production, this would be handled by the auth middleware
        return null;
    }
    
    protected function requireAuth(Request $request, Response $response): ?Response
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return $this->errorResponse(
                $response,
                'Authentication required',
                401,
                [],
                'AUTH_REQUIRED'
            );
        }
        
        return null;
    }
    
    protected function cors(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}