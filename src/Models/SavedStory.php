<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedStory extends Model
{
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

    // Check if user has saved a specific story
    public static function isSavedByUser(int $userId, int $storyId): bool
    {
        return self::where('user_id', $userId)
                  ->where('story_id', $storyId)
                  ->exists();
    }

    // Toggle save status for a story
    public static function toggleSave(int $userId, int $storyId): bool
    {
        $saved = self::where('user_id', $userId)
                    ->where('story_id', $storyId)
                    ->first();

        if ($saved) {
            $saved->delete();
            return false; // Removed from saved
        } else {
            self::create([
                'user_id' => $userId,
                'story_id' => $storyId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true; // Added to saved
        }
    }
}