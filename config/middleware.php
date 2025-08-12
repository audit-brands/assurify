<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use Slim\App;

// Add CORS middleware
$app->add(CorsMiddleware::class);

// Add session middleware
$app->add(function ($request, $handler) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $handler->handle($request);
});