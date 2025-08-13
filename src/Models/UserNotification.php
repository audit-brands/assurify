<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User notifications for mentions, replies, follows, etc.
 */
class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type', // mention, reply, follow, vote, message, system
        'title',
        'message',
        'source_user_id', // User who triggered the notification
        'source_type', // Story, Comment, User, etc.
        'source_id',
        'action_url', // URL to view/respond to notification
        'is_read',
        'is_email_sent',
        'is_push_sent',
        'metadata', // Additional context data
        'priority' // low, normal, high, urgent
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_email_sent' => 'boolean',
        'is_push_sent' => 'boolean',
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    /**
     * Get the source object (Story, Comment, etc.)
     */
    public function source()
    {
        switch ($this->source_type) {
            case 'Story':
                return Story::find($this->source_id);
            case 'Comment':
                return Comment::find($this->source_id);
            case 'User':
                return User::find($this->source_id);
            default:
                return null;
        }
    }

    /**
     * Create notification
     */
    public static function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $sourceUserId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $actionUrl = null,
        array $metadata = [],
        string $priority = 'normal'
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'source_user_id' => $sourceUserId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
            'priority' => $priority,
            'is_read' => false,
            'is_email_sent' => false,
            'is_push_sent' => false
        ]);
    }

    /**
     * Create mention notification
     */
    public static function createMentionNotification(
        int $userId,
        int $sourceUserId,
        string $sourceType,
        int $sourceId,
        string $context
    ): self {
        $sourceUser = User::find($sourceUserId);
        
        return self::createNotification(
            $userId,
            'mention',
            'You were mentioned',
            "{$sourceUser->username} mentioned you in {$context}",
            $sourceUserId,
            $sourceType,
            $sourceId,
            self::generateActionUrl($sourceType, $sourceId),
            ['context' => $context],
            'normal'
        );
    }

    /**
     * Create reply notification
     */
    public static function createReplyNotification(
        int $userId,
        int $sourceUserId,
        string $sourceType,
        int $sourceId,
        string $context
    ): self {
        $sourceUser = User::find($sourceUserId);
        
        return self::createNotification(
            $userId,
            'reply',
            'New reply to your ' . strtolower($sourceType),
            "{$sourceUser->username} replied to your {$context}",
            $sourceUserId,
            $sourceType,
            $sourceId,
            self::generateActionUrl($sourceType, $sourceId),
            ['context' => $context],
            'normal'
        );
    }

    /**
     * Create follow notification
     */
    public static function createFollowNotification(
        int $userId,
        int $sourceUserId
    ): self {
        $sourceUser = User::find($sourceUserId);
        
        return self::createNotification(
            $userId,
            'follow',
            'New follower',
            "{$sourceUser->username} started following you",
            $sourceUserId,
            'User',
            $sourceUserId,
            "/u/{$sourceUser->username}",
            [],
            'low'
        );
    }

    /**
     * Create vote notification
     */
    public static function createVoteNotification(
        int $userId,
        int $sourceUserId,
        string $sourceType,
        int $sourceId,
        string $context
    ): self {
        $sourceUser = User::find($sourceUserId);
        
        return self::createNotification(
            $userId,
            'vote',
            'Your ' . strtolower($sourceType) . ' was upvoted',
            "{$sourceUser->username} upvoted your {$context}",
            $sourceUserId,
            $sourceType,
            $sourceId,
            self::generateActionUrl($sourceType, $sourceId),
            ['context' => $context],
            'low'
        );
    }

    /**
     * Generate action URL based on source type
     */
    private static function generateActionUrl(string $sourceType, int $sourceId): string
    {
        switch ($sourceType) {
            case 'Story':
                $story = Story::find($sourceId);
                return $story ? "/s/{$story->short_id}/{$story->slug}" : '/';
                
            case 'Comment':
                $comment = Comment::find($sourceId);
                if ($comment && $comment->story) {
                    return "/s/{$comment->story->short_id}/{$comment->story->slug}#comment-{$comment->id}";
                }
                return '/';
                
            case 'User':
                $user = User::find($sourceId);
                return $user ? "/u/{$user->username}" : '/';
                
            default:
                return '/';
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->is_read = true;
        $this->save();
    }

    /**
     * Mark multiple notifications as read
     */
    public static function markMultipleAsRead(array $notificationIds): void
    {
        self::whereIn('id', $notificationIds)
           ->update(['is_read' => true]);
    }

    /**
     * Get unread count for user
     */
    public static function getUnreadCount(int $userId): int
    {
        return self::where('user_id', $userId)
                  ->where('is_read', false)
                  ->count();
    }

    /**
     * Get notifications for user with pagination
     */
    public static function getUserNotifications(
        int $userId,
        int $limit = 50,
        bool $unreadOnly = false
    ) {
        $query = self::where('user_id', $userId)
                    ->with(['sourceUser'])
                    ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Clean up old read notifications
     */
    public static function cleanupOldNotifications(int $daysOld = 30): int
    {
        return self::where('is_read', true)
                  ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$daysOld} days")))
                  ->delete();
    }

    /**
     * Get notification icon based on type
     */
    public function getIcon(): string
    {
        switch ($this->type) {
            case 'mention':
                return 'at-sign';
            case 'reply':
                return 'message-circle';
            case 'follow':
                return 'user-plus';
            case 'vote':
                return 'arrow-up';
            case 'message':
                return 'mail';
            case 'system':
                return 'bell';
            default:
                return 'bell';
        }
    }

    /**
     * Get time ago string
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $diff = $this->created_at->diff($now);

        if ($diff->days > 7) {
            return $this->created_at->format('M j, Y');
        } elseif ($diff->days > 0) {
            return $diff->days . 'd ago';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ago';
        } elseif ($diff->i > 0) {
            return $diff->i . 'm ago';
        } else {
            return 'just now';
        }
    }
}