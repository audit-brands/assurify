<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ApiVersionMiddleware implements MiddlewareInterface
{
    private string $defaultVersion;
    private array $supportedVersions;
    
    public function __construct(string $defaultVersion = 'v1', array $supportedVersions = ['v1', 'v2'])
    {
        $this->defaultVersion = $defaultVersion;
        $this->supportedVersions = $supportedVersions;
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        $version = $this->determineVersion($request);
        
        // Add version to request attributes
        $request = $request->withAttribute('api_version', $version);
        
        $response = $handler->handle($request);
        
        // Add version headers to response
        $response = $response->withHeader('API-Version', $version);
        $response = $response->withHeader('API-Supported-Versions', implode(', ', $this->supportedVersions));
        
        // Add deprecation warnings for older versions
        if ($version === 'v1' && in_array('v2', $this->supportedVersions)) {
            $response = $response->withHeader('API-Deprecation-Warning', 'API v1 will be deprecated in future. Please migrate to v2.');
        }
        
        return $response;
    }
    
    private function determineVersion(Request $request): string
    {
        // 1. Check URL path for version (e.g., /api/v2/stories)
        $uri = $request->getUri();
        $path = $uri->getPath();
        
        if (preg_match('/\/api\/(v\d+)\//', $path, $matches)) {
            $version = $matches[1];
            if (in_array($version, $this->supportedVersions)) {
                return $version;
            }
        }
        
        // 2. Check Accept header (e.g., Accept: application/vnd.lobsters.v2+json)
        $acceptHeader = $request->getHeaderLine('Accept');
        if (preg_match('/application\/vnd\.lobsters\.(v\d+)\+json/', $acceptHeader, $matches)) {
            $version = $matches[1];
            if (in_array($version, $this->supportedVersions)) {
                return $version;
            }
        }
        
        // 3. Check custom version header
        $versionHeader = $request->getHeaderLine('API-Version');
        if (!empty($versionHeader) && in_array($versionHeader, $this->supportedVersions)) {
            return $versionHeader;
        }
        
        // 4. Check query parameter
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['version']) && in_array($queryParams['version'], $this->supportedVersions)) {
            return $queryParams['version'];
        }
        
        // 5. Fall back to default version
        return $this->defaultVersion;
    }
    
    public function getSupportedVersions(): array
    {
        return $this->supportedVersions;
    }
    
    public function getDefaultVersion(): string
    {
        return $this->defaultVersion;
    }
}