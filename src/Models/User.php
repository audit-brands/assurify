<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'username',
        'email',
        'password_digest',
        'created_at',
        'is_admin',
        'is_moderator',
        'karma',
        'about',
        'email_notifications',
        'pushover_notifications',
        'pushover_user_key',
        'pushover_sound',
        'mailing_list_mode',
        'show_avatars',
        'show_story_previews',
        'show_submit_tagging_hints',
        'show_read_ribbons',
        'hide_dragons',
        'post_reply_notifications',
        'theme',
        'session_token',
        'password_reset_token',
        'banned_at',
        'banned_reason',
        'deleted',
        'disabled_invites',
        'filtered_tags',
        'favorite_tags'
    ];

    protected $hidden = [
        'password_digest',
        'session_token',
        'password_reset_token'
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_moderator' => 'boolean',
        'karma' => 'integer',
        'email_notifications' => 'boolean',
        'pushover_notifications' => 'boolean',
        'mailing_list_mode' => 'integer',
        'show_avatars' => 'boolean',
        'show_story_previews' => 'boolean',
        'show_submit_tagging_hints' => 'boolean',
        'show_read_ribbons' => 'boolean',
        'hide_dragons' => 'boolean',
        'post_reply_notifications' => 'boolean',
        'banned_at' => 'datetime',
        'deleted' => 'boolean',
        'disabled_invites' => 'boolean'
    ];

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // Messages sent by this user
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'author_user_id');
    }

    // Messages received by this user
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_user_id');
    }

    // All messages (sent or received) for this user
    public function messages()
    {
        return Message::where('author_user_id', $this->id)
                     ->orWhere('recipient_user_id', $this->id);
    }

    // Hats worn by this user
    public function hats(): HasMany
    {
        return $this->hasMany(Hat::class)->whereNull('doffed_at');
    }

    // All hats (including doffed ones)
    public function allHats(): HasMany
    {
        return $this->hasMany(Hat::class);
    }

    // Hats granted by this user (if moderator/admin)
    public function grantedHats(): HasMany
    {
        return $this->hasMany(Hat::class, 'granted_by_user_id');
    }

    // Stories saved by this user
    public function savedStories(): HasMany
    {
        return $this->hasMany(SavedStory::class);
    }

    // Stories hidden by this user
    public function hiddenStories(): HasMany
    {
        return $this->hasMany(Hidden::class);
    }

    // Tag filters set by this user
    public function tagFilters(): HasMany
    {
        return $this->hasMany(TagFilter::class);
    }

    // Moderation actions performed by this user
    public function moderations(): HasMany
    {
        return $this->hasMany(Moderation::class, 'moderator_user_id');
    }

    // Moderation actions performed on this user
    public function moderationsReceived(): HasMany
    {
        return $this->hasMany(Moderation::class, 'user_id');
    }

    // Check if user has saved a specific story
    public function hasSavedStory(int $storyId): bool
    {
        return SavedStory::isSavedByUser($this->id, $storyId);
    }

    // Check if user has hidden a specific story
    public function hasHiddenStory(int $storyId): bool
    {
        return Hidden::isHiddenByUser($this->id, $storyId);
    }

    // Check if user has filtered a specific tag
    public function hasFilteredTag(int $tagId): bool
    {
        return TagFilter::isFilteredByUser($this->id, $tagId);
    }

    // Get active hats for display
    public function getActiveHats()
    {
        return $this->hats()->orderBy('created_at', 'desc')->get();
    }

    // Check if user can moderate
    public function canModerate(): bool
    {
        return $this->is_admin || $this->is_moderator;
    }

    // Get unread message count
    public function getUnreadMessageCount(): int
    {
        return $this->receivedMessages()
                   ->where('has_been_read', false)
                   ->where('deleted_by_recipient', false)
                   ->count();
    }
}
