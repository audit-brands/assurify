<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Middleware\ApiVersionMiddleware;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Psr\Http\Server\RequestHandlerInterface;

class ApiVersionMiddlewareTest extends TestCase
{
    private ApiVersionMiddleware $middleware;
    
    protected function setUp(): void
    {
        $this->middleware = new ApiVersionMiddleware('v1', ['v1', 'v2']);
    }
    
    public function testDefaultVersion(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/stories');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v1', $response->getHeaderLine('API-Version'));
        $this->assertEquals('v1, v2', $response->getHeaderLine('API-Supported-Versions'));
    }
    
    public function testVersionFromPath(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v2/stories');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v2', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v2', $response->getHeaderLine('API-Version'));
    }
    
    public function testVersionFromAcceptHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/stories');
        $request = $request->withHeader('Accept', 'application/vnd.lobsters.v2+json');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v2', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v2', $response->getHeaderLine('API-Version'));
    }
    
    public function testVersionFromCustomHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/stories');
        $request = $request->withHeader('API-Version', 'v2');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v2', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v2', $response->getHeaderLine('API-Version'));
    }
    
    public function testVersionFromQueryParameter(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/stories?version=v2');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v2', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v2', $response->getHeaderLine('API-Version'));
    }
    
    public function testUnsupportedVersionFallsBackToDefault(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v99/stories');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v1', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v1', $response->getHeaderLine('API-Version'));
    }
    
    public function testVersionPrecedence(): void
    {
        // Path should take precedence over headers
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v2/stories');
        $request = $request->withHeader('API-Version', 'v1');
        $request = $request->withHeader('Accept', 'application/vnd.lobsters.v1+json');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function($req) {
            $this->assertEquals('v2', $req->getAttribute('api_version'));
            return new Response();
        });
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v2', $response->getHeaderLine('API-Version'));
    }
    
    public function testDeprecationWarningForV1(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stories');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals('v1', $response->getHeaderLine('API-Version'));
        $this->assertStringContainsString('deprecated', $response->getHeaderLine('API-Deprecation-Warning'));
    }
    
    public function testGetSupportedVersions(): void
    {
        $versions = $this->middleware->getSupportedVersions();
        
        $this->assertEquals(['v1', 'v2'], $versions);
    }
    
    public function testGetDefaultVersion(): void
    {
        $defaultVersion = $this->middleware->getDefaultVersion();
        
        $this->assertEquals('v1', $defaultVersion);
    }
}