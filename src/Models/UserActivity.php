<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks user activities for activity feeds and analytics
 */
class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type', // story_posted, story_voted, comment_posted, comment_voted, user_followed, story_bookmarked
        'target_type', // Story, Comment, User
        'target_id',
        'metadata', // JSON data specific to activity type
        'is_public', // Whether this activity should appear in public feeds
        'points_earned' // Reputation points earned from this activity
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_public' => 'boolean',
        'points_earned' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target object (Story, Comment, User, etc.)
     */
    public function target()
    {
        switch ($this->target_type) {
            case 'Story':
                return Story::find($this->target_id);
            case 'Comment':
                return Comment::find($this->target_id);
            case 'User':
                return User::find($this->target_id);
            default:
                return null;
        }
    }

    /**
     * Create activity record
     */
    public static function createActivity(
        int $userId,
        string $activityType,
        string $targetType,
        int $targetId,
        array $metadata = [],
        bool $isPublic = true,
        int $pointsEarned = 0
    ): self {
        return self::create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'is_public' => $isPublic,
            'points_earned' => $pointsEarned
        ]);
    }

    /**
     * Get human-readable activity description
     */
    public function getDescription(): string
    {
        $target = $this->target();
        $username = $this->user->username;

        switch ($this->activity_type) {
            case 'story_posted':
                return "{$username} posted a story: " . ($target->title ?? 'Unknown story');
                
            case 'story_voted':
                return "{$username} upvoted: " . ($target->title ?? 'Unknown story');
                
            case 'comment_posted':
                $story = $target ? Story::find($target->story_id) : null;
                return "{$username} commented on: " . ($story->title ?? 'Unknown story');
                
            case 'comment_voted':
                $story = $target ? Story::find($target->story_id) : null;
                return "{$username} upvoted a comment on: " . ($story->title ?? 'Unknown story');
                
            case 'user_followed':
                return "{$username} followed " . ($target->username ?? 'Unknown user');
                
            case 'story_bookmarked':
                return "{$username} bookmarked: " . ($target->title ?? 'Unknown story');
                
            default:
                return "{$username} performed an action";
        }
    }

    /**
     * Get activities for user's feed (following + own activities)
     */
    public static function getFeedForUser(int $userId, int $limit = 50)
    {
        // Get IDs of users this user is following
        $followingIds = UserFollow::where('follower_user_id', $userId)
            ->pluck('following_user_id')
            ->toArray();
        
        // Include the user's own activities
        $followingIds[] = $userId;

        return self::whereIn('user_id', $followingIds)
            ->where('is_public', true)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get public activity feed
     */
    public static function getPublicFeed(int $limit = 100)
    {
        return self::where('is_public', true)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}