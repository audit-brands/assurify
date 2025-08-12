<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

class AppTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        // Load environment variables for testing
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        }

        // Create Container
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/dependencies.php');
        $container = $containerBuilder->build();

        // Create App
        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        // Add middleware and routes
        $app = $this->app;
        require __DIR__ . '/../../config/middleware.php';
        require __DIR__ . '/../../config/routes.php';
    }

    public function testHomePageReturns200(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testLoginPageReturns200(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/auth/login');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Login', (string) $response->getBody());
    }

    public function testNewestPageReturns200(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/newest');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Newest Stories', (string) $response->getBody());
    }

    public function testRssFeedReturns200(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/feeds/stories.rss');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/rss+xml', $response->getHeaderLine('Content-Type'));
    }
}
