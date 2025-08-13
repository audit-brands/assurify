<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Story;
use App\Models\Comment;
use App\Models\Vote;
use App\Models\Hat;

class UserService
{
    public function getUserByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function getUserProfile(string $username): ?array
    {
        $user = $this->getUserByUsername($username);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'karma' => $user->karma ?? 0,
            'created_at' => $user->created_at,
            'created_at_formatted' => $user->created_at ? $user->created_at->format('M j, Y') : 'Unknown',
            'about' => $user->about,
            'is_admin' => $user->is_admin,
            'is_moderator' => $user->is_moderator,
            'homepage' => $user->homepage,
            'github_username' => $user->github_username,
            'twitter_username' => $user->twitter_username,
            'show_email' => $user->show_email,
            'email' => $user->show_email ? $user->email : null,
            'avatar_file_name' => $user->avatar_file_name,
            'hats' => $this->getUserHats($user),
            'stats' => $this->getUserStats($user)
        ];
    }

    public function getUserStats(User $user): array
    {
        $storiesCount = Story::where('user_id', $user->id)
                           ->where('is_deleted', false)
                           ->count();

        $commentsCount = Comment::where('user_id', $user->id)
                              ->where('is_deleted', false)
                              ->count();

        $storiesSubmitted = Story::where('user_id', $user->id)
                                ->where('is_deleted', false)
                                ->orderBy('created_at', 'desc')
                                ->limit(10)
                                ->get();

        $commentsPosted = Comment::where('user_id', $user->id)
                               ->where('is_deleted', false)
                               ->with(['story'])
                               ->orderBy('created_at', 'desc')
                               ->limit(10)
                               ->get();

        // Calculate karma from votes received
        $storyKarma = Vote::whereIn('story_id', 
                         Story::where('user_id', $user->id)->pluck('id'))
                         ->sum('vote');

        $commentKarma = Vote::whereIn('comment_id',
                           Comment::where('user_id', $user->id)->pluck('id'))
                           ->sum('vote');

        $totalKarma = $storyKarma + $commentKarma;

        return [
            'stories_count' => $storiesCount,
            'comments_count' => $commentsCount,
            'total_karma' => $totalKarma,
            'story_karma' => $storyKarma,
            'comment_karma' => $commentKarma,
            'recent_stories' => $this->formatStoriesForDisplay($storiesSubmitted),
            'recent_comments' => $this->formatCommentsForDisplay($commentsPosted)
        ];
    }

    public function getUserHats(User $user): array
    {
        return Hat::where('user_id', $user->id)
                 ->whereNull('doffed_at')
                 ->with(['grantedBy'])
                 ->orderBy('created_at', 'desc')
                 ->get()
                 ->map(function ($hat) {
                     return [
                         'id' => $hat->id,
                         'hat' => $hat->hat,
                         'link' => $hat->link,
                         'granted_by' => $hat->grantedBy ? $hat->grantedBy->username : 'System',
                         'created_at' => $hat->created_at,
                         'created_at_formatted' => $hat->created_at->format('M j, Y')
                     ];
                 })
                 ->toArray();
    }

    public function updateUserKarma(User $user): void
    {
        $stats = $this->getUserStats($user);
        $user->karma = $stats['total_karma'];
        $user->save();
    }

    public function recalculateAllKarma(): int
    {
        $users = User::all();
        $updated = 0;

        foreach ($users as $user) {
            $oldKarma = $user->karma;
            $this->updateUserKarma($user);
            if ($user->karma !== $oldKarma) {
                $updated++;
            }
        }

        return $updated;
    }

    private function formatStoriesForDisplay($stories): array
    {
        if (empty($stories)) {
            return [];
        }

        return $stories->map(function ($story) {
            return [
                'id' => $story->id,
                'title' => $story->title,
                'url' => $story->url,
                'short_id' => $story->short_id,
                'slug' => $this->generateSlug($story->title),
                'score' => $story->score ?? 0,
                'comments_count' => $story->comments_count ?? 0,
                'created_at' => $story->created_at,
                'created_at_formatted' => $story->created_at ? $story->created_at->format('M j, Y') : 'Unknown',
                'time_ago' => $this->timeAgo($story->created_at),
                'domain' => $this->extractDomain($story->url)
            ];
        })->toArray();
    }

    private function formatCommentsForDisplay($comments): array
    {
        if (empty($comments)) {
            return [];
        }

        return $comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'short_id' => $comment->short_id,
                'comment' => $comment->comment,
                'markeddown_comment' => $comment->markeddown_comment,
                'score' => $comment->score ?? 0,
                'created_at' => $comment->created_at,
                'created_at_formatted' => $comment->created_at ? $comment->created_at->format('M j, Y') : 'Unknown',
                'time_ago' => $this->timeAgo($comment->created_at),
                'story_title' => $comment->story ? $comment->story->title : 'Unknown Story',
                'story_short_id' => $comment->story ? $comment->story->short_id : '',
                'story_slug' => $comment->story ? $this->generateSlug($comment->story->title) : ''
            ];
        })->toArray();
    }

    private function timeAgo($date): string
    {
        if (!$date) {
            return 'unknown';
        }
        
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    private function extractDomain(?string $url): string
    {
        if (!$url) {
            return '';
        }
        
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    public function getUserSettings(User $user): array
    {
        return [
            'show_avatars' => $user->show_avatars ?? true,
            'show_story_previews' => $user->show_story_previews ?? false,
            'show_read_ribbons' => $user->show_read_ribbons ?? true,
            'hide_dragons' => $user->hide_dragons ?? false,
            'show_email' => $user->show_email ?? false,
            'homepage' => $user->homepage ?? '',
            'github_username' => $user->github_username ?? '',
            'twitter_username' => $user->twitter_username ?? '',
            'about' => $user->about ?? ''
        ];
    }

    public function updateUserSettings(User $user, array $settings): bool
    {
        try {
            $user->show_avatars = $settings['show_avatars'] ?? $user->show_avatars;
            $user->show_story_previews = $settings['show_story_previews'] ?? $user->show_story_previews;
            $user->show_read_ribbons = $settings['show_read_ribbons'] ?? $user->show_read_ribbons;
            $user->hide_dragons = $settings['hide_dragons'] ?? $user->hide_dragons;
            $user->show_email = $settings['show_email'] ?? $user->show_email;
            $user->homepage = trim($settings['homepage'] ?? '');
            $user->github_username = trim($settings['github_username'] ?? '');
            $user->twitter_username = trim($settings['twitter_username'] ?? '');
            $user->about = trim($settings['about'] ?? '');
            $user->updated_at = date('Y-m-d H:i:s');
            
            return $user->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUserTagPreferences(User $user): array
    {
        try {
            // Get user's filtered tags (tags they want to hide)
            $filteredTags = json_decode($user->filtered_tags ?? '[]', true);
            
            // Get user's favorite tags (tags they want to highlight/prioritize)
            $favoriteTags = json_decode($user->favorite_tags ?? '[]', true);
            
            return [
                'filtered_tags' => is_array($filteredTags) ? $filteredTags : [],
                'favorite_tags' => is_array($favoriteTags) ? $favoriteTags : []
            ];
        } catch (\Exception $e) {
            return [
                'filtered_tags' => [],
                'favorite_tags' => []
            ];
        }
    }

    public function updateUserTagPreferences(User $user, array $filteredTags, array $favoriteTags): bool
    {
        try {
            // Validate and sanitize tags
            $filteredTags = array_map('strtolower', array_map('trim', $filteredTags));
            $favoriteTags = array_map('strtolower', array_map('trim', $favoriteTags));
            
            // Remove duplicates and empty values
            $filteredTags = array_values(array_unique(array_filter($filteredTags)));
            $favoriteTags = array_values(array_unique(array_filter($favoriteTags)));
            
            // Limit to reasonable numbers
            $filteredTags = array_slice($filteredTags, 0, 50);
            $favoriteTags = array_slice($favoriteTags, 0, 20);
            
            // Save to user
            $user->filtered_tags = json_encode($filteredTags);
            $user->favorite_tags = json_encode($favoriteTags);
            $user->updated_at = date('Y-m-d H:i:s');
            
            return $user->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function filterStoriesByUserPreferences(User $user, $stories)
    {
        $tagPreferences = $this->getUserTagPreferences($user);
        $filteredTags = $tagPreferences['filtered_tags'];
        $favoriteTags = $tagPreferences['favorite_tags'];
        
        if (empty($filteredTags) && empty($favoriteTags)) {
            return $stories;
        }
        
        // Filter out stories with filtered tags
        $filteredStories = $stories->filter(function ($story) use ($filteredTags) {
            if (empty($filteredTags)) {
                return true;
            }
            
            // Get story tags
            $storyTags = [];
            if (isset($story['tags']) && is_array($story['tags'])) {
                $storyTags = array_map('strtolower', $story['tags']);
            } elseif (isset($story->tags)) {
                $storyTags = $story->tags->pluck('tag')->map(fn($tag) => strtolower($tag))->toArray();
            }
            
            // Check if any story tag is in filtered tags
            return empty(array_intersect($storyTags, $filteredTags));
        });
        
        // Boost stories with favorite tags (move to top)
        if (!empty($favoriteTags)) {
            $favoriteStories = $filteredStories->filter(function ($story) use ($favoriteTags) {
                $storyTags = [];
                if (isset($story['tags']) && is_array($story['tags'])) {
                    $storyTags = array_map('strtolower', $story['tags']);
                } elseif (isset($story->tags)) {
                    $storyTags = $story->tags->pluck('tag')->map(fn($tag) => strtolower($tag))->toArray();
                }
                
                return !empty(array_intersect($storyTags, $favoriteTags));
            });
            
            $regularStories = $filteredStories->diff($favoriteStories);
            
            return $favoriteStories->concat($regularStories);
        }
        
        return $filteredStories;
    }
}