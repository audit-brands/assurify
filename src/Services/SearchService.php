<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    private const PER_PAGE = 20;
    private const MAX_RESULTS = 400; // 20 pages max

    public function search(string $query, string $type = 'all', string $order = 'newest', int $page = 1): array
    {
        $query = trim($query);
        
        if (empty($query)) {
            return [
                'results' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'type' => $type
            ];
        }

        $results = [];
        $total = 0;

        switch ($type) {
            case 'stories':
                $searchResults = $this->searchStories($query, $order, $page);
                $results = $this->formatStoriesForSearch($searchResults);
                $total = $this->countStoryResults($query);
                break;
                
            case 'comments':
                $searchResults = $this->searchComments($query, $order, $page);
                $results = $this->formatCommentsForSearch($searchResults);
                $total = $this->countCommentResults($query);
                break;
                
            case 'all':
            default:
                $storyResults = $this->searchStories($query, $order, $page, self::PER_PAGE / 2);
                $commentResults = $this->searchComments($query, $order, $page, self::PER_PAGE / 2);
                
                $results = array_merge(
                    $this->formatStoriesForSearch($storyResults),
                    $this->formatCommentsForSearch($commentResults)
                );
                
                // Sort mixed results by relevance or date
                $results = $this->sortMixedResults($results, $order);
                
                $total = $this->countStoryResults($query) + $this->countCommentResults($query);
                break;
        }

        return [
            'results' => $results,
            'total' => min($total, self::MAX_RESULTS),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'type' => $type,
            'query' => $query
        ];
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

    public function getPopularSearches(): array
    {
        // For now, return some common searches
        // In a real implementation, you'd track search queries
        return [
            'php', 'javascript', 'python', 'security', 'programming',
            'web development', 'machine learning', 'api', 'database'
        ];
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
}