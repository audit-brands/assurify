<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\StoryController;
use App\Controllers\CommentController;
use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Controllers\TagController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

// Home routes
$app->get('/', [HomeController::class, 'index']);
$app->get('/newest', [HomeController::class, 'newest']);
$app->get('/recent', [HomeController::class, 'recent']);
$app->get('/top', [HomeController::class, 'top']);

// Story routes
$app->group('/stories', function (RouteCollectorProxy $group) {
    $group->get('', [StoryController::class, 'create']);
    $group->post('', [StoryController::class, 'store']);
    $group->post('/{id}/vote', [StoryController::class, 'vote']);
});

$app->get('/s/{id}/{slug}', [StoryController::class, 'show']);

// Comment routes
$app->group('/comments', function (RouteCollectorProxy $group) {
    $group->post('', [CommentController::class, 'store']);
    $group->post('/{id}/vote', [CommentController::class, 'vote']);
});

// Tag routes
$app->get('/t/{tag}', [TagController::class, 'show']);
$app->get('/tags', [TagController::class, 'index']);

// User routes
$app->get('/u/{username}', [UserController::class, 'show']);
$app->get('/users', [UserController::class, 'index']);

// Auth routes
$app->group('/auth', function (RouteCollectorProxy $group) {
    $group->get('/login', [AuthController::class, 'loginForm']);
    $group->post('/login', [AuthController::class, 'login']);
    $group->post('/logout', [AuthController::class, 'logout']);
    $group->get('/signup', [AuthController::class, 'signupForm']);
    $group->post('/signup', [AuthController::class, 'signup']);
});

// Search
$app->get('/search', [HomeController::class, 'search']);

// Feeds
$app->get('/feeds/stories.rss', [HomeController::class, 'storiesFeed']);
$app->get('/feeds/comments.rss', [CommentController::class, 'commentsFeed']);