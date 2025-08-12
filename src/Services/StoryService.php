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
        $story->upvotes = 1;
        $story->downvotes = 0;
        $story->user_is_author = $data['user_is_author'] ?? false;

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

    public function findDuplicateUrl(string $url): ?Story
    {
        // Normalize URL for comparison
        $normalizedUrl = $this->normalizeUrl($url);

        return Story::where('url', $normalizedUrl)
                   ->where('is_expired', false)
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
            $query = Story::where('is_expired', false)
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

    public function getStoriesByTag(string $tagName, int $limit = 25): array
    {
        try {
            $stories = Story::whereHas('tags', function ($query) use ($tagName) {
                             $query->where('tag', $tagName);
            })
                         ->where('is_expired', false)
                         ->where('is_moderated', false)
                         ->with(['user', 'tags'])
                         ->orderBy('created_at', 'desc')
                         ->take($limit)
                         ->get();

            return $this->formatStoriesForView($stories);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function formatStoriesForView($stories): array
    {
        return $stories->map(function ($story) {
            return [
                'id' => $story->id,
                'short_id' => $story->short_id,
                'title' => $story->title,
                'url' => $story->url ?: "/s/{$story->short_id}/" . $this->generateSlug($story->title),
                'slug' => $this->generateSlug($story->title),
                'description' => $story->description,
                'markeddown_description' => $story->markeddown_description,
                'domain' => $story->url ? $this->extractDomain($story->url) : 'self',
                'score' => $story->score,
                'upvotes' => $story->upvotes,
                'downvotes' => $story->downvotes,
                'comments_count' => $story->comments_count,
                'username' => $story->user->username,
                'user_id' => $story->user_id,
                'user_is_author' => $story->user_is_author,
                'created_at' => $story->created_at,
                'time_ago' => $this->timeAgo($story->created_at),
                'tags' => $story->tags->pluck('tag')->toArray(),
            ];
        })->toArray();
    }

    public function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug, 0, 50);
    }

    public function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return preg_replace('/^www\./', '', $host);
    }

    public function timeAgo(Carbon $date): string
    {
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
