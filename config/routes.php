<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\StoryController;
use App\Controllers\CommentController;
use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Controllers\TagController;
use App\Controllers\InvitationController;
use App\Controllers\SearchController;
use App\Controllers\ModerationController;
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
$app->get('/s/{id}/edit', [StoryController::class, 'edit']);

// Comment routes
$app->group('/comments', function (RouteCollectorProxy $group) {
    $group->post('', [CommentController::class, 'store']);
    $group->post('/{id}/vote', [CommentController::class, 'vote']);
    $group->get('/{id}', [CommentController::class, 'show']);
    $group->delete('/{id}', [CommentController::class, 'delete']);
    $group->post('/{id}/flag', [CommentController::class, 'flag']);
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
    $group->get('/forgot-password', [AuthController::class, 'forgotPasswordForm']);
    $group->post('/forgot-password', [AuthController::class, 'sendPasswordReset']);
});

// Signup routes
$app->get('/signup/invited', [AuthController::class, 'invitedSignupForm']);

// Invitation routes  
$app->group('/invitations', function (RouteCollectorProxy $group) {
    $group->get('', [InvitationController::class, 'index']);
    $group->get('/create', [InvitationController::class, 'create']);
    $group->post('', [InvitationController::class, 'store']);
    $group->get('/tree', [InvitationController::class, 'tree']);
});

// Search
$app->get('/search', [SearchController::class, 'index']);
$app->get('/search/autocomplete', [SearchController::class, 'autocomplete']);

// Feeds
$app->get('/feeds/stories.rss', [HomeController::class, 'storiesFeed']);
$app->get('/feeds/comments.rss', [CommentController::class, 'commentsFeed']);
$app->get('/t/{tag}/rss', [TagController::class, 'feed']);
$app->get('/u/{username}/rss', [UserController::class, 'feed']);

// Moderation routes
$app->group('/moderation', function (RouteCollectorProxy $group) {
    $group->get('', [ModerationController::class, 'dashboard']);
    $group->get('/flagged', [ModerationController::class, 'flaggedContent']);
    $group->get('/log', [ModerationController::class, 'moderationLog']);
    $group->post('/stories/{id}', [ModerationController::class, 'moderateStory']);
    $group->post('/comments/{id}', [ModerationController::class, 'moderateComment']);
    $group->post('/users/{id}/ban', [ModerationController::class, 'banUser']);
    $group->post('/users/{id}/unban', [ModerationController::class, 'unbanUser']);
});