<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * CSRF Protection Middleware
 * 
 * Protects against Cross-Site Request Forgery attacks by:
 * - Generating CSRF tokens for forms
 * - Validating CSRF tokens on state-changing requests
 * - Implementing SameSite cookie protection
 * - Supporting AJAX requests with header-based tokens
 */
class CsrfProtectionMiddleware implements MiddlewareInterface
{
    private const TOKEN_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';
    private const COOKIE_NAME = 'csrf_cookie';
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 hour

    private array $exemptRoutes = [
        '/api/webhook', // Webhook endpoints
        '/api/public',  // Public API endpoints
    ];

    private array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();

        // Skip CSRF protection for safe methods
        if (in_array($method, $this->safeMethods)) {
            return $this->addTokenToResponse($handler->handle($request));
        }

        // Skip CSRF protection for exempt routes
        if ($this->isExemptRoute($uri)) {
            return $handler->handle($request);
        }

        // Validate CSRF token for state-changing requests
        if (!$this->validateCsrfToken($request)) {
            return $this->createCsrfErrorResponse();
        }

        return $this->addTokenToResponse($handler->handle($request));
    }

    /**
     * Generate a new CSRF token
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Get current CSRF token from session
     */
    public function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_NAME]) || $this->isTokenExpired()) {
            $_SESSION[self::TOKEN_NAME] = $this->generateToken();
            $_SESSION[self::TOKEN_NAME . '_time'] = time();
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Validate CSRF token from request
     */
    private function validateCsrfToken(ServerRequestInterface $request): bool
    {
        $token = $this->extractTokenFromRequest($request);
        
        if (empty($token)) {
            return false;
        }

        $sessionToken = $this->getToken();
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Extract CSRF token from request (form data or headers)
     */
    private function extractTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // Try to get token from form data
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody[self::TOKEN_NAME])) {
            return $parsedBody[self::TOKEN_NAME];
        }

        // Try to get token from headers (for AJAX requests)
        $headerValues = $request->getHeader(self::HEADER_NAME);
        if (!empty($headerValues)) {
            return $headerValues[0];
        }

        // Try to get token from cookies (as fallback)
        $cookies = $request->getCookieParams();
        if (isset($cookies[self::COOKIE_NAME])) {
            return $cookies[self::COOKIE_NAME];
        }

        return null;
    }

    /**
     * Check if current token is expired
     */
    private function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            return true;
        }

        return (time() - $_SESSION[self::TOKEN_NAME . '_time']) > self::TOKEN_LIFETIME;
    }

    /**
     * Check if route is exempt from CSRF protection
     */
    private function isExemptRoute(string $uri): bool
    {
        foreach ($this->exemptRoutes as $exemptRoute) {
            if (strpos($uri, $exemptRoute) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add CSRF token to response (in cookie and meta tag)
     */
    private function addTokenToResponse(ResponseInterface $response): ResponseInterface
    {
        $token = $this->getToken();

        // Add token as HttpOnly cookie for AJAX requests
        $cookieValue = sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Strict; Secure=%s',
            self::COOKIE_NAME,
            $token,
            $this->isHttps() ? 'true' : 'false'
        );

        return $response->withHeader('Set-Cookie', $cookieValue);
    }

    /**
     * Create CSRF error response
     */
    private function createCsrfErrorResponse(): ResponseInterface
    {
        $response = new Response();
        
        $errorData = [
            'error' => 'CSRF token validation failed',
            'message' => 'Request rejected due to invalid or missing CSRF token',
            'code' => 'CSRF_TOKEN_INVALID'
        ];

        $response->getBody()->write(json_encode($errorData));
        
        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Check if connection is HTTPS
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Generate CSRF token input field for forms
     */
    public static function getTokenField(): string
    {
        $middleware = new self();
        $token = $middleware->getToken();
        
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::TOKEN_NAME,
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generate CSRF meta tag for AJAX requests
     */
    public static function getTokenMeta(): string
    {
        $middleware = new self();
        $token = $middleware->getToken();
        
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get token for JavaScript usage
     */
    public static function getTokenForJs(): string
    {
        $middleware = new self();
        return $middleware->getToken();
    }
}