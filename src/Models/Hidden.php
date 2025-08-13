<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hidden extends Model
{
    protected $table = 'hiddens';
    
    protected $fillable = [
        'user_id',
        'story_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'story_id' => 'integer'
    ];

    // No updated_at timestamp for this model
    const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    // Check if user has hidden a specific story
    public static function isHiddenByUser(int $userId, int $storyId): bool
    {
        return self::where('user_id', $userId)
                  ->where('story_id', $storyId)
                  ->exists();
    }

    // Toggle hide status for a story
    public static function toggleHide(int $userId, int $storyId): bool
    {
        $hidden = self::where('user_id', $userId)
                     ->where('story_id', $storyId)
                     ->first();

        if ($hidden) {
            $hidden->delete();
            return false; // Removed from hidden
        } else {
            self::create([
                'user_id' => $userId,
                'story_id' => $storyId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true; // Added to hidden
        }
    }
}