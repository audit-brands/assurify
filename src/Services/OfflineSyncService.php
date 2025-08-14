<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Offline Data Synchronization Service
 * 
 * Handles offline data storage, synchronization with server,
 * and conflict resolution for offline-first functionality.
 */
class OfflineSyncService
{
    private array $pendingActions = [];
    private array $offlineData = [];
    private CommentService $commentService;
    private StoryService $storyService;
    
    public function __construct(
        CommentService $commentService = null,
        StoryService $storyService = null
    ) {
        $this->commentService = $commentService;
        $this->storyService = $storyService;
        $this->loadPendingActions();
        $this->loadOfflineData();
    }
    
    /**
     * Store an action to be synchronized when online
     */
    public function queueAction(string $type, array $data, int $userId = null): bool
    {
        $action = [
            'id' => $this->generateActionId(),
            'type' => $type,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'attempts' => 0,
            'status' => 'pending'
        ];
        
        $this->pendingActions[] = $action;
        $this->savePendingActions();
        
        return true;
    }
    
    /**
     * Process all pending actions when connection is restored
     */
    public function syncPendingActions(): array
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($this->pendingActions as $index => $action) {
            if ($action['status'] === 'pending' && $action['attempts'] < 3) {
                $result = $this->executeAction($action);
                
                if ($result['success']) {
                    $this->pendingActions[$index]['status'] = 'completed';
                    $results['synced']++;
                } else {
                    $this->pendingActions[$index]['attempts']++;
                    $this->pendingActions[$index]['last_error'] = $result['error'];
                    $results['failed']++;
                    $results['errors'][] = $result['error'];
                    
                    // Mark as failed after 3 attempts
                    if ($this->pendingActions[$index]['attempts'] >= 3) {
                        $this->pendingActions[$index]['status'] = 'failed';
                    }
                }
            }
        }
        
        // Remove completed actions
        $this->pendingActions = array_filter($this->pendingActions, function($action) {
            return $action['status'] !== 'completed';
        });
        
        $this->savePendingActions();
        
        return $results;
    }
    
    /**
     * Execute a specific action type
     */
    private function executeAction(array $action): array
    {
        switch ($action['type']) {
            case 'create_comment':
                return $this->syncComment($action['data']);
                
            case 'vote_story':
                return $this->syncStoryVote($action['data']);
                
            case 'vote_comment':
                return $this->syncCommentVote($action['data']);
                
            case 'flag_comment':
                return $this->syncCommentFlag($action['data']);
                
            case 'create_story':
                return $this->syncStory($action['data']);
                
            default:
                return [
                    'success' => false,
                    'error' => 'Unknown action type: ' . $action['type']
                ];
        }
    }
    
    /**
     * Sync comment creation
     */
    private function syncComment(array $data): array
    {
        try {
            // Validate required fields
            if (!isset($data['story_id']) || !isset($data['comment'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required comment data'
                ];
            }
            
            if (!$this->commentService) {
                return [
                    'success' => false,
                    'error' => 'CommentService not available'
                ];
            }
            
            // Find the user and story
            $user = isset($data['user_id']) ? \App\Models\User::find($data['user_id']) : null;
            $story = \App\Models\Story::find($data['story_id']);
            
            if (!$user || !$story) {
                return [
                    'success' => false,
                    'error' => 'User or story not found'
                ];
            }
            
            // Create comment using actual service
            $comment = $this->commentService->createComment($user, $story, [
                'comment' => $data['comment'],
                'parent_comment_id' => $data['parent_comment_id'] ?? null
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $comment->id,
                    'short_id' => $comment->short_id,
                    'story_id' => $comment->story_id,
                    'comment' => $comment->comment,
                    'parent_comment_id' => $comment->parent_comment_id,
                    'created_at' => $comment->created_at
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync story vote
     */
    private function syncStoryVote(array $data): array
    {
        try {
            if (!isset($data['story_id']) || !isset($data['vote'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required vote data'
                ];
            }
            
            if (!$this->storyService) {
                return [
                    'success' => false,
                    'error' => 'StoryService not available'
                ];
            }
            
            // Find the user and story
            $user = isset($data['user_id']) ? \App\Models\User::find($data['user_id']) : null;
            $story = \App\Models\Story::find($data['story_id']);
            
            if (!$user || !$story) {
                return [
                    'success' => false,
                    'error' => 'User or story not found'
                ];
            }
            
            // Cast vote using actual service
            $voted = $this->storyService->castVote($story, $user, (int)$data['vote']);
            
            // Refresh story to get updated score
            $story->refresh();
            
            return [
                'success' => true,
                'data' => [
                    'story_id' => $story->id,
                    'vote' => $data['vote'],
                    'new_score' => $story->score,
                    'voted' => $voted,
                    'upvotes' => $story->upvotes,
                    'downvotes' => $story->downvotes
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync comment vote
     */
    private function syncCommentVote(array $data): array
    {
        try {
            if (!isset($data['comment_id']) || !isset($data['vote'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required vote data'
                ];
            }
            
            if (!$this->commentService) {
                return [
                    'success' => false,
                    'error' => 'CommentService not available'
                ];
            }
            
            // Find the user and comment
            $user = isset($data['user_id']) ? \App\Models\User::find($data['user_id']) : null;
            $comment = \App\Models\Comment::find($data['comment_id']);
            
            if (!$user || !$comment) {
                return [
                    'success' => false,
                    'error' => 'User or comment not found'
                ];
            }
            
            // Cast vote using actual service
            $voted = $this->commentService->castVote($comment, $user, (int)$data['vote']);
            
            // Refresh comment to get updated score
            $comment->refresh();
            
            return [
                'success' => true,
                'data' => [
                    'comment_id' => $comment->id,
                    'vote' => $data['vote'],
                    'new_score' => $comment->score,
                    'voted' => $voted,
                    'upvotes' => $comment->upvotes,
                    'downvotes' => $comment->downvotes
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync comment flag
     */
    private function syncCommentFlag(array $data): array
    {
        try {
            if (!isset($data['comment_id'])) {
                return [
                    'success' => false,
                    'error' => 'Missing comment ID'
                ];
            }
            
            if (!$this->commentService) {
                return [
                    'success' => false,
                    'error' => 'CommentService not available'
                ];
            }
            
            // Find the user and comment
            $user = isset($data['user_id']) ? \App\Models\User::find($data['user_id']) : null;
            $comment = \App\Models\Comment::find($data['comment_id']);
            
            if (!$user || !$comment) {
                return [
                    'success' => false,
                    'error' => 'User or comment not found'
                ];
            }
            
            // Flag comment using actual service
            $flagged = $this->commentService->flagComment($comment, $user);
            
            return [
                'success' => true,
                'data' => [
                    'comment_id' => $comment->id,
                    'reason' => $data['reason'] ?? 'Inappropriate content',
                    'flagged' => $flagged,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync story creation
     */
    private function syncStory(array $data): array
    {
        try {
            $requiredFields = ['title', 'url'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ];
                }
            }
            
            if (!$this->storyService) {
                return [
                    'success' => false,
                    'error' => 'StoryService not available'
                ];
            }
            
            // Find the user
            $user = isset($data['user_id']) ? \App\Models\User::find($data['user_id']) : null;
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            // Create story using actual service
            $story = $this->storyService->createStory($user, [
                'title' => $data['title'],
                'url' => $data['url'],
                'description' => $data['description'] ?? '',
                'tags' => $data['tags'] ?? []
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $story->id,
                    'short_id' => $story->short_id,
                    'title' => $story->title,
                    'url' => $story->url,
                    'description' => $story->description,
                    'score' => $story->score,
                    'comments_count' => $story->comments_count,
                    'created_at' => $story->created_at,
                    'slug' => $story->slug
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Store data for offline access
     */
    public function storeOfflineData(string $key, array $data, int $ttl = 3600): bool
    {
        $this->offlineData[$key] = [
            'data' => $data,
            'timestamp' => time(),
            'ttl' => $ttl
        ];
        
        $this->saveOfflineData();
        return true;
    }
    
    /**
     * Retrieve offline data
     */
    public function getOfflineData(string $key): ?array
    {
        if (!isset($this->offlineData[$key])) {
            return null;
        }
        
        $item = $this->offlineData[$key];
        
        // Check if data has expired
        if (time() - $item['timestamp'] > $item['ttl']) {
            unset($this->offlineData[$key]);
            $this->saveOfflineData();
            return null;
        }
        
        return $item['data'];
    }
    
    /**
     * Get all cached stories for offline viewing
     */
    public function getCachedStories(int $limit = 50): array
    {
        $stories = $this->getOfflineData('cached_stories') ?? [];
        return array_slice($stories, 0, $limit);
    }
    
    /**
     * Cache stories for offline viewing
     */
    public function cacheStories(array $stories): bool
    {
        return $this->storeOfflineData('cached_stories', $stories, 7200); // 2 hour TTL
    }
    
    /**
     * Get cached comments for a story
     */
    public function getCachedComments(int $storyId): array
    {
        return $this->getOfflineData("story_comments_{$storyId}") ?? [];
    }
    
    /**
     * Cache comments for a story
     */
    public function cacheComments(int $storyId, array $comments): bool
    {
        return $this->storeOfflineData("story_comments_{$storyId}", $comments, 3600); // 1 hour TTL
    }
    
    /**
     * Clean up expired offline data
     */
    public function cleanupExpiredData(): int
    {
        $cleaned = 0;
        $currentTime = time();
        
        foreach ($this->offlineData as $key => $item) {
            if ($currentTime - $item['timestamp'] > $item['ttl']) {
                unset($this->offlineData[$key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->saveOfflineData();
        }
        
        return $cleaned;
    }
    
    /**
     * Get synchronization statistics
     */
    public function getSyncStats(): array
    {
        $pending = array_filter($this->pendingActions, function($action) {
            return $action['status'] === 'pending';
        });
        
        $failed = array_filter($this->pendingActions, function($action) {
            return $action['status'] === 'failed';
        });
        
        return [
            'pending_actions' => count($pending),
            'failed_actions' => count($failed),
            'total_actions' => count($this->pendingActions),
            'cached_items' => count($this->offlineData),
            'last_sync' => $this->getLastSyncTime()
        ];
    }
    
    /**
     * Handle conflict resolution when syncing
     */
    public function resolveConflict(array $localData, array $serverData, string $strategy = 'server_wins'): array
    {
        switch ($strategy) {
            case 'server_wins':
                return $serverData;
                
            case 'local_wins':
                return $localData;
                
            case 'merge':
                return array_merge($serverData, $localData);
                
            case 'latest_timestamp':
                $localTime = $localData['updated_at'] ?? 0;
                $serverTime = $serverData['updated_at'] ?? 0;
                return $serverTime > $localTime ? $serverData : $localData;
                
            default:
                return $serverData;
        }
    }
    
    /**
     * Generate unique action ID
     */
    private function generateActionId(): string
    {
        return uniqid('action_', true);
    }
    
    /**
     * Load pending actions from storage
     */
    private function loadPendingActions(): void
    {
        $file = $this->getStoragePath('pending_actions.json');
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $this->pendingActions = json_decode($data, true) ?? [];
        }
    }
    
    /**
     * Save pending actions to storage
     */
    private function savePendingActions(): void
    {
        $file = $this->getStoragePath('pending_actions.json');
        $this->ensureStorageDirectory();
        file_put_contents($file, json_encode($this->pendingActions, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load offline data from storage
     */
    private function loadOfflineData(): void
    {
        $file = $this->getStoragePath('offline_data.json');
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $this->offlineData = json_decode($data, true) ?? [];
        }
    }
    
    /**
     * Save offline data to storage
     */
    private function saveOfflineData(): void
    {
        $file = $this->getStoragePath('offline_data.json');
        $this->ensureStorageDirectory();
        file_put_contents($file, json_encode($this->offlineData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get storage file path
     */
    private function getStoragePath(string $filename): string
    {
        return __DIR__ . '/../../storage/offline/' . $filename;
    }
    
    /**
     * Ensure storage directory exists
     */
    private function ensureStorageDirectory(): void
    {
        $dir = __DIR__ . '/../../storage/offline';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    /**
     * Get last sync time
     */
    private function getLastSyncTime(): ?int
    {
        $file = $this->getStoragePath('last_sync.txt');
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }
        return null;
    }
    
    /**
     * Update last sync time
     */
    public function updateLastSyncTime(): void
    {
        $file = $this->getStoragePath('last_sync.txt');
        $this->ensureStorageDirectory();
        file_put_contents($file, (string) time());
    }
}