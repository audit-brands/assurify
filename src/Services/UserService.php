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
     * Get user profile by username
     */
    public function getUserProfile(string $username, ?User $viewer = null): ?array
    {
        $user = User::with(['stories', 'comments', 'invitedBy'])->where('username', $username)->first();
        
        if (!$user) {
            return null;
        }
        
        // Get recent stories for display
        $recentStories = $user->stories()
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($story) use ($viewer) {
                return [
                    'id' => $story->id,
                    'short_id' => $story->short_id,
                    'title' => $story->title,
                    'score' => $story->score,
                    'comments_count' => $story->comments_count ?? 0,
                    'created_at' => $story->created_at,
                    'time_ago' => $this->timeAgo($story->created_at),
                    'slug' => $this->generateSlug($story->title),
                    'domain' => $this->extractDomain($story->url ?? ''),
                    'url' => $story->url,
                    'can_edit' => $viewer ? $story->isEditableByUser($viewer) : false
                ];
            })
            ->toArray();

        // Get recent comments for display
        $recentComments = $user->comments()
            ->with(['story'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'short_id' => $comment->short_id,
                    'comment' => substr(strip_tags($comment->comment), 0, 200),
                    'score' => $comment->score,
                    'created_at' => $comment->created_at,
                    'time_ago' => $this->timeAgo($comment->created_at),
                    'story' => [
                        'title' => $comment->story->title ?? 'Unknown',
                        'short_id' => $comment->story->short_id ?? '',
                        'slug' => $this->generateSlug($comment->story->title ?? 'unknown')
                    ]
                ];
            })
            ->toArray();

        // Get user profile for additional fields
        $profile = $user->getProfile();
        
        // Return flattened structure expected by view
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_moderator' => $user->is_moderator,
            'karma' => $user->karma,
            'about' => $user->about,
            'homepage' => $user->homepage,
            'github_username' => $user->github_username,
            'twitter_username' => $user->twitter_username,
            'mastodon_username' => $user->mastodon_username,
            'linkedin_username' => $user->linkedin_username,
            'bluesky_username' => $user->bluesky_username,
            'show_email' => $profile->show_email ?? false,
            'invited_by' => $user->invitedBy ? [
                'id' => $user->invitedBy->id,
                'username' => $user->invitedBy->username
            ] : null,
            'created_at' => $user->created_at,
            'created_at_formatted' => $this->formatJoinedDate($user->created_at),
            'stats' => [
                'stories_count' => $user->stories()->where('is_deleted', false)->count(),
                'comments_count' => $user->comments()->where('is_deleted', false)->count(),
                'karma' => $user->karma,
                'recent_stories' => $recentStories,
                'recent_comments' => $recentComments
            ]
        ];
    }

    /**
     * Get user by username (returns User model)
     */
    public function getUserByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    /**
     * Update user karma (placeholder - implement karma calculation logic)
     */
    public function updateUserKarma(User $user): void
    {
        // Placeholder - would calculate karma based on story scores, comment scores, etc.
        // For now, just ensure the karma field exists
    }

    /**
     * Get user hats/badges (placeholder)
     */
    public function getUserHats(User $user): array
    {
        // Placeholder - would return user's hats/badges
        return [];
    }

    /**
     * Get user settings
     */
    public function getUserSettings(User $user): array
    {
        $profile = $user->getProfile();
        
        return [
            'username' => $user->username,
            'email' => $user->email,
            'email_notifications' => $user->email_notifications ?? true,
            'pushover_notifications' => $user->pushover_notifications ?? false,
            'pushover_user_key' => $user->pushover_user_key ?? '',
            'pushover_sound' => $user->pushover_sound ?? '',
            'mailing_list_mode' => $user->mailing_list_mode ?? false,
            'show_avatars' => $user->show_avatars ?? true,
            'show_story_previews' => $user->show_story_previews ?? true,
            'show_submit_tagging_hints' => $user->show_submit_tagging_hints ?? true,
            'show_read_ribbons' => $user->show_read_ribbons ?? true,
            'hide_dragons' => $user->hide_dragons ?? false,
            'show_email' => $profile->show_email ?? false,
            'homepage' => $user->homepage ?? '',
            'github_username' => $user->github_username ?? '',
            'twitter_username' => $user->twitter_username ?? '',
            'mastodon_username' => $user->mastodon_username ?? '',
            'linkedin_username' => $user->linkedin_username ?? '',
            'bluesky_username' => $user->bluesky_username ?? '',
            'about' => $user->about ?? '',
            'allow_messages_from' => $profile->allow_messages_from ?? 'members',
        ];
    }

    /**
     * Get user tag preferences (placeholder)
     */
    public function getUserTagPreferences(User $user): array
    {
        // Placeholder - would return user's tag filtering and favorite preferences
        return [
            'filtered_tags' => [],
            'favorite_tags' => []
        ];
    }

    /**
     * Update user settings
     */
    public function updateUserSettings(User $user, array $settings): bool
    {
        try {
            // Prepare user fields update - only include fields that are actually being updated
            $userFields = [];
            
            // Username validation and update
            if (isset($settings['username'])) {
                $username = trim($settings['username']);
                
                // Validate username format (letters, numbers, underscore, dash only)
                if (!preg_match('/^[A-Za-z0-9_\-]+$/', $username)) {
                    throw new \InvalidArgumentException('Username can only contain letters, numbers, underscore, and dash.');
                }
                
                // Validate username length
                if (strlen($username) < 2 || strlen($username) > 50) {
                    throw new \InvalidArgumentException('Username must be between 2 and 50 characters.');
                }
                
                // Check if username is already taken by another user
                $existingUser = User::where('username', $username)->where('id', '!=', $user->id)->first();
                if ($existingUser) {
                    throw new \InvalidArgumentException('Username is already in use.');
                }
                
                // Update session username if it changed
                if ($username !== $user->username && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user->id) {
                    $_SESSION['username'] = $username;
                }
                
                $userFields['username'] = $username;
            }
            
            // Account fields
            if (isset($settings['email'])) {
                $email = trim($settings['email']);
                
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Invalid email address format.');
                }
                
                // Check if email is already taken by another user
                $existingUser = User::where('email', $email)->where('id', '!=', $user->id)->first();
                if ($existingUser) {
                    throw new \InvalidArgumentException('Email address is already in use.');
                }
                
                $userFields['email'] = $email;
            }
            
            // Profile information fields
            if (isset($settings['about'])) {
                $userFields['about'] = $settings['about'];
            }
            if (isset($settings['homepage'])) {
                $userFields['homepage'] = $settings['homepage'];
            }
            if (isset($settings['github_username'])) {
                $userFields['github_username'] = $settings['github_username'];
            }
            if (isset($settings['twitter_username'])) {
                $userFields['twitter_username'] = $settings['twitter_username'];
            }
            if (isset($settings['mastodon_username'])) {
                $userFields['mastodon_username'] = $settings['mastodon_username'];
            }
            if (isset($settings['linkedin_username'])) {
                $userFields['linkedin_username'] = $settings['linkedin_username'];
            }
            if (isset($settings['bluesky_username'])) {
                $userFields['bluesky_username'] = $settings['bluesky_username'];
            }
            
            // Display preference fields (these come as boolean values from checkboxes)
            if (array_key_exists('show_avatars', $settings)) {
                $userFields['show_avatars'] = $settings['show_avatars'];
            }
            if (array_key_exists('show_story_previews', $settings)) {
                $userFields['show_story_previews'] = $settings['show_story_previews'];
            }
            if (array_key_exists('show_read_ribbons', $settings)) {
                $userFields['show_read_ribbons'] = $settings['show_read_ribbons'];
            }
            if (array_key_exists('hide_dragons', $settings)) {
                $userFields['hide_dragons'] = $settings['hide_dragons'];
            }
            
            // Update user fields if there are any
            if (!empty($userFields)) {
                $user->update($userFields);
            }
            
            // Update profile fields
            $profileFields = [];
            if (array_key_exists('show_email', $settings)) {
                $profileFields['show_email'] = $settings['show_email'];
            }
            if (isset($settings['allow_messages_from'])) {
                $profileFields['allow_messages_from'] = $settings['allow_messages_from'];
            }
            
            if (!empty($profileFields)) {
                $profile = $user->getProfile();
                $profile->update($profileFields);
            }
            
            return true;
        } catch (\InvalidArgumentException $e) {
            // Re-throw validation exceptions so they can be handled by the controller
            throw $e;
        } catch (\Exception $e) {
            error_log('Failed to update user settings: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Update user tag preferences (placeholder)
     */
    public function updateUserTagPreferences(User $user, array $filteredTags, array $favoriteTags): bool
    {
        // Placeholder - would update user's tag preferences
        return true;
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

    /**
     * Generate time ago string
     */
    private function timeAgo(\DateTime $datetime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($datetime);
        
        if ($diff->days > 365) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->days > 30) {
            $months = floor($diff->days / 30);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } elseif ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    }

    /**
     * Generate URL slug from title
     */
    private function generateSlug(?string $title): string
    {
        if (empty($title)) {
            return 'untitled';
        }
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        if (empty($url)) {
            return 'self';
        }
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return preg_replace('/^www\./', '', $host) ?: 'self';
    }

    /**
     * Format joined date for display (Rails time_ago_in_words style)
     */
    private function formatJoinedDate($createdAt): string
    {
        if (!$createdAt) {
            return 'unknown';
        }
        
        // Convert to DateTime if it's a string
        if (is_string($createdAt)) {
            $date = new \DateTime($createdAt);
        } else if ($createdAt instanceof \DateTime) {
            $date = $createdAt;
        } else {
            return 'unknown';
        }
        
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        // Calculate total seconds difference for more precise calculations
        $totalSeconds = ($now->getTimestamp() - $date->getTimestamp());
        $totalMinutes = round($totalSeconds / 60);
        $totalHours = round($totalSeconds / 3600);
        $totalDays = $diff->days;
        
        // Follow Rails time_ago_in_words logic
        if ($totalSeconds < 60) {
            return 'less than a minute ago';
        } elseif ($totalMinutes < 2) {
            return '1 minute ago';
        } elseif ($totalMinutes < 45) {
            return $totalMinutes . ' minutes ago';
        } elseif ($totalMinutes < 90) {
            return 'about 1 hour ago';
        } elseif ($totalHours < 24) {
            $hours = round($totalMinutes / 60);
            return 'about ' . $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($totalHours < 48) {
            return '1 day ago';
        } elseif ($totalDays < 30) {
            return $totalDays . ' days ago';
        } elseif ($totalDays < 60) {
            return 'about 1 month ago';
        } elseif ($totalDays < 365) {
            $months = round($totalDays / 30);
            return 'about ' . $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } elseif ($totalDays < 548) { // 1.5 years
            return 'about 1 year ago';
        } elseif ($totalDays < 730) { // 2 years
            return 'over 1 year ago';
        } else {
            $years = round($totalDays / 365);
            return 'about ' . $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}