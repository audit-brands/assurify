<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserFollow;
use App\Models\UserBookmark;
use App\Models\UserCollection;
use App\Models\UserNotification;
use App\Models\UserActivity;
use App\Models\Story;
use App\Models\Comment;

/**
 * Advanced user functionality service
 */
class UserService
{
    /**
     * Get enhanced user profile with statistics
     */
    public function getEnhancedProfile(int $userId, ?User $viewer = null): array
    {
        $user = User::with(['profile', 'stories', 'comments'])->findOrFail($userId);
        $profile = $user->getProfile();

        // Check if profile is visible to viewer
        if (!$profile->isVisibleTo($viewer)) {
            throw new \Exception('Profile is private');
        }

        // Update profile views if different user
        if ($viewer && $viewer->id !== $userId) {
            $profile->incrementViews();
        }

        // Get statistics
        $stats = $this->getUserStatistics($userId);
        
        // Get recent activities
        $recentActivities = UserActivity::where('user_id', $userId)
            ->where('is_public', true)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get follow relationship if viewer exists
        $followRelationship = null;
        if ($viewer && $viewer->id !== $userId) {
            $followRelationship = [
                'is_following' => $viewer->isFollowing($userId),
                'is_followed_by' => UserFollow::isFollowing($userId, $viewer->id)
            ];
        }

        return [
            'user' => $user,
            'profile' => $profile,
            'stats' => $stats,
            'recent_activities' => $recentActivities,
            'follow_relationship' => $followRelationship,
            'social_links' => $profile->getSocialLinks()
        ];
    }

    /**
     * Get comprehensive user statistics
     */
    public function getUserStatistics(int $userId): array
    {
        $user = User::findOrFail($userId);

        $storyStats = [
            'total' => $user->stories()->count(),
            'total_score' => $user->stories()->sum('score'),
            'average_score' => $user->stories()->avg('score') ?: 0
        ];

        $commentStats = [
            'total' => $user->comments()->count(), 
            'total_score' => $user->comments()->sum('score'),
            'average_score' => $user->comments()->avg('score') ?: 0
        ];

        $socialStats = [
            'followers' => $user->followers()->count(),
            'following' => $user->following()->count()
        ];

        $bookmarkStats = UserBookmark::getUserStats($userId);

        return [
            'karma' => $user->karma,
            'stories' => $storyStats,
            'comments' => $commentStats,
            'social' => $socialStats,
            'bookmarks' => $bookmarkStats,
            'member_since' => $user->created_at,
            'profile_views' => $user->profile->profile_views ?? 0,
            'reputation_score' => $user->profile->reputation_score ?? 0
        ];
    }

    /**
     * Handle user following/unfollowing
     */
    public function toggleFollow(int $followerId, int $followingId): array
    {
        if ($followerId === $followingId) {
            throw new \InvalidArgumentException('Cannot follow yourself');
        }

        $isFollowing = UserFollow::isFollowing($followerId, $followingId);

        if ($isFollowing) {
            UserFollow::unfollow($followerId, $followingId);
            $action = 'unfollowed';
        } else {
            UserFollow::follow($followerId, $followingId);
            
            // Create notification for the followed user
            UserNotification::createFollowNotification($followingId, $followerId);
            
            $action = 'followed';
        }

        return [
            'success' => true,
            'action' => $action,
            'is_following' => !$isFollowing
        ];
    }

    /**
     * Get follow suggestions for user
     */
    public function getFollowSuggestions(int $userId, int $limit = 10)
    {
        $suggestions = UserFollow::getFollowSuggestions($userId, $limit);

        return $suggestions->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->profile->display_name ?? $user->username,
                'bio' => $user->profile->bio,
                'follower_count' => $user->profile->follower_count ?? 0,
                'story_count' => $user->stories()->count(),
                'karma' => $user->karma
            ];
        });
    }

    /**
     * Handle story bookmarking
     */
    public function toggleBookmark(
        int $userId,
        int $storyId,
        ?int $collectionId = null,
        string $notes = '',
        array $tags = []
    ): array {
        $isBookmarked = UserBookmark::isBookmarked($userId, $storyId);

        if ($isBookmarked) {
            UserBookmark::removeBookmark($userId, $storyId);
            $action = 'removed';
        } else {
            // Get or create default collection if none specified
            if (!$collectionId) {
                $collection = UserCollection::getDefaultCollection($userId);
                $collectionId = $collection->id;
            }

            UserBookmark::addBookmark($userId, $storyId, $collectionId, $notes, $tags);
            $action = 'added';
        }

        return [
            'success' => true,
            'action' => $action,
            'is_bookmarked' => !$isBookmarked
        ];
    }

    /**
     * Get user's bookmark collections with stats
     */
    public function getUserCollections(int $userId): array
    {
        $collections = UserCollection::getUserCollections($userId, true);

        return $collections->map(function ($collection) {
            return [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
                'slug' => $collection->slug,
                'color' => $collection->color,
                'is_public' => $collection->is_public,
                'is_default' => $collection->is_default,
                'bookmark_count' => $collection->bookmark_count,
                'url' => $collection->getUrl()
            ];
        })->toArray();
    }

    /**
     * Create new collection for user
     */
    public function createCollection(
        int $userId,
        string $name,
        string $description = '',
        string $color = '#3b82f6',
        bool $isPublic = false
    ): UserCollection {
        return UserCollection::createCollection($userId, $name, $description, $color, $isPublic);
    }

    /**
     * Get user's activity feed
     */
    public function getActivityFeed(int $userId, int $limit = 50): array
    {
        $activities = UserActivity::getFeedForUser($userId, $limit);

        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'type' => $activity->activity_type,
                'description' => $activity->getDescription(),
                'user' => [
                    'id' => $activity->user->id,
                    'username' => $activity->user->username,
                    'display_name' => $activity->user->profile->display_name ?? $activity->user->username
                ],
                'target' => $this->formatActivityTarget($activity),
                'created_at' => $activity->created_at,
                'time_ago' => $activity->created_at->diffForHumans()
            ];
        })->toArray();
    }

    /**
     * Format activity target for display
     */
    private function formatActivityTarget($activity): ?array
    {
        $target = $activity->target();
        
        if (!$target) {
            return null;
        }

        switch ($activity->target_type) {
            case 'Story':
                return [
                    'type' => 'story',
                    'id' => $target->id,
                    'title' => $target->title,
                    'url' => "/s/{$target->short_id}/{$target->slug}",
                    'score' => $target->score
                ];
                
            case 'Comment':
                $story = Story::find($target->story_id);
                return [
                    'type' => 'comment',
                    'id' => $target->id,
                    'story_title' => $story->title ?? 'Unknown story',
                    'url' => $story ? "/s/{$story->short_id}/{$story->slug}#comment-{$target->id}" : '',
                    'score' => $target->score
                ];
                
            case 'User':
                return [
                    'type' => 'user',
                    'id' => $target->id,
                    'username' => $target->username,
                    'display_name' => $target->profile->display_name ?? $target->username,
                    'url' => "/u/{$target->username}"
                ];
                
            default:
                return null;
        }
    }

    /**
     * Get user notifications with formatting
     */
    public function getUserNotifications(int $userId, int $limit = 50, bool $unreadOnly = false): array
    {
        $notifications = UserNotification::getUserNotifications($userId, $limit, $unreadOnly);

        return $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'icon' => $notification->getIcon(),
                'is_read' => $notification->is_read,
                'priority' => $notification->priority,
                'action_url' => $notification->action_url,
                'source_user' => $notification->sourceUser ? [
                    'username' => $notification->sourceUser->username,
                    'display_name' => $notification->sourceUser->profile->display_name ?? $notification->sourceUser->username
                ] : null,
                'created_at' => $notification->created_at,
                'time_ago' => $notification->getTimeAgo()
            ];
        })->toArray();
    }

    /**
     * Mark notifications as read
     */
    public function markNotificationsAsRead(int $userId, array $notificationIds): bool
    {
        UserNotification::where('user_id', $userId)
            ->whereIn('id', $notificationIds)
            ->update(['is_read' => true]);

        return true;
    }

    /**
     * Update user profile
     */
    public function updateProfile(int $userId, array $data): UserProfile
    {
        $user = User::findOrFail($userId);
        $profile = $user->getProfile();

        $allowedFields = [
            'display_name', 'bio', 'location', 'website', 'twitter_handle',
            'github_handle', 'linkedin_handle', 'company', 'job_title',
            'expertise_tags', 'interests', 'timezone', 'preferred_language',
            'profile_visibility', 'show_email', 'show_real_name', 'show_location',
            'show_social_links', 'allow_messages_from', 'email_on_mention',
            'email_on_reply', 'email_on_follow', 'push_on_mention',
            'push_on_reply', 'push_on_follow'
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        $profile->update($updateData);
        $profile->updateReputationScore();

        return $profile;
    }
}