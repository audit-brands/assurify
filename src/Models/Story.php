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
        'is_deleted',
        'is_moderated',
        'markeddown_description',
        'comments_count',
        'created_at',
        'updated_at',
        'last_edited_at',
        'unavailable_at',
        'twitter_id',
        'user_is_author',
        'merged_story_id',
        'domain_id',
        'mastodon_id',
        'origin_id',
        'last_comment_at',
        'stories_count',
        'token',
        'normalized_url',
        'hotness',
        'user_is_following'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'score' => 'integer',
        'flags' => 'integer',
        'is_deleted' => 'boolean',
        'is_moderated' => 'boolean',
        'comments_count' => 'integer',
        'user_is_author' => 'boolean',
        'merged_story_id' => 'integer',
        'domain_id' => 'integer',
        'origin_id' => 'integer',
        'stories_count' => 'integer',
        'hotness' => 'float',
        'user_is_following' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'unavailable_at' => 'datetime',
        'last_comment_at' => 'datetime'
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
