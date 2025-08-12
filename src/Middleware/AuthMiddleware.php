<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // TODO: Implement proper authentication check
        $isAuthenticated = isset($_SESSION['user_id']);

        if (!$isAuthenticated) {
            $response = new Response();
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(401);
        }

        return $handler->handle($request);
    }
}
