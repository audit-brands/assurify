<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User story bookmarks for saving stories to read later
 */
class UserBookmark extends Model
{
    protected $fillable = [
        'user_id',
        'story_id',
        'collection_id', // Optional: organize bookmarks into collections
        'notes', // Personal notes about the bookmark
        'tags', // Personal tags for organization
        'is_favorite', // Mark as favorite bookmark
        'read_at' // When user marked as read
    ];

    protected $casts = [
        'tags' => 'array',
        'is_favorite' => 'boolean',
        'read_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'collection_id');
    }

    /**
     * Check if user has bookmarked a story
     */
    public static function isBookmarked(int $userId, int $storyId): bool
    {
        return self::where('user_id', $userId)
                  ->where('story_id', $storyId)
                  ->exists();
    }

    /**
     * Add bookmark
     */
    public static function addBookmark(
        int $userId,
        int $storyId,
        ?int $collectionId = null,
        string $notes = '',
        array $tags = [],
        bool $isFavorite = false
    ): self {
        // Check if already bookmarked
        $existing = self::where('user_id', $userId)
                       ->where('story_id', $storyId)
                       ->first();

        if ($existing) {
            // Update existing bookmark
            $existing->update([
                'collection_id' => $collectionId,
                'notes' => $notes,
                'tags' => $tags,
                'is_favorite' => $isFavorite
            ]);
            return $existing;
        }

        // Create new bookmark
        $bookmark = self::create([
            'user_id' => $userId,
            'story_id' => $storyId,
            'collection_id' => $collectionId,
            'notes' => $notes,
            'tags' => $tags,
            'is_favorite' => $isFavorite
        ]);

        // Create activity
        UserActivity::createActivity(
            $userId,
            'story_bookmarked',
            'Story',
            $storyId,
            ['collection_id' => $collectionId],
            false, // Private activity
            2
        );

        return $bookmark;
    }

    /**
     * Remove bookmark
     */
    public static function removeBookmark(int $userId, int $storyId): bool
    {
        return self::where('user_id', $userId)
                  ->where('story_id', $storyId)
                  ->delete() > 0;
    }

    /**
     * Get user's bookmarks with filtering
     */
    public static function getUserBookmarks(
        int $userId,
        ?int $collectionId = null,
        ?array $tags = null,
        bool $favoritesOnly = false,
        bool $unreadOnly = false,
        int $limit = 50
    ) {
        $query = self::where('user_id', $userId)
                    ->with(['story', 'collection'])
                    ->orderBy('created_at', 'desc');

        if ($collectionId) {
            $query->where('collection_id', $collectionId);
        }

        if ($tags) {
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if ($favoritesOnly) {
            $query->where('is_favorite', true);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Mark bookmark as read
     */
    public function markAsRead(): void
    {
        $this->read_at = now();
        $this->save();
    }

    /**
     * Get unique tags used by user across all bookmarks
     */
    public static function getUserTags(int $userId): array
    {
        $bookmarks = self::where('user_id', $userId)
                        ->whereNotNull('tags')
                        ->pluck('tags')
                        ->toArray();

        $allTags = [];
        foreach ($bookmarks as $tags) {
            if (is_array($tags)) {
                $allTags = array_merge($allTags, $tags);
            }
        }

        return array_unique(array_filter($allTags));
    }

    /**
     * Get bookmark statistics for user
     */
    public static function getUserStats(int $userId): array
    {
        $bookmarks = self::where('user_id', $userId);
        
        return [
            'total' => $bookmarks->count(),
            'favorites' => $bookmarks->where('is_favorite', true)->count(),
            'unread' => $bookmarks->whereNull('read_at')->count(),
            'collections' => $bookmarks->whereNotNull('collection_id')
                                     ->distinct('collection_id')
                                     ->count('collection_id'),
            'tags' => count(self::getUserTags($userId))
        ];
    }
}