<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

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
        'token',
        // New fields for enhanced logging
        'subject_type',
        'subject_id',
        'subject_title',
        'metadata',
        'ip_address'
    ];

    protected $casts = [
        'moderator_user_id' => 'integer',
        'story_id' => 'integer',
        'comment_id' => 'integer',
        'user_id' => 'integer',
        'is_from_suggestions' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Moderation actions
    const ACTION_DELETED_STORY = 'deleted story';
    const ACTION_DELETED_COMMENT = 'deleted comment';
    const ACTION_BANNED_USER = 'banned user';
    const ACTION_UNBANNED_USER = 'unbanned user';
    const ACTION_MERGED_STORY = 'merged story';
    const ACTION_FLAGGED = 'flagged';
    const ACTION_EDITED_STORY = 'edited story';
    const ACTION_UNDELETED_STORY = 'undeleted story';
    const ACTION_EDITED_COMMENT = 'edited comment';
    const ACTION_UNDELETED_COMMENT = 'undeleted comment';
    const ACTION_TAG_EDIT = 'edited tag';
    const ACTION_TAG_CATEGORY_CHANGE = 'changed tag category';
    const ACTION_DOMAIN_BAN = 'banned domain';

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

    // Enhanced scopes for querying
    public function scopeForStory(Builder $query, Story $story): Builder
    {
        return $query->where(function ($q) use ($story) {
            $q->where('story_id', $story->id)
              ->orWhere(function ($subQ) use ($story) {
                  $subQ->where('subject_type', 'story')
                       ->where('subject_id', $story->id);
              });
        });
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($subQ) use ($user) {
                  $subQ->where('subject_type', 'user')
                       ->where('subject_id', $user->id);
              });
        });
    }

    public function scopeByModerator(Builder $query, User $moderator): Builder
    {
        return $query->where('moderator_user_id', $moderator->id);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeBySubjectType(Builder $query, string $type): Builder
    {
        return $query->where('subject_type', $type);
    }

    // Static method for easy logging
    public static function log(
        User $moderator,
        string $action,
        $subject = null,
        ?string $reason = null,
        ?array $metadata = null,
        ?string $ipAddress = null
    ): self {
        $data = [
            'moderator_user_id' => $moderator->id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null)
        ];

        // Handle both old format and new format
        if ($subject instanceof Story) {
            $data['story_id'] = $subject->id;
            $data['subject_type'] = 'story';
            $data['subject_id'] = $subject->id;
            $data['subject_title'] = $subject->title;
        } elseif ($subject instanceof Comment) {
            $data['comment_id'] = $subject->id;
            $data['subject_type'] = 'comment';
            $data['subject_id'] = $subject->id;
            $data['subject_title'] = 'Comment #' . $subject->short_id;
        } elseif ($subject instanceof User) {
            $data['user_id'] = $subject->id;
            $data['subject_type'] = 'user';
            $data['subject_id'] = $subject->id;
            $data['subject_title'] = $subject->username;
        } elseif ($subject instanceof Tag) {
            $data['subject_type'] = 'tag';
            $data['subject_id'] = $subject->id;
            $data['subject_title'] = $subject->tag;
        } elseif (is_array($subject)) {
            // Allow passing custom subject data
            $data = array_merge($data, $subject);
        }

        return static::create($data);
    }

    // Generate a description of the moderation action
    public function getDescriptionAttribute(): string
    {
        $moderator = $this->moderator ? $this->moderator->username : 'System';
        $target = '';

        // Try new format first, fall back to old format
        if ($this->subject_title) {
            $target = $this->subject_title;
        } elseif ($this->story) {
            $target = "story \"{$this->story->title}\"";
        } elseif ($this->comment) {
            $target = "comment #{$this->comment->short_id}";
        } elseif ($this->user) {
            $target = "user @{$this->user->username}";
        }

        $reason = $this->reason ? " (reason: {$this->reason})" : '';

        return "{$moderator} {$this->action} {$target}{$reason}";
    }

    public function getSubjectLinkAttribute(): ?string
    {
        // Try new format first
        if ($this->subject_type && $this->subject_id) {
            switch ($this->subject_type) {
                case 'story':
                    $story = Story::find($this->subject_id);
                    return $story ? "/s/{$story->short_id}" : null;
                case 'comment':
                    $comment = Comment::find($this->subject_id);
                    return $comment ? "/c/{$comment->short_id}" : null;
                case 'user':
                    $user = User::find($this->subject_id);
                    return $user ? "/~{$user->username}" : null;
                case 'tag':
                    $tag = Tag::find($this->subject_id);
                    return $tag ? "/t/{$tag->tag}" : null;
            }
        }

        // Fall back to old format
        if ($this->story) {
            return "/s/{$this->story->short_id}";
        } elseif ($this->comment) {
            return "/c/{$this->comment->short_id}";
        } elseif ($this->user) {
            return "/~{$this->user->username}";
        }

        return null;
    }
}