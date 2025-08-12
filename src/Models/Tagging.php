<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tagging extends Model
{
    protected $fillable = [
        'story_id',
        'tag_id'
    ];

    protected $casts = [
        'story_id' => 'integer',
        'tag_id' => 'integer'
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
