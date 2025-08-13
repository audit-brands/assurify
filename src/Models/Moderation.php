<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Moderation extends Model
{
    protected $fillable = [
        'moderator_user_id',
        'story_id',
        'comment_id',
        'user_id',
        'action',
        'reason',
        'is_from_suggestions',
        'token'
    ];

    protected $casts = [
        'moderator_user_id' => 'integer',
        'story_id' => 'integer',
        'comment_id' => 'integer',
        'user_id' => 'integer',
        'is_from_suggestions' => 'boolean'
    ];

    // Moderation actions
    const ACTION_DELETED_STORY = 'deleted story';
    const ACTION_DELETED_COMMENT = 'deleted comment';
    const ACTION_BANNED_USER = 'banned user';
    const ACTION_UNBANNED_USER = 'unbanned user';
    const ACTION_MERGED_STORY = 'merged story';
    const ACTION_FLAGGED = 'flagged';

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_user_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Get the target of this moderation action
    public function getTargetAttribute()
    {
        if ($this->story_id) {
            return $this->story;
        } elseif ($this->comment_id) {
            return $this->comment;
        } elseif ($this->user_id) {
            return $this->user;
        }
        return null;
    }

    // Generate a description of the moderation action
    public function getDescriptionAttribute(): string
    {
        $moderator = $this->moderator ? $this->moderator->username : 'System';
        $target = '';

        if ($this->story) {
            $target = "story \"{$this->story->title}\"";
        } elseif ($this->comment) {
            $target = "comment #{$this->comment->short_id}";
        } elseif ($this->user) {
            $target = "user @{$this->user->username}";
        }

        $reason = $this->reason ? " (reason: {$this->reason})" : '';

        return "{$moderator} {$this->action} {$target}{$reason}";
    }
}