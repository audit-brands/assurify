<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use App\Models\Story;
use App\Models\User;
use App\Models\Tagging;

class TagService
{
    public function createTag(string $tagName, string $description = '', bool $privileged = false): ?Tag
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }
        
        try {
            $tagName = $this->normalizeTagName($tagName);

            // Check if tag already exists
            $existingTag = Tag::where('tag', $tagName)->first();
            if ($existingTag) {
                return $existingTag;
            }

            // Find or create "Other" category for new tags
            $otherCategory = \App\Models\TagCategory::firstOrCreate(
                ['name' => 'Other'],
                [
                    'description' => 'Uncategorized tags',
                    'sort_order' => 999,
                    'is_active' => true
                ]
            );

            $tag = new Tag();
            $tag->tag = $tagName;
            $tag->description = $description;
            $tag->privileged = $privileged;
            $tag->category_id = $otherCategory->id; // Default new tags to "Other" category
            $tag->token = bin2hex(random_bytes(16)); // Generate a random token
            $tag->save();

            return $tag;
            
        } catch (\Exception $e) {
            error_log("Database error in createTag: " . $e->getMessage());
            return null;
        }
    }

    public function normalizeTagName(string $tagName): string
    {
        $tagName = strtolower(trim($tagName));
        $tagName = preg_replace('/[^a-z0-9-]/', '', $tagName);
        return substr($tagName, 0, 25);
    }

    public function parseTagsFromString(string $tagsString): array
    {
        $tags = explode(',', $tagsString);
        $normalizedTags = [];

        foreach ($tags as $tag) {
            $normalized = $this->normalizeTagName($tag);
            if (!empty($normalized) && !in_array($normalized, $normalizedTags)) {
                $normalizedTags[] = $normalized;
            }
        }

        return array_slice($normalizedTags, 0, 5); // Limit to 5 tags
    }

    public function tagStory(Story $story, string $tagsString, User $user): void
    {
        $tagNames = $this->parseTagsFromString($tagsString);

        // Remove existing tags
        Tagging::where('story_id', $story->id)->delete();

        // Add new tags
        foreach ($tagNames as $tagName) {
            $tag = $this->createTag($tagName);

            // Skip if tag creation failed
            if (!$tag) {
                error_log("Failed to create tag: $tagName");
                continue;
            }

            // Check if user can use this tag (privileged tags)
            if ($tag->privileged && !$user->is_moderator && !$user->is_admin) {
                continue; // Skip privileged tags for non-moderators
            }

            $tagging = new Tagging();
            $tagging->story_id = $story->id;
            $tagging->tag_id = $tag->id;
            $tagging->save();
        }
    }

    public function getAllTags(string $sortBy = 'tag', string $searchQuery = '', bool $includeInactive = false): array
    {
        // Check if database connection is available
        if (!$this->isDatabaseAvailable()) {
            return $this->getDefaultTags();
        }
        
        try {
            $query = Tag::query();

            if (!$includeInactive) {
                $query->where('inactive', false);
            }

            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('tag', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('description', 'LIKE', "%{$searchQuery}%");
                });
            }

            $query->withCount(['stories' => function ($query) {
                $query->where('is_expired', false)
                      ->where('is_moderated', false);
            }]);

            switch ($sortBy) {
                case 'stories':
                    $query->orderBy('stories_count', 'desc');
                    break;
                case 'recent':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'alphabetical':
                default:
                    $query->orderBy('tag');
                    break;
            }

            $tags = $query->get();

            return $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'tag' => $tag->tag,
                    'description' => $tag->description,
                    'category_id' => $tag->category_id,
                    'privileged' => $tag->privileged,
                    'is_media' => $tag->is_media,
                    'inactive' => $tag->inactive,
                    'story_count' => $tag->stories_count,
                    'hotness_mod' => $tag->hotness_mod,
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            error_log("Database error in getAllTags: " . $e->getMessage());
            
            // Return default tags when database is not available
            return $this->getDefaultTags();
        }
    }

    /**
     * Check if database connection is available
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            // Try to get the Eloquent connection resolver
            $resolver = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
            if ($resolver === null) {
                return false;
            }
            
            // Try to get the default connection
            $connection = $resolver->connection();
            if ($connection === null) {
                return false;
            }
            
            // Test the connection with a simple query
            $connection->getPdo();
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get default tags when database is not available
     */
    private function getDefaultTags(): array
    {
        return [
            [
                'tag' => 'auditing',
                'description' => 'Audit topics',
                'privileged' => false,
                'is_media' => false,
                'inactive' => false,
                'story_count' => 0,
                'hotness_mod' => 0,
            ],
            [
                'tag' => 'risk',
                'description' => 'Risk management',
                'privileged' => false,
                'is_media' => false,
                'inactive' => false,
                'story_count' => 0,
                'hotness_mod' => 0,
            ],
            [
                'tag' => 'jobs',
                'description' => 'share and find jobs',
                'privileged' => false,
                'is_media' => false,
                'inactive' => false,
                'story_count' => 0,
                'hotness_mod' => 0,
            ]
        ];
    }

    public function getTagByName(string $tagName): ?Tag
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }
        
        try {
            return Tag::where('tag', $this->normalizeTagName($tagName))
                      ->where('inactive', false)
                      ->first();
        } catch (\Exception $e) {
            error_log("Database error in getTagByName: " . $e->getMessage());
            return null;
        }
    }

    public function getPopularTags(int $limit = 20): array
    {
        if (!$this->isDatabaseAvailable()) {
            return array_slice($this->getDefaultTags(), 0, $limit);
        }
        
        try {
            $tags = Tag::withCount(['stories' => function ($query) {
                          $query->where('is_expired', false)
                                ->where('is_moderated', false)
                                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 month'))); // Stories from last month
            }])
                      ->where('inactive', false)
                      ->orderBy('stories_count', 'desc')
                      ->take($limit)
                      ->get();

            return $tags->map(function ($tag) {
                return [
                    'tag' => $tag->tag,
                    'description' => $tag->description,
                    'story_count' => $tag->stories_count,
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            error_log("Database error in getPopularTags: " . $e->getMessage());
            return array_slice($this->getDefaultTags(), 0, $limit);
        }
    }

    public function getSuggestedTags(string $title, string $description = '', string $url = ''): array
    {
        // Simple tag suggestion based on keywords
        $text = strtolower("$title $description $url");
        $suggestions = [];

        // Programming language detection
        $languages = [
            'javascript' => ['js', 'javascript', 'node', 'npm', 'react', 'vue', 'angular'],
            'python' => ['python', 'django', 'flask', 'pip', 'pandas', 'numpy'],
            'php' => ['php', 'laravel', 'symfony', 'composer'],
            'java' => ['java', 'spring', 'maven', 'gradle'],
            'rust' => ['rust', 'cargo', 'rustc'],
            'go' => ['golang', 'go '],
            'c' => [' c ', 'gcc'],
            'cpp' => ['c++', 'cpp'],
            'ruby' => ['ruby', 'rails', 'gem'],
        ];

        foreach ($languages as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $suggestions[] = $tag;
                    break;
                }
            }
        }

        // Topic detection
        $topics = [
            'ai' => ['ai', 'artificial intelligence', 'machine learning', 'ml', 'neural', 'deep learning'],
            'security' => ['security', 'vulnerability', 'hack', 'exploit', 'encryption', 'ssl', 'tls'],
            'web' => ['web', 'http', 'html', 'css', 'browser', 'frontend', 'backend'],
            'mobile' => ['mobile', 'ios', 'android', 'app store', 'smartphone'],
            'database' => ['database', 'sql', 'mysql', 'postgresql', 'mongodb', 'redis'],
            'devops' => ['docker', 'kubernetes', 'ci/cd', 'deployment', 'infrastructure'],
        ];

        foreach ($topics as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $suggestions[] = $tag;
                    break;
                }
            }
        }

        return array_unique($suggestions);
    }

    public function canUserCreateTag(User $user): bool
    {
        // Users with sufficient karma can create new tags
        return $user->karma >= 10 || $user->is_moderator || $user->is_admin;
    }

    public function canUserUsePrivilegedTag(User $user, Tag $tag): bool
    {
        if (!$tag->privileged) {
            return true;
        }

        return $user->is_moderator || $user->is_admin;
    }

    public function getTagStats(string $tagName): array
    {
        if (!$this->isDatabaseAvailable()) {
            return [
                'total_stories' => 0,
                'stories_this_week' => 0,
                'stories_this_month' => 0,
                'avg_score' => 0,
                'top_contributors' => []
            ];
        }
        
        try {
            $tag = $this->getTagByName($tagName);
            if (!$tag) {
                return [
                    'total_stories' => 0,
                    'stories_this_week' => 0,
                    'stories_this_month' => 0,
                    'avg_score' => 0,
                    'top_contributors' => []
                ];
            }

            $totalStories = $tag->stories()
                ->where('is_expired', false)
                ->where('is_moderated', false)
                ->count();

            $storiesThisWeek = $tag->stories()
                ->where('is_expired', false)
                ->where('is_moderated', false)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')))
                ->count();

            $storiesThisMonth = $tag->stories()
                ->where('is_expired', false)
                ->where('is_moderated', false)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 month')))
                ->count();

            $avgScore = $tag->stories()
                ->where('is_expired', false)
                ->where('is_moderated', false)
                ->avg('score') ?? 0;

            $topContributors = $tag->stories()
                ->with('user')
                ->where('is_expired', false)
                ->where('is_moderated', false)
                ->selectRaw('user_id, count(*) as story_count')
                ->groupBy('user_id')
                ->orderByDesc('story_count')
                ->limit(5)
                ->get()
                ->map(function ($contributor) {
                    return [
                        'username' => $contributor->user->username ?? 'Unknown',
                        'story_count' => $contributor->story_count
                    ];
                });

            return [
                'total_stories' => $totalStories,
                'stories_this_week' => $storiesThisWeek,
                'stories_this_month' => $storiesThisMonth,
                'avg_score' => round($avgScore, 1),
                'top_contributors' => $topContributors->toArray()
            ];
            
        } catch (\Exception $e) {
            error_log("Database error in getTagStats: " . $e->getMessage());
            return [
                'total_stories' => 0,
                'stories_this_week' => 0,
                'stories_this_month' => 0,
                'avg_score' => 0,
                'top_contributors' => []
            ];
        }
    }

    public function getTrendingTags(int $days = 7, int $limit = 10): array
    {
        if (!$this->isDatabaseAvailable()) {
            return array_slice($this->getDefaultTags(), 0, $limit);
        }
        
        try {
            $tags = Tag::withCount(['stories' => function ($query) use ($days) {
                          $query->where('is_expired', false)
                                ->where('is_moderated', false)
                                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-$days days")));
            }])
                      ->where('inactive', false)
                      ->having('stories_count', '>', 0)
                      ->orderBy('stories_count', 'desc')
                      ->take($limit)
                      ->get();

            return $tags->map(function ($tag) {
                return [
                    'tag' => $tag->tag,
                    'description' => $tag->description,
                    'recent_stories' => $tag->stories_count,
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            error_log("Database error in getTrendingTags: " . $e->getMessage());
            return array_slice($this->getDefaultTags(), 0, $limit);
        }
    }

    public function getRelatedTags(string $tagName, int $limit = 10): array
    {
        if (!$this->isDatabaseAvailable()) {
            return [];
        }
        
        try {
            $tag = $this->getTagByName($tagName);
            if (!$tag) {
                return [];
            }

            // Find tags that appear together with this tag on stories
            $relatedTags = Tag::whereIn('id', function ($query) use ($tag) {
                $query->select('tag_id')
                      ->from('taggings as t1')
                      ->join('taggings as t2', 't1.story_id', '=', 't2.story_id')
                      ->where('t2.tag_id', $tag->id)
                      ->where('t1.tag_id', '!=', $tag->id)
                      ->groupBy('t1.tag_id')
                      ->orderByRaw('COUNT(*) DESC');
            })
            ->withCount(['stories' => function ($query) {
                $query->where('is_expired', false)
                      ->where('is_moderated', false);
            }])
            ->where('inactive', false)
            ->take($limit)
            ->get();

            return $relatedTags->map(function ($relatedTag) {
                return [
                    'tag' => $relatedTag->tag,
                    'description' => $relatedTag->description,
                    'story_count' => $relatedTag->stories_count,
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            error_log("Database error in getRelatedTags: " . $e->getMessage());
            return [];
        }
    }

    public function getTagCategories(): array
    {
        return [
            'Technology' => [
                'tags' => ['programming', 'web', 'mobile', 'ai', 'security', 'database'],
                'description' => 'Technology and programming related topics'
            ],
            'Business' => [
                'tags' => ['startups', 'business', 'marketing', 'finance', 'jobs'],
                'description' => 'Business and career topics'
            ],
            'Science' => [
                'tags' => ['science', 'research', 'math', 'physics', 'biology'],
                'description' => 'Scientific research and discoveries'
            ],
            'Culture' => [
                'tags' => ['culture', 'art', 'music', 'books', 'education'],
                'description' => 'Arts, culture, and educational content'
            ]
        ];
    }

    public function getTagsByNames(array $tagNames): array
    {
        if (empty($tagNames)) {
            return [];
        }

        // Check if database connection is available
        if (!$this->isDatabaseAvailable()) {
            // Return default tags that match the names
            $defaultTags = $this->getDefaultTags();
            return array_filter($defaultTags, function($tag) use ($tagNames) {
                return in_array($tag['tag'], $tagNames);
            });
        }

        try {
            $tags = Tag::whereIn('tag', $tagNames)->get();
            
            return $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'tag' => $tag->tag,
                    'description' => $tag->description,
                    'story_count' => $tag->stories()->count(),
                    'filter_count' => 0, // TODO: implement filter count tracking
                    'inactive' => $tag->inactive ?? false
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            error_log("Database error in getTagsByNames: " . $e->getMessage());
            // Fallback to default tags
            $defaultTags = $this->getDefaultTags();
            return array_filter($defaultTags, function($tag) use ($tagNames) {
                return in_array($tag['tag'], $tagNames);
            });
        }
    }
}
