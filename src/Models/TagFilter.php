<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagFilter extends Model
{
    protected $fillable = [
        'user_id',
        'tag_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tag_id' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    // Check if user has filtered a specific tag
    public static function isFilteredByUser(int $userId, int $tagId): bool
    {
        return self::where('user_id', $userId)
                  ->where('tag_id', $tagId)
                  ->exists();
    }

    // Toggle filter status for a tag
    public static function toggleFilter(int $userId, int $tagId): bool
    {
        $filter = self::where('user_id', $userId)
                     ->where('tag_id', $tagId)
                     ->first();

        if ($filter) {
            $filter->delete();
            return false; // Removed filter
        } else {
            self::create([
                'user_id' => $userId,
                'tag_id' => $tagId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return true; // Added filter
        }
    }

    // Get all filtered tag IDs for a user
    public static function getFilteredTagIds(int $userId): array
    {
        return self::where('user_id', $userId)
                  ->pluck('tag_id')
                  ->toArray();
    }
}