<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Security Headers Middleware
 * 
 * Implements comprehensive security headers to protect against:
 * - XSS attacks (Content Security Policy)
 * - Clickjacking (X-Frame-Options)
 * - MIME sniffing (X-Content-Type-Options)
 * - Information disclosure (X-Powered-By removal)
 * - Man-in-the-middle attacks (HSTS)
 * - Referrer information leakage (Referrer-Policy)
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Apply all security headers
        $response = $this->addContentSecurityPolicy($response, $request);
        $response = $this->addFrameOptions($response);
        $response = $this->addContentTypeOptions($response);
        $response = $this->addXssProtection($response);
        $response = $this->addHsts($response, $request);
        $response = $this->addReferrerPolicy($response);
        $response = $this->addPermissionsPolicy($response);
        $response = $this->removePoweredByHeader($response);
        $response = $this->addCrossOriginHeaders($response);

        return $response;
    }

    /**
     * Add Content Security Policy header
     */
    private function addContentSecurityPolicy(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config['csp']['enabled']) {
            return $response;
        }

        $nonce = $this->generateNonce();
        $request = $request->withAttribute('csp_nonce', $nonce);

        $csp = $this->buildCspDirectives($nonce);

        return $response->withHeader('Content-Security-Policy', $csp);
    }

    /**
     * Build CSP directives
     */
    private function buildCspDirectives(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' " . implode(' ', $this->config['csp']['script_sources']),
            "style-src 'self' 'unsafe-inline' " . implode(' ', $this->config['csp']['style_sources']),
            "img-src 'self' data: blob: " . implode(' ', $this->config['csp']['img_sources']),
            "font-src 'self' " . implode(' ', $this->config['csp']['font_sources']),
            "connect-src 'self' " . implode(' ', $this->config['csp']['connect_sources']),
            "media-src 'self' " . implode(' ', $this->config['csp']['media_sources']),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors " . implode(' ', $this->config['csp']['frame_ancestors']),
            "upgrade-insecure-requests"
        ];

        if ($this->config['csp']['report_uri']) {
            $directives[] = "report-uri " . $this->config['csp']['report_uri'];
        }

        return implode('; ', $directives);
    }

    /**
     * Generate cryptographic nonce for CSP
     */
    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Add X-Frame-Options header
     */
    private function addFrameOptions(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['frame_options']['enabled']) {
            return $response;
        }

        return $response->withHeader('X-Frame-Options', $this->config['frame_options']['value']);
    }

    /**
     * Add X-Content-Type-Options header
     */
    private function addContentTypeOptions(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['content_type_options']['enabled']) {
            return $response;
        }

        return $response->withHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Add X-XSS-Protection header
     */
    private function addXssProtection(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['xss_protection']['enabled']) {
            return $response;
        }

        return $response->withHeader('X-XSS-Protection', $this->config['xss_protection']['value']);
    }

    /**
     * Add Strict-Transport-Security header
     */
    private function addHsts(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config['hsts']['enabled'] || !$this->isHttps($request)) {
            return $response;
        }

        $hstsValue = sprintf(
            'max-age=%d%s%s',
            $this->config['hsts']['max_age'],
            $this->config['hsts']['include_subdomains'] ? '; includeSubDomains' : '',
            $this->config['hsts']['preload'] ? '; preload' : ''
        );

        return $response->withHeader('Strict-Transport-Security', $hstsValue);
    }

    /**
     * Add Referrer-Policy header
     */
    private function addReferrerPolicy(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['referrer_policy']['enabled']) {
            return $response;
        }

        return $response->withHeader('Referrer-Policy', $this->config['referrer_policy']['value']);
    }

    /**
     * Add Permissions-Policy header (formerly Feature-Policy)
     */
    private function addPermissionsPolicy(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['permissions_policy']['enabled']) {
            return $response;
        }

        $policies = [];
        foreach ($this->config['permissions_policy']['directives'] as $directive => $allowlist) {
            if (empty($allowlist)) {
                $policies[] = "{$directive}=()";
            } elseif ($allowlist === ['*']) {
                $policies[] = "{$directive}=*";
            } else {
                $allowlistStr = implode(' ', array_map(function($origin) {
                    return $origin === 'self' ? '"self"' : $origin;
                }, $allowlist));
                $policies[] = "{$directive}=({$allowlistStr})";
            }
        }

        return $response->withHeader('Permissions-Policy', implode(', ', $policies));
    }

    /**
     * Remove X-Powered-By header
     */
    private function removePoweredByHeader(ResponseInterface $response): ResponseInterface
    {
        return $response->withoutHeader('X-Powered-By');
    }

    /**
     * Add Cross-Origin headers
     */
    private function addCrossOriginHeaders(ResponseInterface $response): ResponseInterface
    {
        if (!$this->config['cross_origin']['enabled']) {
            return $response;
        }

        $response = $response->withHeader('Cross-Origin-Embedder-Policy', $this->config['cross_origin']['embedder_policy']);
        $response = $response->withHeader('Cross-Origin-Opener-Policy', $this->config['cross_origin']['opener_policy']);
        $response = $response->withHeader('Cross-Origin-Resource-Policy', $this->config['cross_origin']['resource_policy']);

        return $response;
    }

    /**
     * Check if connection is HTTPS
     */
    private function isHttps(ServerRequestInterface $request): bool
    {
        $scheme = $request->getUri()->getScheme();
        return $scheme === 'https';
    }

    /**
     * Get default security configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'csp' => [
                'enabled' => true,
                'script_sources' => [
                    'https://cdnjs.cloudflare.com',
                    'https://cdn.jsdelivr.net'
                ],
                'style_sources' => [
                    'https://fonts.googleapis.com',
                    'https://cdnjs.cloudflare.com'
                ],
                'img_sources' => [
                    'https:',
                    'data:'
                ],
                'font_sources' => [
                    'https://fonts.gstatic.com',
                    'https://cdnjs.cloudflare.com'
                ],
                'connect_sources' => [
                    'wss:',
                    'ws:'
                ],
                'media_sources' => [],
                'frame_ancestors' => ["'none'"],
                'report_uri' => '/api/v1/csp-report'
            ],
            'frame_options' => [
                'enabled' => true,
                'value' => 'DENY'
            ],
            'content_type_options' => [
                'enabled' => true
            ],
            'xss_protection' => [
                'enabled' => true,
                'value' => '1; mode=block'
            ],
            'hsts' => [
                'enabled' => true,
                'max_age' => 31536000, // 1 year
                'include_subdomains' => true,
                'preload' => true
            ],
            'referrer_policy' => [
                'enabled' => true,
                'value' => 'strict-origin-when-cross-origin'
            ],
            'permissions_policy' => [
                'enabled' => true,
                'directives' => [
                    'camera' => [],
                    'microphone' => [],
                    'geolocation' => [],
                    'payment' => [],
                    'usb' => [],
                    'magnetometer' => [],
                    'gyroscope' => [],
                    'accelerometer' => [],
                    'fullscreen' => ['self'],
                    'picture-in-picture' => ['self']
                ]
            ],
            'cross_origin' => [
                'enabled' => true,
                'embedder_policy' => 'require-corp',
                'opener_policy' => 'same-origin',
                'resource_policy' => 'same-origin'
            ]
        ];
    }

    /**
     * Create CSP report endpoint response
     */
    public static function handleCspReport(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getBody()->getContents();
        $report = json_decode($body, true);

        if ($report && isset($report['csp-report'])) {
            // Log CSP violation
            error_log('CSP Violation: ' . json_encode($report['csp-report']));
            
            // You could also store this in a database or send to a monitoring service
            // $this->logCspViolation($report['csp-report']);
        }

        $response = new \Slim\Psr7\Response();
        return $response->withStatus(204); // No Content
    }

    /**
     * Get CSP nonce for use in templates
     */
    public static function getCspNonce(ServerRequestInterface $request): ?string
    {
        return $request->getAttribute('csp_nonce');
    }
}