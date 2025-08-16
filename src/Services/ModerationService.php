<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\Comment;
use App\Models\User;

class ModerationService
{
    public function getFlaggedContent(): array
    {
        $flaggedStories = [];
        $flaggedComments = [];

        try {
            // Get flagged stories (score < -5 or reported)
            $flaggedStories = Story::with(['user', 'tags'])
                ->where('score', '<', -5)
                ->orWhere('is_flagged', true)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($story) {
                    return [
                        'type' => 'story',
                        'id' => $story->id,
                        'short_id' => $story->short_id,
                        'title' => $story->title,
                        'description' => $story->description,
                        'url' => $story->url,
                        'score' => $story->score,
                        'user' => $story->user->username ?? 'Unknown',
                        'user_id' => $story->user_id,
                        'created_at' => $story->created_at,
                        'time_ago' => $this->timeAgo($story->created_at),
                        'is_flagged' => $story->is_flagged ?? false,
                        'flag_reason' => $story->flag_reason ?? null,
                    ];
                })->toArray();

            // Get flagged comments (score < -5 or has flags)
            $flaggedComments = Comment::with(['user', 'story'])
                ->where('score', '<', -5)
                ->orWhere('flags', '>', 0)
                ->where('is_deleted', false)
                ->orderBy('flags', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($comment) {
                    return [
                        'type' => 'comment',
                        'id' => $comment->id,
                        'short_id' => $comment->short_id,
                        'comment' => substr($comment->comment, 0, 200) . (strlen($comment->comment) > 200 ? '...' : ''),
                        'content' => $comment->markeddown_comment ?? $comment->comment,
                        'score' => $comment->score,
                        'flags' => $comment->flags,
                        'user' => $comment->user->username ?? 'Unknown',
                        'user_id' => $comment->user_id,
                        'story' => [
                            'id' => $comment->story->id,
                            'short_id' => $comment->story->short_id,
                            'title' => $comment->story->title
                        ],
                        'created_at' => $comment->created_at,
                        'time_ago' => $this->timeAgo($comment->created_at),
                        'is_flagged' => $comment->flags > 0,
                        'flag_count' => $comment->flags,
                    ];
                })->toArray();
        } catch (\Exception $e) {
            // Return empty arrays if database fails
        }

        return [
            'stories' => $flaggedStories,
            'comments' => $flaggedComments
        ];
    }


    public function moderateStory(int $storyId, string $action, User $moderator, ?string $reason = null): bool
    {
        try {
            $story = Story::find($storyId);
            if (!$story) {
                return false;
            }

            switch ($action) {
                case 'approve':
                    $story->is_flagged = false;
                    $story->flag_reason = null;
                    break;
                    
                case 'delete':
                    $story->is_deleted = true;
                    $story->deleted_at = date('Y-m-d H:i:s');
                    $story->deleted_by = $moderator->id;
                    break;
                    
                case 'flag':
                    $story->is_flagged = true;
                    $story->flag_reason = $reason;
                    break;
                    
                case 'unflag':
                    $story->is_flagged = false;
                    $story->flag_reason = null;
                    break;
                    
                default:
                    return false;
            }

            $story->save();
            
            // Log moderation action
            $this->logModerationAction('story', $storyId, $action, $moderator->id, $reason);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function moderateComment(int $commentId, string $action, User $moderator, ?string $reason = null): bool
    {
        try {
            $comment = Comment::find($commentId);
            if (!$comment) {
                return false;
            }

            switch ($action) {
                case 'approve':
                    $comment->flags = 0; // Clear all flags
                    $comment->is_moderated = true;
                    break;
                    
                case 'delete':
                    $comment->is_deleted = true;
                    $comment->comment = '[deleted]';
                    $comment->markeddown_comment = '<p>[deleted]</p>';
                    break;
                    
                case 'flag':
                    $comment->flags = ($comment->flags ?? 0) + 1;
                    break;
                    
                case 'unflag':
                    $comment->flags = max(0, ($comment->flags ?? 0) - 1);
                    break;
                    
                default:
                    return false;
            }

            $comment->save();
            
            // Log moderation action
            $this->logModerationAction('comment', $commentId, $action, $moderator->id, $reason);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function banUser(int $userId, User $moderator, string $reason, int $durationDays = 0): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            $user->banned_at = date('Y-m-d H:i:s');
            $user->banned_by = $moderator->id;
            $user->ban_reason = $reason;
            
            if ($durationDays > 0) {
                $user->banned_until = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
            }
            
            $user->save();
            
            // Log moderation action
            $this->logModerationAction('user', $userId, 'ban', $moderator->id, $reason);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function unbanUser(int $userId, User $moderator): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            $user->banned_at = null;
            $user->banned_by = null;
            $user->ban_reason = null;
            $user->banned_until = null;
            $user->save();
            
            // Log moderation action
            $this->logModerationAction('user', $userId, 'unban', $moderator->id);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    public function getTotalModerationCount(): int
    {
        // Placeholder - return 0 for now until moderation_log table is created
        return 0;
    }

    private function logModerationAction(string $type, int $itemId, string $action, int $moderatorId, ?string $reason = null): void
    {
        // In a real implementation, this would insert into a moderation_log table
        // For now, we'll skip logging to avoid database dependency
    }

    public function isUserModerator(User $user): bool
    {
        // Check if user has moderation privileges (moderators or admins)
        return $user->canModerate();
    }

    public function isUserAdmin(User $user): bool
    {
        // Check if user has admin privileges
        return $user->is_admin ?? false;
    }

    public function getModerationStats(): array
    {
        try {
            $totalStories = Story::count();
            $totalComments = Comment::count();
            $totalUsers = User::count();
            
            $flaggedStoriesCount = Story::where('score', '<', -5)
                ->orWhere('is_flagged', true)
                ->count();
            
            $flaggedCommentsCount = Comment::where('score', '<', -5)
                ->orWhere('flags', '>', 0)
                ->where('is_deleted', false)
                ->count();
            
            $lowScoreStories = Story::where('score', '<', -5)->count();
            $lowScoreComments = Comment::where('score', '<', -5)->count();
            
            $bannedUsersCount = User::whereNotNull('banned_at')->count();
            
            return [
                'total_stories' => $totalStories,
                'total_comments' => $totalComments,
                'total_users' => $totalUsers,
                'flagged_stories' => $flaggedStoriesCount,
                'flagged_comments' => $flaggedCommentsCount,
                'low_score_stories' => $lowScoreStories,
                'low_score_comments' => $lowScoreComments,
                'banned_users' => $bannedUsersCount,
                'total_flagged' => $flaggedStoriesCount + $flaggedCommentsCount
            ];
        } catch (\Exception $e) {
            return [
                'total_stories' => 0,
                'total_comments' => 0,
                'total_users' => 0,
                'flagged_stories' => 0,
                'flagged_comments' => 0,
                'low_score_stories' => 0,
                'low_score_comments' => 0,
                'banned_users' => 0,
                'total_flagged' => 0
            ];
        }
    }

    public function getModerationLog(int $limit = 20): array
    {
        try {
            return \App\Models\Moderation::with(['moderator'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($moderation) {
                    return [
                        'id' => $moderation->id,
                        'action' => $moderation->action,
                        'reason' => $moderation->reason,
                        'subject_type' => $moderation->subject_type,
                        'subject_title' => $moderation->subject_title,
                        'moderator' => $moderation->moderator->username ?? 'System',
                        'created_at' => $moderation->created_at,
                        'time_ago' => $this->timeAgo($moderation->created_at),
                        'ip_address' => $moderation->ip_address
                    ];
                })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function timeAgo($date): string
    {
        // Handle different date input types
        if (is_string($date)) {
            $date = new \DateTime($date);
        } elseif (!$date instanceof \DateTime) {
            // Handle Carbon dates or other date types
            $date = new \DateTime($date);
        }
        
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
}