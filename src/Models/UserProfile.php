<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Extended user profile for advanced social features
 */
class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'location', 
        'website',
        'twitter_handle',
        'github_handle',
        'linkedin_handle',
        'company',
        'job_title',
        'expertise_tags',
        'interests',
        'timezone',
        'preferred_language',
        'profile_visibility', // public, private, members_only
        'show_email',
        'show_real_name',
        'show_location',
        'show_social_links',
        'allow_messages_from', // anyone, members, followed_users, none
        'email_on_mention',
        'email_on_reply',
        'email_on_follow',
        'push_on_mention',
        'push_on_reply', 
        'push_on_follow',
        'last_active_at',
        'profile_views',
        'follower_count',
        'following_count',
        'reputation_score'
    ];

    protected $casts = [
        'expertise_tags' => 'array',
        'interests' => 'array',
        'show_email' => 'boolean',
        'show_real_name' => 'boolean', 
        'show_location' => 'boolean',
        'show_social_links' => 'boolean',
        'email_on_mention' => 'boolean',
        'email_on_reply' => 'boolean',
        'email_on_follow' => 'boolean',
        'push_on_mention' => 'boolean',
        'push_on_reply' => 'boolean',
        'push_on_follow' => 'boolean',
        'last_active_at' => 'datetime',
        'profile_views' => 'integer',
        'follower_count' => 'integer',
        'following_count' => 'integer',
        'reputation_score' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Activity tracking
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'user_id', 'user_id');
    }

    // Social connections
    public function followers(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_user_id', 'user_id');
    }

    public function following(): HasMany  
    {
        return $this->hasMany(UserFollow::class, 'follower_user_id', 'user_id');
    }

    // Bookmarks and collections
    public function bookmarks(): HasMany
    {
        return $this->hasMany(UserBookmark::class, 'user_id', 'user_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(UserCollection::class, 'user_id', 'user_id');
    }

    // Notifications
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id', 'user_id');
    }

    /**
     * Get the user's full display name
     */
    public function getDisplayName(): string
    {
        return $this->display_name ?: $this->user->username;
    }

    /**
     * Get formatted bio with markdown support
     */
    public function getFormattedBio(): string
    {
        if (!$this->bio) {
            return '';
        }
        
        // Basic markdown processing - convert **bold** and *italic*
        $bio = htmlspecialchars($this->bio);
        $bio = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $bio);
        $bio = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $bio);
        $bio = nl2br($bio);
        
        return $bio;
    }

    /**
     * Check if profile is visible to given user
     */
    public function isVisibleTo(?User $viewer): bool
    {
        switch ($this->profile_visibility) {
            case 'private':
                return $viewer && $viewer->id === $this->user_id;
            case 'members_only':
                return $viewer !== null;
            case 'public':
            default:
                return true;
        }
    }

    /**
     * Get social media links
     */
    public function getSocialLinks(): array
    {
        if (!$this->show_social_links) {
            return [];
        }

        $links = [];
        
        if ($this->website) {
            $links['website'] = [
                'url' => $this->website,
                'label' => 'Website',
                'icon' => 'globe'
            ];
        }
        
        if ($this->twitter_handle) {
            $links['twitter'] = [
                'url' => 'https://twitter.com/' . ltrim($this->twitter_handle, '@'),
                'label' => '@' . ltrim($this->twitter_handle, '@'),
                'icon' => 'twitter'
            ];
        }
        
        if ($this->github_handle) {
            $links['github'] = [
                'url' => 'https://github.com/' . $this->github_handle,
                'label' => $this->github_handle,
                'icon' => 'github'
            ];
        }
        
        if ($this->linkedin_handle) {
            $links['linkedin'] = [
                'url' => 'https://linkedin.com/in/' . $this->linkedin_handle,
                'label' => $this->linkedin_handle,
                'icon' => 'linkedin'
            ];
        }

        return $links;
    }

    /**
     * Update last active timestamp
     */
    public function updateLastActive(): void
    {
        $this->last_active_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Increment profile view count
     */
    public function incrementViews(): void
    {
        $this->increment('profile_views');
    }

    /**
     * Update follower counts
     */
    public function updateFollowerCount(): void
    {
        $this->follower_count = $this->followers()->count();
        $this->save();
    }

    public function updateFollowingCount(): void
    {
        $this->following_count = $this->following()->count(); 
        $this->save();
    }

    /**
     * Calculate and update reputation score based on activity
     */
    public function updateReputationScore(): void
    {
        $user = $this->user;
        
        $score = 0;
        
        // Base karma from user model
        $score += $user->karma * 10;
        
        // Story contributions
        $storyCount = $user->stories()->count();
        $storyScore = $user->stories()->sum('score'); 
        $score += $storyCount * 5 + $storyScore * 2;
        
        // Comment contributions  
        $commentCount = $user->comments()->count();
        $commentScore = $user->comments()->sum('score');
        $score += $commentCount * 2 + $commentScore;
        
        // Social engagement
        $score += $this->follower_count * 3;
        $score += $this->following_count;
        
        // Activity recency bonus
        if ($this->last_active_at && (time() - strtotime($this->last_active_at)) < (30 * 24 * 60 * 60)) {
            $score *= 1.1; // 10% bonus for recent activity
        }
        
        $this->reputation_score = (int) $score;
        $this->save();
    }
}