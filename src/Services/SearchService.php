<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use App\Models\SearchAnalytic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    private const PER_PAGE = 20;
    private const MAX_RESULTS = 400; // 20 pages max
    private const CACHE_TTL = 600; // 10 minutes
    private const SUGGESTION_CACHE_TTL = 3600; // 1 hour
    
    public function __construct(
        private CacheService $cacheService,
        private SearchIndexService $searchIndexService
    ) {}

    /**
     * Advanced search with caching, analytics, and highlighting
     */
    public function search(string $query, string $type = 'all', string $order = 'newest', int $page = 1, array $filters = [], ?int $userId = null): array
    {
        $startTime = microtime(true);
        $query = trim($query);
        
        if (empty($query)) {
            return [
                'results' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'type' => $type,
                'highlighted_results' => [],
                'search_time_ms' => 0
            ];
        }

        // Parse advanced query syntax
        $parsedQuery = $this->parseAdvancedQuery($query);
        
        // Try cache first
        $cacheKey = $this->generateCacheKey($query, $type, $order, $page, $filters);
        $cached = $this->cacheService->get($cacheKey);
        
        if ($cached !== null) {
            $this->trackSearch($query, $type, $filters, count($cached['results']), $userId, microtime(true) - $startTime);
            return $cached;
        }

        $results = [];
        $total = 0;

        switch ($type) {
            case 'stories':
                $searchResults = $this->searchStoriesAdvanced($parsedQuery, $order, $page, $filters);
                $results = $this->formatStoriesForSearch($searchResults);
                $total = $this->countStoryResultsAdvanced($parsedQuery, $filters);
                break;
                
            case 'comments':
                $searchResults = $this->searchCommentsAdvanced($parsedQuery, $order, $page, $filters);
                $results = $this->formatCommentsForSearch($searchResults);
                $total = $this->countCommentResultsAdvanced($parsedQuery, $filters);
                break;
                
            case 'users':
                $searchResults = $this->searchUsers($parsedQuery, $order, $page, $filters);
                $results = $this->formatUsersForSearch($searchResults);
                $total = $this->countUserResults($parsedQuery, $filters);
                break;
                
            case 'all':
            default:
                $storyResults = $this->searchStoriesAdvanced($parsedQuery, $order, $page, $filters, self::PER_PAGE / 3);
                $commentResults = $this->searchCommentsAdvanced($parsedQuery, $order, $page, $filters, self::PER_PAGE / 3);
                $userResults = $this->searchUsers($parsedQuery, $order, $page, $filters, self::PER_PAGE / 3);
                
                $results = array_merge(
                    $this->formatStoriesForSearch($storyResults),
                    $this->formatCommentsForSearch($commentResults),
                    $this->formatUsersForSearch($userResults)
                );
                
                // Sort mixed results by advanced relevance scoring
                $results = $this->sortMixedResultsAdvanced($results, $order, $parsedQuery['terms']);
                
                $total = $this->countStoryResultsAdvanced($parsedQuery, $filters) + 
                        $this->countCommentResultsAdvanced($parsedQuery, $filters) +
                        $this->countUserResults($parsedQuery, $filters);
                break;
        }

        // Apply result highlighting
        $highlightedResults = $this->highlightResults($results, $parsedQuery['terms']);
        
        // Calculate search time
        $searchTimeMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $response = [
            'results' => $results,
            'highlighted_results' => $highlightedResults,
            'total' => min($total, self::MAX_RESULTS),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'type' => $type,
            'query' => $query,
            'parsed_query' => $parsedQuery,
            'search_time_ms' => $searchTimeMs,
            'filters_applied' => $filters
        ];
        
        // Cache results
        $this->cacheService->set($cacheKey, $response, self::CACHE_TTL);
        
        // Track search analytics
        $this->trackSearch($query, $type, $filters, $total, $userId, $searchTimeMs);
        
        return $response;
    }

    private function searchStories(string $query, string $order, int $page, ?int $limit = null): Collection
    {
        $limit = $limit ?? self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $searchQuery = Story::with(['user', 'tags'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('url', 'LIKE', "%{$query}%");
            });

        // Apply ordering
        switch ($order) {
            case 'score':
                $searchQuery->orderBy('score', 'desc');
                break;
            case 'relevance':
                // Simple relevance: title matches score higher
                $searchQuery->orderByRaw("
                    CASE 
                        WHEN title LIKE ? THEN 3
                        WHEN description LIKE ? THEN 2
                        ELSE 1
                    END DESC, score DESC
                ", ["%{$query}%", "%{$query}%"]);
                break;
            case 'newest':
            default:
                $searchQuery->orderBy('created_at', 'desc');
                break;
        }

        return $searchQuery->limit($limit)->offset($offset)->get();
    }

    private function searchComments(string $query, string $order, int $page, ?int $limit = null): Collection
    {
        $limit = $limit ?? self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $searchQuery = Comment::with(['user', 'story'])
            ->where('comment', 'LIKE', "%{$query}%")
            ->where('is_deleted', false);

        // Apply ordering
        switch ($order) {
            case 'score':
                $searchQuery->orderBy('score', 'desc');
                break;
            case 'relevance':
                // Simple relevance scoring
                $searchQuery->orderByRaw("
                    LENGTH(comment) - LENGTH(REPLACE(LOWER(comment), LOWER(?), '')) DESC,
                    score DESC
                ", [$query]);
                break;
            case 'newest':
            default:
                $searchQuery->orderBy('created_at', 'desc');
                break;
        }

        return $searchQuery->limit($limit)->offset($offset)->get();
    }

    private function countStoryResults(string $query): int
    {
        return Story::where(function ($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
              ->orWhere('description', 'LIKE', "%{$query}%")
              ->orWhere('url', 'LIKE', "%{$query}%");
        })->count();
    }

    private function countCommentResults(string $query): int
    {
        return Comment::where('comment', 'LIKE', "%{$query}%")
            ->where('is_deleted', false)
            ->count();
    }

    private function formatStoriesForSearch(Collection $stories): array
    {
        return $stories->map(function ($story) {
            return [
                'type' => 'story',
                'id' => $story->id,
                'short_id' => $story->short_id,
                'title' => $story->title,
                'description' => $story->description,
                'url' => $story->url,
                'score' => $story->score,
                'comment_count' => $story->comments_count ?? 0,
                'user' => [
                    'username' => $story->user->username ?? 'Unknown'
                ],
                'tags' => $story->tags->pluck('tag')->toArray(),
                'created_at' => $story->created_at,
                'time_ago' => $this->timeAgo($story->created_at),
                'domain' => $this->extractDomain($story->url)
            ];
        })->toArray();
    }

    private function formatCommentsForSearch(Collection $comments): array
    {
        return $comments->map(function ($comment) {
            return [
                'type' => 'comment',
                'id' => $comment->id,
                'short_id' => $comment->short_id,
                'comment' => $comment->comment,
                'score' => $comment->score,
                'user' => [
                    'username' => $comment->user->username ?? 'Unknown'
                ],
                'story' => [
                    'id' => $comment->story->id,
                    'short_id' => $comment->story->short_id,
                    'title' => $comment->story->title
                ],
                'created_at' => $comment->created_at,
                'time_ago' => $this->timeAgo($comment->created_at)
            ];
        })->toArray();
    }

    private function sortMixedResults(array $results, string $order): array
    {
        usort($results, function ($a, $b) use ($order) {
            switch ($order) {
                case 'score':
                    return $b['score'] <=> $a['score'];
                case 'relevance':
                    // Favor stories slightly over comments for mixed results
                    $aScore = $a['score'] + ($a['type'] === 'story' ? 0.1 : 0);
                    $bScore = $b['score'] + ($b['type'] === 'story' ? 0.1 : 0);
                    return $bScore <=> $aScore;
                case 'newest':
                default:
                    return $b['created_at'] <=> $a['created_at'];
            }
        });

        return $results;
    }

    public function searchTags(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        return Tag::where('tag', 'LIKE', "%{$query}%")
            ->orderBy('tag')
            ->limit(10)
            ->pluck('tag')
            ->toArray();
    }

    /**
     * Get popular searches from analytics
     */
    public function getPopularSearches(int $limit = 10, int $days = 30): array
    {
        return $this->cacheService->remember('popular_searches', function() use ($limit, $days) {
            return SearchAnalytic::getPopularQueries($limit, $days);
        }, self::SUGGESTION_CACHE_TTL);
    }
    
    /**
     * Get trending searches
     */
    public function getTrendingSearches(int $limit = 10): array
    {
        return $this->cacheService->remember('trending_searches', function() use ($limit) {
            return SearchAnalytic::getTrendingQueries($limit);
        }, self::SUGGESTION_CACHE_TTL);
    }
    
    /**
     * Get search analytics stats
     */
    public function getSearchStats(int $days = 30): array
    {
        return SearchAnalytic::getSearchStats($days);
    }
    
    /**
     * Advanced search suggestions with fuzzy matching
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [
                'suggestions' => [],
                'popular' => array_keys($this->getPopularSearches(5))
            ];
        }
        
        $cacheKey = "suggestions:" . md5($query);
        return $this->cacheService->remember($cacheKey, function() use ($query, $limit) {
            $suggestions = [];
            
            // Tag suggestions
            $tagSuggestions = $this->searchTags($query);
            foreach ($tagSuggestions as $tag) {
                $suggestions[] = [
                    'text' => $tag,
                    'type' => 'tag',
                    'score' => $this->calculateSuggestionScore($query, $tag)
                ];
            }
            
            // User suggestions
            $userSuggestions = $this->searchUsernames($query);
            foreach ($userSuggestions as $username) {
                $suggestions[] = [
                    'text' => "user:" . $username,
                    'type' => 'user',
                    'score' => $this->calculateSuggestionScore($query, $username)
                ];
            }
            
            // Popular query suggestions
            $popularQueries = SearchAnalytic::select('query_normalized')
                ->where('query_normalized', 'LIKE', "%{$query}%")
                ->groupBy('query_normalized')
                ->orderByRaw('COUNT(*) DESC')
                ->limit($limit)
                ->pluck('query_normalized')
                ->toArray();
                
            foreach ($popularQueries as $popularQuery) {
                $suggestions[] = [
                    'text' => $popularQuery,
                    'type' => 'query',
                    'score' => $this->calculateSuggestionScore($query, $popularQuery)
                ];
            }
            
            // Sort by score and limit
            usort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);
            
            return [
                'suggestions' => array_slice($suggestions, 0, $limit),
                'popular' => array_keys($this->getPopularSearches(5))
            ];
        }, self::SUGGESTION_CACHE_TTL);
    }
    
    /**
     * Faceted search with multiple filters
     */
    public function facetedSearch(array $filters = []): array
    {
        return $this->searchIndexService->facetedSearch($filters);
    }
    
    /**
     * Track search result click for analytics
     */
    public function trackClick(string $searchId, int $resultId, string $resultType): bool
    {
        // Find the search record and update it
        $search = SearchAnalytic::where('id', $searchId)->first();
        if ($search) {
            return $search->trackClick($resultId, $resultType);
        }
        return false;
    }

    private function timeAgo(\DateTime $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    }

    private function extractDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }
    
    /**
     * Parse advanced query syntax (phrases, boolean operators, wildcards)
     */
    private function parseAdvancedQuery(string $query): array
    {
        $parsed = [
            'original' => $query,
            'terms' => [],
            'phrases' => [],
            'must_include' => [],
            'must_exclude' => [],
            'wildcards' => [],
            'filters' => []
        ];
        
        // Extract quoted phrases
        preg_match_all('/"([^"]+)"/', $query, $phrases);
        $parsed['phrases'] = $phrases[1];
        $query = preg_replace('/"[^"]+"/', '', $query);
        
        // Extract must include terms (+term)
        preg_match_all('/\+([\w\d]+)/', $query, $mustInclude);
        $parsed['must_include'] = $mustInclude[1];
        $query = preg_replace('/\+[\w\d]+/', '', $query);
        
        // Extract must exclude terms (-term)
        preg_match_all('/-([\w\d]+)/', $query, $mustExclude);
        $parsed['must_exclude'] = $mustExclude[1];
        $query = preg_replace('/-[\w\d]+/', '', $query);
        
        // Extract wildcard terms (*term, term*)
        preg_match_all('/\*?[\w\d]+\*?/', $query, $wildcards);
        foreach ($wildcards[0] as $wildcard) {
            if (strpos($wildcard, '*') !== false) {
                $parsed['wildcards'][] = $wildcard;
                $query = str_replace($wildcard, '', $query);
            }
        }
        
        // Extract field filters (field:value)
        preg_match_all('/([\w]+):([\w\d]+)/', $query, $filters);
        for ($i = 0; $i < count($filters[0]); $i++) {
            $parsed['filters'][$filters[1][$i]] = $filters[2][$i];
            $query = str_replace($filters[0][$i], '', $query);
        }
        
        // Remaining terms
        $remainingTerms = array_filter(explode(' ', $query), 'trim');
        $parsed['terms'] = array_merge($remainingTerms, $parsed['phrases']);
        
        return $parsed;
    }
    
    /**
     * Advanced story search with filters
     */
    private function searchStoriesAdvanced(array $parsedQuery, string $order, int $page, array $filters, ?int $limit = null): Collection
    {
        $limit = $limit ?? self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $searchQuery = Story::with(['user', 'tags']);
        
        // Apply text search conditions
        $searchQuery->where(function ($q) use ($parsedQuery) {
            // Handle phrases (exact matches)
            foreach ($parsedQuery['phrases'] as $phrase) {
                $q->where(function ($subQ) use ($phrase) {
                    $subQ->where('title', 'LIKE', "%{$phrase}%")
                         ->orWhere('description', 'LIKE', "%{$phrase}%")
                         ->orWhere('url', 'LIKE', "%{$phrase}%");
                });
            }
            
            // Handle regular terms
            foreach ($parsedQuery['terms'] as $term) {
                if (!in_array($term, $parsedQuery['phrases'])) {
                    $q->where(function ($subQ) use ($term) {
                        $subQ->where('title', 'LIKE', "%{$term}%")
                             ->orWhere('description', 'LIKE', "%{$term}%")
                             ->orWhere('url', 'LIKE', "%{$term}%");
                    });
                }
            }
            
            // Handle must include terms
            foreach ($parsedQuery['must_include'] as $term) {
                $q->where(function ($subQ) use ($term) {
                    $subQ->where('title', 'LIKE', "%{$term}%")
                         ->orWhere('description', 'LIKE', "%{$term}%")
                         ->orWhere('url', 'LIKE', "%{$term}%");
                });
            }
            
            // Handle must exclude terms
            foreach ($parsedQuery['must_exclude'] as $term) {
                $q->where(function ($subQ) use ($term) {
                    $subQ->where('title', 'NOT LIKE', "%{$term}%")
                         ->where('description', 'NOT LIKE', "%{$term}%")
                         ->where('url', 'NOT LIKE', "%{$term}%");
                });
            }
        });
        
        // Apply filters
        $this->applyStoryFilters($searchQuery, $filters);
        
        // Apply field-specific filters from query
        if (isset($parsedQuery['filters']['user'])) {
            $searchQuery->whereHas('user', function ($q) use ($parsedQuery) {
                $q->where('username', $parsedQuery['filters']['user']);
            });
        }
        
        if (isset($parsedQuery['filters']['tag'])) {
            $searchQuery->whereHas('tags', function ($q) use ($parsedQuery) {
                $q->where('tag', $parsedQuery['filters']['tag']);
            });
        }

        // Apply advanced ordering with relevance scoring
        switch ($order) {
            case 'relevance':
                $searchQuery = $this->applyRelevanceScoring($searchQuery, $parsedQuery);
                break;
            case 'score':
                $searchQuery->orderBy('score', 'desc');
                break;
            case 'comments':
                $searchQuery->orderBy('comments_count', 'desc');
                break;
            case 'newest':
            default:
                $searchQuery->orderBy('created_at', 'desc');
                break;
        }

        return $searchQuery->limit($limit)->offset($offset)->get();
    }
    
    /**
     * Advanced comment search with filters
     */
    private function searchCommentsAdvanced(array $parsedQuery, string $order, int $page, array $filters, ?int $limit = null): Collection
    {
        $limit = $limit ?? self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $searchQuery = Comment::with(['user', 'story'])
            ->where('is_deleted', false);
            
        // Apply text search conditions
        $searchQuery->where(function ($q) use ($parsedQuery) {
            foreach ($parsedQuery['phrases'] as $phrase) {
                $q->where('comment', 'LIKE', "%{$phrase}%");
            }
            
            foreach ($parsedQuery['terms'] as $term) {
                if (!in_array($term, $parsedQuery['phrases'])) {
                    $q->where('comment', 'LIKE', "%{$term}%");
                }
            }
            
            foreach ($parsedQuery['must_include'] as $term) {
                $q->where('comment', 'LIKE', "%{$term}%");
            }
            
            foreach ($parsedQuery['must_exclude'] as $term) {
                $q->where('comment', 'NOT LIKE', "%{$term}%");
            }
        });
        
        // Apply filters
        $this->applyCommentFilters($searchQuery, $filters);
        
        // Apply field-specific filters
        if (isset($parsedQuery['filters']['user'])) {
            $searchQuery->whereHas('user', function ($q) use ($parsedQuery) {
                $q->where('username', $parsedQuery['filters']['user']);
            });
        }

        // Apply ordering
        switch ($order) {
            case 'relevance':
                $searchQuery->orderByRaw("
                    (
                        LENGTH(comment) - LENGTH(REPLACE(LOWER(comment), LOWER(?), ''))
                    ) * confidence DESC,
                    score DESC
                ", [$parsedQuery['original']]);
                break;
            case 'score':
                $searchQuery->orderBy('score', 'desc');
                break;
            case 'confidence':
                $searchQuery->orderBy('confidence', 'desc');
                break;
            case 'newest':
            default:
                $searchQuery->orderBy('created_at', 'desc');
                break;
        }

        return $searchQuery->limit($limit)->offset($offset)->get();
    }
    
    /**
     * Search users
     */
    private function searchUsers(array $parsedQuery, string $order, int $page, array $filters, ?int $limit = null): Collection
    {
        $limit = $limit ?? self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $searchQuery = User::where('deleted', false);
        
        // Apply text search
        $searchQuery->where(function ($q) use ($parsedQuery) {
            foreach (array_merge($parsedQuery['terms'], $parsedQuery['phrases']) as $term) {
                $q->where('username', 'LIKE', "%{$term}%")
                  ->orWhere('about', 'LIKE', "%{$term}%");
            }
        });
        
        // Apply filters
        if (!empty($filters['min_karma'])) {
            $searchQuery->where('karma', '>=', $filters['min_karma']);
        }
        
        if (!empty($filters['is_moderator'])) {
            $searchQuery->where('is_moderator', true);
        }

        // Apply ordering
        switch ($order) {
            case 'karma':
                $searchQuery->orderBy('karma', 'desc');
                break;
            case 'newest':
                $searchQuery->orderBy('created_at', 'desc');
                break;
            case 'relevance':
            default:
                $searchQuery->orderByRaw("
                    CASE 
                        WHEN username LIKE ? THEN 2
                        WHEN about LIKE ? THEN 1
                        ELSE 0
                    END DESC, karma DESC
                ", ["%{$parsedQuery['original']}%", "%{$parsedQuery['original']}%"]);
                break;
        }

        return $searchQuery->limit($limit)->offset($offset)->get();
    }
    
    /**
     * Apply story-specific filters
     */
    private function applyStoryFilters($query, array $filters): void
    {
        if (!empty($filters['min_score'])) {
            $query->where('score', '>=', $filters['min_score']);
        }
        
        if (!empty($filters['max_score'])) {
            $query->where('score', '<=', $filters['max_score']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tag', $tags);
            });
        }
        
        if (!empty($filters['domain'])) {
            $query->where('url', 'LIKE', '%' . $filters['domain'] . '%');
        }
        
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['is_expired']) && $filters['is_expired'] !== '') {
            $query->where('is_expired', (bool) $filters['is_expired']);
        }
    }
    
    /**
     * Apply comment-specific filters
     */
    private function applyCommentFilters($query, array $filters): void
    {
        if (!empty($filters['min_score'])) {
            $query->where('score', '>=', $filters['min_score']);
        }
        
        if (!empty($filters['min_confidence'])) {
            $query->where('confidence', '>=', $filters['min_confidence']);
        }
        
        if (!empty($filters['story_id'])) {
            $query->where('story_id', $filters['story_id']);
        }
        
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
    }
    
    /**
     * Apply relevance scoring to story queries
     */
    private function applyRelevanceScoring($query, array $parsedQuery)
    {
        $searchTerm = $parsedQuery['original'];
        
        return $query->orderByRaw("
            (
                CASE 
                    WHEN LOWER(title) = LOWER(?) THEN 100
                    WHEN LOWER(title) LIKE LOWER(?) THEN 50
                    WHEN LOWER(description) LIKE LOWER(?) THEN 25
                    WHEN LOWER(url) LIKE LOWER(?) THEN 10
                    ELSE 1
                END
                * (1 + (score / 100))
                * (1 + (comments_count / 50))
                * CASE 
                    WHEN created_at > NOW() - INTERVAL 1 DAY THEN 1.5
                    WHEN created_at > NOW() - INTERVAL 7 DAY THEN 1.2
                    WHEN created_at > NOW() - INTERVAL 30 DAY THEN 1.1
                    ELSE 1.0
                END
            ) DESC
        ", [
            $searchTerm,
            "%{$searchTerm}%",
            "%{$searchTerm}%",
            "%{$searchTerm}%"
        ]);
    }
    
    /**
     * Count story results with advanced query
     */
    private function countStoryResultsAdvanced(array $parsedQuery, array $filters): int
    {
        $query = Story::query();
        
        $query->where(function ($q) use ($parsedQuery) {
            foreach ($parsedQuery['phrases'] as $phrase) {
                $q->where(function ($subQ) use ($phrase) {
                    $subQ->where('title', 'LIKE', "%{$phrase}%")
                         ->orWhere('description', 'LIKE', "%{$phrase}%")
                         ->orWhere('url', 'LIKE', "%{$phrase}%");
                });
            }
            
            foreach ($parsedQuery['terms'] as $term) {
                if (!in_array($term, $parsedQuery['phrases'])) {
                    $q->where(function ($subQ) use ($term) {
                        $subQ->where('title', 'LIKE', "%{$term}%")
                             ->orWhere('description', 'LIKE', "%{$term}%")
                             ->orWhere('url', 'LIKE', "%{$term}%");
                    });
                }
            }
        });
        
        $this->applyStoryFilters($query, $filters);
        
        return $query->count();
    }
    
    /**
     * Count comment results with advanced query
     */
    private function countCommentResultsAdvanced(array $parsedQuery, array $filters): int
    {
        $query = Comment::where('is_deleted', false);
        
        $query->where(function ($q) use ($parsedQuery) {
            foreach (array_merge($parsedQuery['terms'], $parsedQuery['phrases']) as $term) {
                $q->where('comment', 'LIKE', "%{$term}%");
            }
        });
        
        $this->applyCommentFilters($query, $filters);
        
        return $query->count();
    }
    
    /**
     * Count user results
     */
    private function countUserResults(array $parsedQuery, array $filters): int
    {
        $query = User::where('deleted', false);
        
        $query->where(function ($q) use ($parsedQuery) {
            foreach (array_merge($parsedQuery['terms'], $parsedQuery['phrases']) as $term) {
                $q->where('username', 'LIKE', "%{$term}%")
                  ->orWhere('about', 'LIKE', "%{$term}%");
            }
        });
        
        if (!empty($filters['min_karma'])) {
            $query->where('karma', '>=', $filters['min_karma']);
        }
        
        return $query->count();
    }
    
    /**
     * Format users for search results
     */
    private function formatUsersForSearch(Collection $users): array
    {
        return $users->map(function ($user) {
            return [
                'type' => 'user',
                'id' => $user->id,
                'username' => $user->username,
                'about' => $user->about,
                'karma' => $user->karma,
                'is_admin' => $user->is_admin,
                'is_moderator' => $user->is_moderator,
                'created_at' => $user->created_at,
                'time_ago' => $this->timeAgo($user->created_at)
            ];
        })->toArray();
    }
    
    /**
     * Sort mixed results with advanced relevance scoring
     */
    private function sortMixedResultsAdvanced(array $results, string $order, array $searchTerms): array
    {
        if ($order === 'relevance') {
            usort($results, function ($a, $b) use ($searchTerms) {
                $aScore = $this->searchIndexService->calculateRelevanceScore($searchTerms, $a, [
                    'score' => $a['score'] ?? 0,
                    'comments_count' => $a['comment_count'] ?? 0,
                    'user_karma' => $a['user']['karma'] ?? 0
                ]);
                
                $bScore = $this->searchIndexService->calculateRelevanceScore($searchTerms, $b, [
                    'score' => $b['score'] ?? 0,
                    'comments_count' => $b['comment_count'] ?? 0,
                    'user_karma' => $b['user']['karma'] ?? 0
                ]);
                
                return $bScore <=> $aScore;
            });
        } else {
            $results = $this->sortMixedResults($results, $order);
        }
        
        return $results;
    }
    
    /**
     * Highlight search terms in results
     */
    private function highlightResults(array $results, array $searchTerms): array
    {
        $highlighted = [];
        
        foreach ($results as $result) {
            $highlightedResult = $result;
            
            foreach ($searchTerms as $term) {
                $term = trim($term);
                if (empty($term)) continue;
                
                $highlightTag = '<mark>' . htmlspecialchars($term) . '</mark>';
                
                // Highlight in different fields based on result type
                switch ($result['type']) {
                    case 'story':
                        $highlightedResult['title'] = $this->highlightText($result['title'], $term, $highlightTag);
                        $highlightedResult['description'] = $this->highlightText($result['description'], $term, $highlightTag);
                        break;
                        
                    case 'comment':
                        $highlightedResult['comment'] = $this->highlightText($result['comment'], $term, $highlightTag);
                        break;
                        
                    case 'user':
                        $highlightedResult['username'] = $this->highlightText($result['username'], $term, $highlightTag);
                        $highlightedResult['about'] = $this->highlightText($result['about'] ?? '', $term, $highlightTag);
                        break;
                }
            }
            
            $highlighted[] = $highlightedResult;
        }
        
        return $highlighted;
    }
    
    /**
     * Highlight specific text with search term
     */
    private function highlightText(string $text, string $term, string $highlightTag): string
    {
        return preg_replace('/(' . preg_quote($term, '/') . ')/i', $highlightTag, $text);
    }
    
    /**
     * Search usernames for suggestions
     */
    private function searchUsernames(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        return User::where('username', 'LIKE', "%{$query}%")
            ->where('deleted', false)
            ->orderBy('karma', 'desc')
            ->limit(10)
            ->pluck('username')
            ->toArray();
    }
    
    /**
     * Calculate suggestion relevance score
     */
    private function calculateSuggestionScore(string $query, string $suggestion): float
    {
        $query = strtolower($query);
        $suggestion = strtolower($suggestion);
        
        // Exact match
        if ($query === $suggestion) {
            return 1.0;
        }
        
        // Starts with
        if (strpos($suggestion, $query) === 0) {
            return 0.9;
        }
        
        // Contains
        if (strpos($suggestion, $query) !== false) {
            return 0.7;
        }
        
        // Levenshtein distance for fuzzy matching
        $maxLen = max(strlen($query), strlen($suggestion));
        $distance = levenshtein($query, $suggestion);
        
        return max(0, 1 - ($distance / $maxLen));
    }
    
    /**
     * Generate cache key for search results
     */
    private function generateCacheKey(string $query, string $type, string $order, int $page, array $filters): string
    {
        $key = 'search:' . md5(json_encode([
            'query' => $query,
            'type' => $type,
            'order' => $order,
            'page' => $page,
            'filters' => $filters
        ]));
        
        return $key;
    }
    
    /**
     * Track search for analytics
     */
    private function trackSearch(string $query, string $type, array $filters, int $resultsCount, ?int $userId, float $searchTimeMs): void
    {
        try {
            SearchAnalytic::create([
                'user_id' => $userId,
                'query' => $query,
                'query_normalized' => strtolower(trim($query)),
                'type' => $type,
                'filters' => $filters,
                'results_count' => $resultsCount,
                'search_time_ms' => round($searchTimeMs, 2),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the search
            error_log('Failed to track search: ' . $e->getMessage());
        }
    }
}