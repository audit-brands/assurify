<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User collections for organizing bookmarked stories
 */
class UserCollection extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'slug',
        'color', // Hex color for visual organization
        'is_public', // Whether collection can be shared/viewed by others
        'is_default', // Default collection for bookmarks
        'bookmark_count'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_default' => 'boolean',
        'bookmark_count' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(UserBookmark::class, 'collection_id');
    }

    /**
     * Get or create default collection for user
     */
    public static function getDefaultCollection(int $userId): self
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'is_default' => true
            ],
            [
                'name' => 'Saved Stories',
                'description' => 'Default collection for bookmarked stories',
                'slug' => 'saved-stories',
                'color' => '#3b82f6',
                'is_public' => false,
                'is_default' => true
            ]
        );
    }

    /**
     * Create collection with unique slug
     */
    public static function createCollection(
        int $userId,
        string $name,
        string $description = '',
        string $color = '#3b82f6',
        bool $isPublic = false
    ): self {
        $slug = self::generateUniqueSlug($userId, $name);

        return self::create([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'color' => $color,
            'is_public' => $isPublic,
            'is_default' => false,
            'bookmark_count' => 0
        ]);
    }

    /**
     * Generate unique slug for collection
     */
    private static function generateUniqueSlug(int $userId, string $name): string
    {
        $baseSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $baseSlug = trim($baseSlug, '-');
        
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Update bookmark count
     */
    public function updateBookmarkCount(): void
    {
        $this->bookmark_count = $this->bookmarks()->count();
        $this->save();
    }

    /**
     * Get public collections for discovery
     */
    public static function getPublicCollections(int $limit = 20)
    {
        return self::where('is_public', true)
                  ->where('bookmark_count', '>', 0)
                  ->with(['user', 'bookmarks' => function ($query) {
                      $query->with('story')->limit(3);
                  }])
                  ->orderBy('bookmark_count', 'desc')
                  ->limit($limit)
                  ->get();
    }

    /**
     * Get user's collections
     */
    public static function getUserCollections(int $userId, bool $includeStats = true)
    {
        $query = self::where('user_id', $userId)
                    ->orderBy('is_default', 'desc')
                    ->orderBy('name');

        if ($includeStats) {
            $query->with(['bookmarks' => function ($q) {
                $q->select('collection_id')
                  ->selectRaw('COUNT(*) as count')
                  ->groupBy('collection_id');
            }]);
        }

        return $query->get();
    }

    /**
     * Get collection URL
     */
    public function getUrl(): string
    {
        return "/u/{$this->user->username}/collections/{$this->slug}";
    }

    /**
     * Check if collection is viewable by user
     */
    public function isViewableBy(?User $viewer): bool
    {
        if (!$viewer) {
            return $this->is_public;
        }

        return $this->is_public || $viewer->id === $this->user_id;
    }
}