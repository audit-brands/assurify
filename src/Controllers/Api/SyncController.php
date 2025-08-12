<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\OfflineSyncService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Offline Synchronization API Controller
 * 
 * Handles offline data synchronization endpoints for PWA functionality
 */
class SyncController extends BaseApiController
{
    private OfflineSyncService $syncService;
    
    public function __construct(OfflineSyncService $syncService)
    {
        $this->syncService = $syncService;
    }
    
    /**
     * Queue an action for offline synchronization
     * POST /api/v1/sync/queue
     */
    public function queueAction(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validate required fields
            if (!isset($data['type']) || !isset($data['data'])) {
                return $this->errorResponse($response, 'Missing required fields: type, data', 400);
            }
            
            $userId = $this->getCurrentUserId($request);
            
            $result = $this->syncService->queueAction(
                $data['type'],
                $data['data'],
                $userId
            );
            
            if ($result) {
                return $this->successResponse($response, [
                    'queued' => true,
                    'message' => 'Action queued for synchronization'
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to queue action', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error queueing action: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Synchronize all pending actions
     * POST /api/v1/sync/process
     */
    public function syncPendingActions(Request $request, Response $response): Response
    {
        try {
            $results = $this->syncService->syncPendingActions();
            $this->syncService->updateLastSyncTime();
            
            $message = sprintf(
                'Sync completed: %d actions synced, %d failed',
                $results['synced'],
                $results['failed']
            );
            
            return $this->successResponse($response, [
                'sync_results' => $results,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Sync error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get synchronization status and statistics
     * GET /api/v1/sync/status
     */
    public function getSyncStatus(Request $request, Response $response): Response
    {
        try {
            $stats = $this->syncService->getSyncStats();
            
            return $this->successResponse($response, [
                'sync_status' => $stats,
                'is_online' => $this->isOnline($request)
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error getting sync status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cache data for offline access
     * POST /api/v1/sync/cache
     */
    public function cacheData(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (!isset($data['key']) || !isset($data['data'])) {
                return $this->errorResponse($response, 'Missing required fields: key, data', 400);
            }
            
            $ttl = $data['ttl'] ?? 3600; // Default 1 hour
            
            $result = $this->syncService->storeOfflineData(
                $data['key'],
                $data['data'],
                $ttl
            );
            
            if ($result) {
                return $this->successResponse($response, [
                    'cached' => true,
                    'key' => $data['key'],
                    'ttl' => $ttl
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to cache data', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Cache error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get cached data for offline access
     * GET /api/v1/sync/cache/{key}
     */
    public function getCachedData(Request $request, Response $response, array $args): Response
    {
        try {
            $key = $args['key'] ?? '';
            
            if (empty($key)) {
                return $this->errorResponse($response, 'Cache key is required', 400);
            }
            
            $data = $this->syncService->getOfflineData($key);
            
            if ($data !== null) {
                return $this->successResponse($response, [
                    'cached_data' => $data,
                    'key' => $key
                ]);
            } else {
                return $this->errorResponse($response, 'Cache data not found or expired', 404);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error retrieving cache: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get cached stories for offline viewing
     * GET /api/v1/sync/stories/cached
     */
    public function getCachedStories(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $limit = min((int)($params['limit'] ?? 50), 100); // Max 100 stories
            
            $stories = $this->syncService->getCachedStories($limit);
            
            return $this->successResponse($response, [
                'stories' => $stories,
                'count' => count($stories),
                'limit' => $limit,
                'is_cached' => true
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error getting cached stories: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cache stories for offline viewing
     * POST /api/v1/sync/stories/cache
     */
    public function cacheStories(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (!isset($data['stories']) || !is_array($data['stories'])) {
                return $this->errorResponse($response, 'Stories array is required', 400);
            }
            
            $result = $this->syncService->cacheStories($data['stories']);
            
            if ($result) {
                return $this->successResponse($response, [
                    'cached' => true,
                    'story_count' => count($data['stories']),
                    'message' => 'Stories cached successfully'
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to cache stories', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error caching stories: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get cached comments for a story
     * GET /api/v1/sync/stories/{storyId}/comments/cached
     */
    public function getCachedComments(Request $request, Response $response, array $args): Response
    {
        try {
            $storyId = (int)($args['storyId'] ?? 0);
            
            if ($storyId <= 0) {
                return $this->errorResponse($response, 'Valid story ID is required', 400);
            }
            
            $comments = $this->syncService->getCachedComments($storyId);
            
            return $this->successResponse($response, [
                'comments' => $comments,
                'story_id' => $storyId,
                'count' => count($comments),
                'is_cached' => true
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error getting cached comments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cache comments for a story
     * POST /api/v1/sync/stories/{storyId}/comments/cache
     */
    public function cacheComments(Request $request, Response $response, array $args): Response
    {
        try {
            $storyId = (int)($args['storyId'] ?? 0);
            
            if ($storyId <= 0) {
                return $this->errorResponse($response, 'Valid story ID is required', 400);
            }
            
            $data = $this->getRequestData($request);
            
            if (!isset($data['comments']) || !is_array($data['comments'])) {
                return $this->errorResponse($response, 'Comments array is required', 400);
            }
            
            $result = $this->syncService->cacheComments($storyId, $data['comments']);
            
            if ($result) {
                return $this->successResponse($response, [
                    'cached' => true,
                    'story_id' => $storyId,
                    'comment_count' => count($data['comments']),
                    'message' => 'Comments cached successfully'
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to cache comments', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error caching comments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Clean up expired cached data
     * POST /api/v1/sync/cleanup
     */
    public function cleanupExpiredData(Request $request, Response $response): Response
    {
        try {
            $cleaned = $this->syncService->cleanupExpiredData();
            
            return $this->successResponse($response, [
                'cleaned_items' => $cleaned,
                'message' => "Cleaned up {$cleaned} expired cache items"
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Cleanup error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Handle conflict resolution during sync
     * POST /api/v1/sync/resolve-conflict
     */
    public function resolveConflict(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $requiredFields = ['local_data', 'server_data'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse($response, "Missing required field: {$field}", 400);
                }
            }
            
            $strategy = $data['strategy'] ?? 'server_wins';
            
            $resolvedData = $this->syncService->resolveConflict(
                $data['local_data'],
                $data['server_data'],
                $strategy
            );
            
            return $this->successResponse($response, [
                'resolved_data' => $resolvedData,
                'strategy_used' => $strategy,
                'message' => 'Conflict resolved successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Conflict resolution error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check if the client is online (simple connectivity test)
     */
    private function isOnline(Request $request): bool
    {
        // Simple check - in a real implementation, you might check network connectivity
        // For now, assume online if the request reached the server
        return true;
    }
    
    /**
     * Get current user ID from request (authenticated user)
     */
    private function getCurrentUserId(Request $request): ?int
    {
        // This would typically extract user ID from JWT token or session
        // Placeholder implementation
        $user = $request->getAttribute('user');
        return $user['id'] ?? null;
    }
}