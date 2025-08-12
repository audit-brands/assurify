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
use App\Controllers\AdminController;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\StoriesApiController;
use App\Controllers\Api\V2\StoriesApiController as V2StoriesApiController;
use App\Controllers\Api\DocsController;
use App\Controllers\Api\PushController;
use App\Controllers\Api\SyncController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\ApiVersionMiddleware;
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

// Admin routes
$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('', [AdminController::class, 'dashboard']);
    $group->get('/performance', [AdminController::class, 'performance']);
    $group->get('/cache', [AdminController::class, 'cache']);
    $group->get('/users', [AdminController::class, 'users']);
    $group->get('/settings', [AdminController::class, 'settings']);
    $group->get('/logs', [AdminController::class, 'logs']);
    
    // Admin API endpoints
    $group->post('/flush-cache', [AdminController::class, 'flushCache']);
    $group->post('/cleanup', [AdminController::class, 'cleanupSystem']);
    $group->get('/export', [AdminController::class, 'exportData']);
    $group->get('/metrics', [AdminController::class, 'metricsApi']);
    $group->get('/system-info', [AdminController::class, 'systemInfo']);
});

// Health check endpoint (public)
$app->get('/health', [AdminController::class, 'healthCheck']);

// API Documentation
$app->get('/api/docs', [DocsController::class, 'index']);
$app->get('/api/openapi.json', [DocsController::class, 'openapi']);

// API v1 Routes
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // Public auth endpoints
    $group->post('/auth/login', [AuthApiController::class, 'login']);
    $group->post('/auth/register', [AuthApiController::class, 'register']);
    $group->post('/auth/refresh', [AuthApiController::class, 'refresh']);
    $group->get('/auth/scopes', [AuthApiController::class, 'getAvailableScopes']);
    
    // Public stories endpoints (read-only)
    $group->get('/stories', [StoriesApiController::class, 'index']);
    $group->get('/stories/{id}', [StoriesApiController::class, 'show']);
    
    // Protected endpoints (require authentication)
    $group->group('', function (RouteCollectorProxy $group) {
        // Auth endpoints
        $group->post('/auth/logout', [AuthApiController::class, 'logout']);
        $group->get('/auth/me', [AuthApiController::class, 'me']);
        $group->post('/auth/api-keys', [AuthApiController::class, 'createApiKey']);
        
        // Stories endpoints
        $group->post('/stories', [StoriesApiController::class, 'store']);
        $group->put('/stories/{id}', [StoriesApiController::class, 'update']);
        $group->delete('/stories/{id}', [StoriesApiController::class, 'delete']);
        $group->post('/stories/{id}/vote', [StoriesApiController::class, 'vote']);
        
        // Push notification endpoints
        $group->post('/push/subscribe', [PushController::class, 'subscribe']);
        $group->post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
        $group->get('/push/subscriptions', [PushController::class, 'getUserSubscriptions']);
        $group->post('/push/test', [PushController::class, 'sendTestNotification']);
        
        // Offline sync endpoints
        $group->post('/sync/queue', [SyncController::class, 'queueAction']);
        $group->post('/sync/process', [SyncController::class, 'syncPendingActions']);
        $group->get('/sync/status', [SyncController::class, 'getSyncStatus']);
        $group->post('/sync/cache', [SyncController::class, 'cacheData']);
        $group->get('/sync/cache/{key}', [SyncController::class, 'getCachedData']);
        $group->get('/sync/stories/cached', [SyncController::class, 'getCachedStories']);
        $group->post('/sync/stories/cache', [SyncController::class, 'cacheStories']);
        $group->get('/sync/stories/{storyId}/comments/cached', [SyncController::class, 'getCachedComments']);
        $group->post('/sync/stories/{storyId}/comments/cache', [SyncController::class, 'cacheComments']);
        $group->post('/sync/cleanup', [SyncController::class, 'cleanupExpiredData']);
        $group->post('/sync/resolve-conflict', [SyncController::class, 'resolveConflict']);
        
    })->add(ApiAuthMiddleware::class);
})->add(ApiVersionMiddleware::class);

// API v2 Routes (Enhanced version)
$app->group('/api/v2', function (RouteCollectorProxy $group) {
    // Public auth endpoints (same as v1)
    $group->post('/auth/login', [AuthApiController::class, 'login']);
    $group->post('/auth/register', [AuthApiController::class, 'register']);
    $group->post('/auth/refresh', [AuthApiController::class, 'refresh']);
    $group->get('/auth/scopes', [AuthApiController::class, 'getAvailableScopes']);
    
    // Public push notification endpoints
    $group->get('/push/public-key', [PushController::class, 'getPublicKey']);
    
    // Enhanced stories endpoints with v2 features
    $group->get('/stories', [V2StoriesApiController::class, 'index']);
    $group->get('/stories/{id}', [V2StoriesApiController::class, 'show']);
    
    // Protected endpoints (require authentication)
    $group->group('', function (RouteCollectorProxy $group) {
        // Auth endpoints (same as v1)
        $group->post('/auth/logout', [AuthApiController::class, 'logout']);
        $group->get('/auth/me', [AuthApiController::class, 'me']);
        $group->post('/auth/api-keys', [AuthApiController::class, 'createApiKey']);
        
        // Enhanced stories endpoints
        $group->post('/stories', [V2StoriesApiController::class, 'store']);
        $group->put('/stories/{id}', [V2StoriesApiController::class, 'update']);
        $group->delete('/stories/{id}', [V2StoriesApiController::class, 'delete']);
        $group->post('/stories/{id}/vote', [V2StoriesApiController::class, 'vote']);
        
    })->add(ApiAuthMiddleware::class);
})->add(ApiVersionMiddleware::class);