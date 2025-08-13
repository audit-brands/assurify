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

            // Get flagged comments (score < -5 or reported)
            $flaggedComments = Comment::with(['user', 'story'])
                ->where('score', '<', -5)
                ->orWhere('is_flagged', true)
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($comment) {
                    return [
                        'type' => 'comment',
                        'id' => $comment->id,
                        'short_id' => $comment->short_id,
                        'comment' => $comment->comment,
                        'score' => $comment->score,
                        'user' => $comment->user->username ?? 'Unknown',
                        'user_id' => $comment->user_id,
                        'story' => [
                            'id' => $comment->story->id,
                            'short_id' => $comment->story->short_id,
                            'title' => $comment->story->title
                        ],
                        'created_at' => $comment->created_at,
                        'time_ago' => $this->timeAgo($comment->created_at),
                        'is_flagged' => $comment->is_flagged ?? false,
                        'flag_reason' => $comment->flag_reason ?? null,
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

    public function getModerationStats(): array
    {
        try {
            $stats = [
                'total_stories' => Story::count(),
                'total_comments' => Comment::where('is_deleted', false)->count(),
                'flagged_stories' => Story::where('is_flagged', true)->count(),
                'flagged_comments' => Comment::where('is_flagged', true)->where('is_deleted', false)->count(),
                'low_score_stories' => Story::where('score', '<', -3)->count(),
                'low_score_comments' => Comment::where('score', '<', -3)->where('is_deleted', false)->count(),
                'total_users' => User::count(),
                'banned_users' => User::where('banned_at', '!=', null)->count(),
            ];
        } catch (\Exception $e) {
            $stats = [
                'total_stories' => 0,
                'total_comments' => 0,
                'flagged_stories' => 0,
                'flagged_comments' => 0,
                'low_score_stories' => 0,
                'low_score_comments' => 0,
                'total_users' => 0,
                'banned_users' => 0,
            ];
        }

        return $stats;
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
                    $comment->is_flagged = false;
                    $comment->flag_reason = null;
                    break;
                    
                case 'delete':
                    $comment->is_deleted = true;
                    $comment->deleted_at = date('Y-m-d H:i:s');
                    $comment->deleted_by = $moderator->id;
                    break;
                    
                case 'flag':
                    $comment->is_flagged = true;
                    $comment->flag_reason = $reason;
                    break;
                    
                case 'unflag':
                    $comment->is_flagged = false;
                    $comment->flag_reason = null;
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

    public function getModerationLog(int $limit = 50): array
    {
        // For now, return empty array - in real implementation would query moderation_log table
        return [];
    }

    private function logModerationAction(string $type, int $itemId, string $action, int $moderatorId, ?string $reason = null): void
    {
        // In a real implementation, this would insert into a moderation_log table
        // For now, we'll skip logging to avoid database dependency
    }

    public function isUserModerator(User $user): bool
    {
        // Check if user has moderation privileges
        return $user->is_moderator ?? false;
    }

    public function isUserAdmin(User $user): bool
    {
        // Check if user has admin privileges
        return $user->is_admin ?? false;
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
}