<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\StoryController;
use App\Controllers\CommentController;
use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Controllers\TagController;
use App\Controllers\MessageController;
use App\Controllers\InvitationController;
use App\Controllers\SearchController;
use App\Controllers\ModerationController;
use App\Controllers\AdminController;
use App\Controllers\Mod\StoriesController as ModStoriesController;
use App\Controllers\Mod\CommentsController as ModCommentsController;
use App\Controllers\PageController;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\StoriesApiController;
use App\Controllers\Api\V2\StoriesApiController as V2StoriesApiController;
use App\Controllers\Api\DocsController;
use App\Controllers\Api\PushController;
use App\Controllers\Api\SyncController;
use App\Controllers\Api\ContentIntelligenceController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\ApiVersionMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

// Home routes
$app->get('/', [HomeController::class, 'index']);
$app->get('/active', [HomeController::class, 'index']); // Active = same as home (hot stories)
$app->get('/recent', [HomeController::class, 'recent']);
$app->get('/comments', [CommentController::class, 'index']);

// Story routes
$app->group('/stories', function (RouteCollectorProxy $group) {
    $group->get('', [StoryController::class, 'create']);
    $group->post('', [StoryController::class, 'store']);
    $group->post('/{id}/vote', [StoryController::class, 'vote']);
});

$app->get('/s/{id}/edit', [StoryController::class, 'edit']);
$app->post('/s/{id}/update', [StoryController::class, 'update']);
$app->get('/s/{id}/{slug}', [StoryController::class, 'show']);

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
$app->post('/admin/tags/{id}/update', [PageController::class, 'updateTag']);

// Page routes
$app->get('/about', [PageController::class, 'about']);
$app->get('/tags', [PageController::class, 'tags']);
$app->get('/filter', [PageController::class, 'filter']);
$app->post('/filter', [PageController::class, 'filter']);

// User routes
$app->get('/u/{username}', [UserController::class, 'show']);
$app->get('/u/{username}/saved', [UserController::class, 'saved']);
$app->get('/users', [UserController::class, 'index']);

// Settings routes
$app->get('/settings', [UserController::class, 'settings']);
$app->post('/settings', [UserController::class, 'updateSettings']);

// Message routes
$app->group('/messages', function (RouteCollectorProxy $group) {
    $group->get('', [MessageController::class, 'inbox']);
    $group->get('/sent', [MessageController::class, 'sent']);
    $group->get('/compose', [MessageController::class, 'compose']);
    $group->post('/send', [MessageController::class, 'send']);
    $group->get('/search', [MessageController::class, 'search']);
    $group->get('/unread-count', [MessageController::class, 'unreadCount']);
    $group->get('/{id}', [MessageController::class, 'show']);
    $group->post('/{id}/reply', [MessageController::class, 'reply']);
    $group->get('/{id}/delete', [MessageController::class, 'delete']);
});

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

// Public moderation transparency (like Lobste.rs)
$app->get('/moderations', [ModerationController::class, 'moderationLog']);

// Moderation interface (/mod namespace - requires moderator access)
$app->group('/mod', function (RouteCollectorProxy $group) {
    // Moderation dashboard and tools (admin-only)
    $group->get('', [ModerationController::class, 'dashboard']);
    $group->get('/flagged', [ModerationController::class, 'flaggedContent']);
    
    // Story moderation
    $group->get('/stories/{short_id}/edit', [ModStoriesController::class, 'edit']);
    $group->post('/stories/{short_id}/update', [ModStoriesController::class, 'update']);
    $group->post('/stories/{short_id}/delete', [ModStoriesController::class, 'delete']);
    $group->post('/stories/{short_id}/undelete', [ModStoriesController::class, 'undelete']);
    
    // Comment moderation
    $group->get('/comments/{short_id}/edit', [ModCommentsController::class, 'edit']);
    $group->post('/comments/{short_id}/update', [ModCommentsController::class, 'update']);
    $group->post('/comments/{short_id}/delete', [ModCommentsController::class, 'delete']);
    $group->post('/comments/{short_id}/undelete', [ModCommentsController::class, 'undelete']);
    
    // User and content moderation actions
    $group->post('/stories/{id}/moderate', [ModerationController::class, 'moderateStory']);
    $group->post('/comments/{id}/moderate', [ModerationController::class, 'moderateComment']);
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
    
    // Category management
    $group->get('/categories', [AdminController::class, 'categories']);
    $group->post('/categories/add', [AdminController::class, 'addCategory']);
    $group->post('/categories/{id}/update', [AdminController::class, 'updateCategory']);
    $group->post('/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
    $group->post('/tags/create', [AdminController::class, 'createTag']);
    $group->post('/tags/assign-category', [AdminController::class, 'assignTagToCategory']);
    $group->post('/tags/remove-category', [AdminController::class, 'removeTagFromCategory']);
    
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
        
        // Content Intelligence endpoints
        $group->get('/intelligence/recommendations', [ContentIntelligenceController::class, 'getRecommendations']);
        $group->get('/intelligence/feed', [ContentIntelligenceController::class, 'getPersonalizedFeed']);
        $group->get('/intelligence/insights', [ContentIntelligenceController::class, 'getContentInsights']);
        $group->get('/intelligence/recommendations/{storyId}/explain', [ContentIntelligenceController::class, 'explainRecommendation']);
        $group->post('/intelligence/analyze', [ContentIntelligenceController::class, 'analyzeContent']);
        $group->post('/intelligence/suggest-tags', [ContentIntelligenceController::class, 'suggestTags']);
        $group->post('/intelligence/check-duplicates', [ContentIntelligenceController::class, 'checkDuplicates']);
        $group->post('/intelligence/similarity', [ContentIntelligenceController::class, 'calculateSimilarity']);
        $group->post('/intelligence/interaction', [ContentIntelligenceController::class, 'recordInteraction']);
        $group->post('/intelligence/preferences', [ContentIntelligenceController::class, 'updatePreferences']);
        
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