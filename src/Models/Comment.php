<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'user_id',
        'story_id',
        'parent_comment_id',
        'thread_id',
        'comment',
        'markeddown_comment',
        'is_deleted',
        'is_moderated',
        'score',
        'flags',
        'confidence',
        'upvotes',
        'downvotes'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'story_id' => 'integer',
        'parent_comment_id' => 'integer',
        'thread_id' => 'integer',
        'is_deleted' => 'boolean',
        'is_moderated' => 'boolean',
        'score' => 'integer',
        'flags' => 'integer',
        'confidence' => 'float',
        'upvotes' => 'integer',
        'downvotes' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_comment_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
