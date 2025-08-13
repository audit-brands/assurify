<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    // Only use updated_at timestamp, not created_at
    const CREATED_AT = null;
    
    protected $fillable = [
        'user_id',
        'story_id',
        'comment_id',
        'vote',
        'reason',
        'updated_at'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'story_id' => 'integer',
        'comment_id' => 'integer',
        'vote' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
