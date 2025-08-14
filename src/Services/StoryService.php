<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use App\Models\Tag;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Michelf\Markdown;

class StoryService
{
    public function __construct(private TagService $tagService)
    {
    }

    public function generateShortId(): string
    {
        // Generate a unique 6-character alphanumeric ID
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $shortId = '';
            for ($i = 0; $i < 6; $i++) {
                $shortId .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Story::where('short_id', $shortId)->exists());

        return $shortId;
    }

    public function createStory(User $user, array $data): Story
    {
        // Validate input
        $this->validateStoryData($data);

        // Check for URL duplication
        if (!empty($data['url'])) {
            $existingStory = $this->findDuplicateUrl($data['url']);
            if ($existingStory) {
                throw new \Exception("This URL has already been submitted: /s/{$existingStory->short_id}");
            }
        }

        // Create story
        $story = new Story();
        $story->user_id = $user->id;
        $story->title = trim($data['title']);
        $story->url = trim($data['url'] ?? '');
        $story->description = trim($data['description'] ?? '');
        $story->short_id = $this->generateShortId();
        $story->score = 1; // Initial score from submitter
        $story->user_is_author = $data['user_is_author'] ?? false;
        $story->normalized_url = $story->url ? $this->normalizeUrl($story->url) : null;
        $story->token = bin2hex(random_bytes(16));
        $story->created_at = date('Y-m-d H:i:s');
        $story->updated_at = date('Y-m-d H:i:s');
        $story->last_edited_at = date('Y-m-d H:i:s');

        // Process markdown for description
        if ($story->description) {
            $story->markeddown_description = Markdown::defaultTransform($story->description);
        }

        $story->save();

        // Add submitter's upvote
        $this->castVote($story, $user, 1);

        // Process tags
        if (!empty($data['tags'])) {
            $this->tagService->tagStory($story, $data['tags'], $user);
        }

        return $story;
    }

    public function updateStoryTags(\App\Models\Story $story, string $tags): void
    {
        // Remove existing tags
        $story->tags()->detach();
        
        // Add new tags
        if (!empty(trim($tags))) {
            // Get the story author for tag validation
            $user = \App\Models\User::find($story->user_id);
            if ($user) {
                $this->tagService->tagStory($story, $tags, $user);
            }
        }
    }

    public function findDuplicateUrl(string $url): ?Story
    {
        // Normalize URL for comparison
        $normalizedUrl = $this->normalizeUrl($url);

        return Story::where('normalized_url', $normalizedUrl)
                   ->where('is_deleted', false)
                   ->first();
    }

    public function normalizeUrl(string $url): string
    {
        // Remove common URL variations for deduplication
        $url = trim($url);
        $url = preg_replace('/^https?:\/\//', 'https://', $url);
        $url = preg_replace('/^https:\/\/www\./', 'https://', $url);
        $url = rtrim($url, '/');

        // Remove tracking parameters
        $url = preg_replace('/[?&](utm_|fbclid|gclid)[^&]*/', '', $url);
        $url = preg_replace('/[?&]$/', '', $url);

        return $url;
    }

    public function getStoryByShortId(string $shortId): ?Story
    {
        try {
            return Story::where('short_id', $shortId)
                       ->with(['user', 'tags', 'votes'])
                       ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getStoriesForListing(string $sort = 'hot', int $limit = 25, int $offset = 0): array
    {
        try {
            $query = Story::where('is_deleted', false)
                         ->where('is_moderated', false)
                         ->with(['user', 'tags']);

            switch ($sort) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'recent':
                    $query->orderBy('updated_at', 'desc');
                    break;
                case 'top':
                    $query->orderBy('score', 'desc')
                          ->orderBy('created_at', 'desc');
                    break;
                case 'hot':
                default:
                    $query->orderBy('score', 'desc')
                          ->orderBy('created_at', 'desc');
                    break;
            }

            $stories = $query->skip($offset)
                            ->take($limit)
                            ->get();

            return $this->formatStoriesForView($stories);
        } catch (\Exception $e) {
            // Database connection failed - return empty array
            return [];
        }
    }

    public function getRecentStories(int $limit = 25): array
    {
        return $this->getStoriesForListing('recent', $limit);
    }

    public function getNewestStories(int $limit = 25): array
    {
        return $this->getStoriesForListing('newest', $limit);
    }

    public function getTopStories(int $limit = 25): array
    {
        return $this->getStoriesForListing('top', $limit);
    }

    public function getHotStories(int $limit = 25): array
    {
        return $this->getStoriesForListing('hot', $limit);
    }

    public function getStories(int $limit = 25, int $offset = 0, string $sort = 'hot'): array
    {
        return $this->getStoriesForListing($sort, $limit, $offset);
    }

    public function getTotalStories(): int
    {
        try {
            return Story::where('is_deleted', false)
                       ->where('is_moderated', false)
                       ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getStoriesByTag(string $tagName, int $page = 1, string $sort = 'hottest', string $timeframe = 'all', int $limit = 25): array
    {
        try {
            $query = Story::whereHas('tags', function ($query) use ($tagName) {
                             $query->where('tag', $tagName);
            })
                         ->where('is_expired', false)
                         ->where('is_moderated', false)
                         ->with(['user', 'tags']);

            // Apply timeframe filter
            switch ($timeframe) {
                case 'day':
                    $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 day')));
                    break;
                case 'week':
                    $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')));
                    break;
                case 'month':
                    $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 month')));
                    break;
                case 'year':
                    $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 year')));
                    break;
                case 'all':
                default:
                    // No time filter
                    break;
            }

            // Apply sorting
            switch ($sort) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'most_comments':
                    $query->orderBy('comments_count', 'desc');
                    break;
                case 'hottest':
                default:
                    $query->orderByRaw('(upvotes - downvotes) / POWER((JULIANDAY("now") - JULIANDAY(created_at)) * 24 + 2, 1.5) DESC');
                    break;
            }

            $offset = ($page - 1) * $limit;
            $stories = $query->offset($offset)->take($limit)->get();

            return $this->formatStoriesForView($stories);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function formatStoriesForView($stories): array
    {
        if (empty($stories)) {
            return [];
        }

        // Keep as collection/objects, don't convert to array
        if (is_object($stories) && method_exists($stories, 'all')) {
            $stories = $stories->all();
        }

        if (!is_array($stories)) {
            return [];
        }

        $formatted = [];
        foreach ($stories as $story) {
            // Story should be an object at this point
            $title = $story->title ?? 'Untitled';
            $storyData = [
                'id' => $story->id,
                'short_id' => $story->short_id,
                'title' => $title,
                'description' => $story->description ?? '',
                'markeddown_description' => $story->markeddown_description ?? '',
                'score' => $story->score ?? 1,
                'upvotes' => $story->upvotes ?? 1,
                'downvotes' => $story->downvotes ?? 0,
                'comments_count' => $story->comments_count ?? 0,
                'user_id' => $story->user_id,
                'user_is_author' => $story->user_is_author ?? false,
                'created_at' => $story->created_at,
                'username' => $story->user->username ?? 'Unknown',
                'time_ago' => $this->timeAgo($story->created_at),
                'created_at_formatted' => $this->timeAgo($story->created_at),
                'tags' => []
            ];
            
            // Handle URL - use story URL or generate permalink
            $storyUrl = $story->url ?? '';
            $storyData['url'] = $storyUrl ?: "/s/" . $storyData['short_id'] . "/" . $this->generateSlug($title);
            $storyData['slug'] = $this->generateSlug($title);
            $storyData['domain'] = $storyUrl ? $this->extractDomain($storyUrl) : 'self';
            
            // Handle tags - use simple iteration instead of Laravel helpers
            if (isset($story->tags)) {
                foreach ($story->tags as $tag) {
                    $storyData['tags'][] = $tag->tag;
                }
            }
            
            $formatted[] = $storyData;
        }
        
        return $formatted;
    }

    public function generateSlug(?string $title): string
    {
        if (empty($title)) {
            return 'untitled';
        }
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    public function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return preg_replace('/^www\./', '', $host);
    }

    public function timeAgo($date): string
    {
        if (!$date) {
            return 'unknown';
        }
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->diffForHumans();
    }

    public function castVote(Story $story, User $user, int $vote): bool
    {
        if ($vote !== 1 && $vote !== -1) {
            throw new \InvalidArgumentException('Vote must be 1 or -1');
        }

        // Check if user has already voted
        $existingVote = Vote::where('story_id', $story->id)
                          ->where('user_id', $user->id)
                          ->first();

        if ($existingVote) {
            if ($existingVote->vote === $vote) {
                // Same vote - remove it
                $this->removeVote($story, $existingVote);
                return false;
            } else {
                // Different vote - update it
                $this->updateVote($story, $existingVote, $vote);
                return true;
            }
        } else {
            // New vote
            $this->addVote($story, $user, $vote);
            return true;
        }
    }

    private function addVote(Story $story, User $user, int $vote): void
    {
        $voteRecord = new Vote();
        $voteRecord->user_id = $user->id;
        $voteRecord->story_id = $story->id;
        $voteRecord->vote = $vote;
        $voteRecord->updated_at = date('Y-m-d H:i:s');
        $voteRecord->save();

        $this->updateStoryScore($story);
    }

    private function updateVote(Story $story, Vote $existingVote, int $vote): void
    {
        $existingVote->vote = $vote;
        $existingVote->save();

        $this->updateStoryScore($story);
    }

    private function removeVote(Story $story, Vote $existingVote): void
    {
        $existingVote->delete();
        $this->updateStoryScore($story);
    }

    private function updateStoryScore(Story $story): void
    {
        $votes = Vote::where('story_id', $story->id)->get();

        $upvotes = $votes->where('vote', 1)->count();
        $downvotes = $votes->where('vote', -1)->count();
        $score = $upvotes - $downvotes;

        $story->upvotes = $upvotes;
        $story->downvotes = $downvotes;
        $story->score = $score;
        $story->save();
    }

    private function validateStoryData(array $data): void
    {
        if (empty($data['title'])) {
            throw new \Exception('Title is required');
        }

        if (strlen($data['title']) > 150) {
            throw new \Exception('Title must be 150 characters or less');
        }

        if (empty($data['url']) && empty($data['description'])) {
            throw new \Exception('Either URL or description is required');
        }

        if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid URL format');
        }
    }
}
