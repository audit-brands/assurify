<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User following relationships
 */
class UserFollow extends Model
{
    protected $fillable = [
        'follower_user_id',
        'following_user_id',
        'created_at'
    ];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_user_id');
    }

    /**
     * Check if user A is following user B
     */
    public static function isFollowing(int $followerUserId, int $followingUserId): bool
    {
        return self::where('follower_user_id', $followerUserId)
                  ->where('following_user_id', $followingUserId)
                  ->exists();
    }

    /**
     * Create follow relationship
     */
    public static function follow(int $followerUserId, int $followingUserId): bool
    {
        if ($followerUserId === $followingUserId) {
            return false; // Can't follow yourself
        }

        if (self::isFollowing($followerUserId, $followingUserId)) {
            return false; // Already following
        }

        self::create([
            'follower_user_id' => $followerUserId,
            'following_user_id' => $followingUserId
        ]);

        // Update follow counts
        self::updateFollowCounts($followerUserId, $followingUserId);

        // Create activity
        UserActivity::createActivity(
            $followerUserId,
            'user_followed',
            'User',
            $followingUserId,
            [],
            true,
            5
        );

        return true;
    }

    /**
     * Remove follow relationship
     */
    public static function unfollow(int $followerUserId, int $followingUserId): bool
    {
        $deleted = self::where('follower_user_id', $followerUserId)
                      ->where('following_user_id', $followingUserId)
                      ->delete();

        if ($deleted) {
            // Update follow counts
            self::updateFollowCounts($followerUserId, $followingUserId);
        }

        return $deleted > 0;
    }

    /**
     * Update follower/following counts for both users
     */
    private static function updateFollowCounts(int $followerUserId, int $followingUserId): void
    {
        // Update follower's following count
        $followerProfile = UserProfile::firstOrCreate(['user_id' => $followerUserId]);
        $followerProfile->updateFollowingCount();

        // Update following user's follower count
        $followingProfile = UserProfile::firstOrCreate(['user_id' => $followingUserId]);
        $followingProfile->updateFollowerCount();
    }

    /**
     * Get users that both users follow (mutual following)
     */
    public static function getMutualFollowing(int $userId1, int $userId2)
    {
        $user1Following = self::where('follower_user_id', $userId1)
                             ->pluck('following_user_id');
        
        $user2Following = self::where('follower_user_id', $userId2)
                             ->pluck('following_user_id');

        $mutualIds = $user1Following->intersect($user2Following);

        return User::whereIn('id', $mutualIds)->get();
    }

    /**
     * Get follow suggestions for user (friends of friends, etc.)
     */
    public static function getFollowSuggestions(int $userId, int $limit = 10)
    {
        // Get users followed by people this user follows
        $currentFollowing = self::where('follower_user_id', $userId)
                               ->pluck('following_user_id')
                               ->toArray();

        // Don't include already followed users or self
        $currentFollowing[] = $userId;

        $suggestions = self::whereIn('follower_user_id', $currentFollowing)
                          ->whereNotIn('following_user_id', $currentFollowing)
                          ->select('following_user_id')
                          ->groupBy('following_user_id')
                          ->orderByRaw('COUNT(*) DESC')
                          ->limit($limit)
                          ->pluck('following_user_id');

        return User::whereIn('id', $suggestions)
                   ->with(['profile'])
                   ->get();
    }
}