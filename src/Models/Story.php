<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'short_id',
        'url',
        'score',
        'flags',
        'is_expired',
        'is_moderated',
        'markeddown_description',
        'story_cache',
        'comments_count',
        'created_at',
        'upvotes',
        'downvotes',
        'is_unavailable',
        'unavailable_at',
        'twitter_id',
        'user_is_author',
        'merged_story_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'score' => 'integer',
        'flags' => 'integer',
        'is_expired' => 'boolean',
        'is_moderated' => 'boolean',
        'comments_count' => 'integer',
        'upvotes' => 'integer',
        'downvotes' => 'integer',
        'is_unavailable' => 'boolean',
        'user_is_author' => 'boolean',
        'merged_story_id' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'taggings');
    }

    public function taggings(): HasMany
    {
        return $this->hasMany(Tagging::class);
    }
}
