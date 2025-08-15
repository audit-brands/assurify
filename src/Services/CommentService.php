<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Story;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Michelf\Markdown;

class CommentService
{
    public function generateShortId(): string
    {
        // Generate a unique 10-character alphanumeric ID
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $shortId = '';
            for ($i = 0; $i < 10; $i++) {
                $shortId .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Comment::where('short_id', $shortId)->exists());

        return $shortId;
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16)); // 32-character hex string
        } while (Comment::where('token', $token)->exists());

        return $token;
    }

    public function createComment(User $user, Story $story, array $data): Comment
    {
        // Validate input
        $this->validateCommentData($data);

        // Check for duplicate comments (same user, story, parent, content within last 30 seconds only)
        // This is a safety net to catch any remaining edge cases
        $commentText = trim($data['comment']);
        $parentCommentId = $data['parent_comment_id'] ?? null;
        
        $query = Comment::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->where('comment', $commentText)
            ->where('created_at', '>', Carbon::now()->subSeconds(30));
            
        // Handle null parent_comment_id properly
        if ($parentCommentId === null) {
            $query->whereNull('parent_comment_id');
        } else {
            $query->where('parent_comment_id', $parentCommentId);
        }
        
        $duplicateComment = $query->first();

        if ($duplicateComment) {
            throw new \Exception("Duplicate submission detected. Please wait a moment before posting again.");
        }

        // Create comment
        $comment = new Comment();
        $comment->user_id = $user->id;
        $comment->story_id = $story->id;
        $comment->parent_comment_id = $data['parent_comment_id'] ?? null;
        $comment->comment = trim($data['comment']);
        $comment->short_id = $this->generateShortId();
        $comment->score = 1; // Initial score from submitter
        $comment->upvotes = 1;
        $comment->downvotes = 0;
        $comment->created_at = date('Y-m-d H:i:s');
        $comment->updated_at = date('Y-m-d H:i:s');
        $comment->last_edited_at = date('Y-m-d H:i:s');
        $comment->token = $this->generateUniqueToken();

        // Process markdown for comment
        if ($comment->comment) {
            $comment->markeddown_comment = Markdown::defaultTransform($comment->comment);
        }

        // Set thread_id for threading
        if ($comment->parent_comment_id) {
            $parentComment = Comment::find($comment->parent_comment_id);
            $comment->thread_id = $parentComment->thread_id ?: $parentComment->short_id;
        } else {
            $comment->thread_id = null; // Top-level comment
        }

        $comment->save();

        // Perform post-creation operations (don't let failures here affect comment creation)
        try {
            // Add submitter's upvote (only if they haven't already voted on this story)
            $existingVote = Vote::where('user_id', $user->id)
                              ->where('story_id', $story->id)
                              ->first();
            
            if (!$existingVote) {
                $this->castVote($comment, $user, 1);
            }

            // Update story comment count
            $this->updateStoryCommentCount($story);
        } catch (\Exception $e) {
            // Log the error but don't fail the comment creation
            error_log("Post-comment creation operations failed: " . $e->getMessage());
        }

        return $comment;
    }

    public function getCommentsForStory(Story $story, string $sort = 'confidence', int $limit = 100): array
    {
        try {
            $query = Comment::where('story_id', $story->id)
                           ->where('is_deleted', false)
                           ->where('is_moderated', false)
                           ->with(['user', 'votes']);

            switch ($sort) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'score':
                    $query->orderBy('score', 'desc')
                          ->orderBy('created_at', 'asc');
                    break;
                case 'confidence':
                default:
                    $query->orderBy('confidence', 'desc')
                          ->orderBy('created_at', 'asc');
                    break;
            }

            $comments = $query->take($limit)->get();

            return $this->formatCommentsForView($comments);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function buildCommentTree(array $comments): array
    {
        $tree = [];
        $lookup = [];

        // First pass: create lookup table
        foreach ($comments as $comment) {
            $lookup[$comment['id']] = $comment;
            $lookup[$comment['id']]['replies'] = [];
        }

        // Second pass: build tree structure
        foreach ($comments as $comment) {
            if ($comment['parent_comment_id']) {
                if (isset($lookup[$comment['parent_comment_id']])) {
                    $lookup[$comment['parent_comment_id']]['replies'][] = &$lookup[$comment['id']];
                }
            } else {
                $tree[] = &$lookup[$comment['id']];
            }
        }

        return $tree;
    }

    public function formatCommentsForView($comments): array
    {
        if (empty($comments)) {
            return [];
        }

        // Convert collection to array if needed
        if (is_object($comments) && method_exists($comments, 'all')) {
            $comments = $comments->all();
        }

        $formatted = [];
        foreach ($comments as $comment) {
            $formatted[] = [
                'id' => $comment->id,
                'short_id' => $comment->short_id,
                'comment' => $comment->comment,
                'content' => $comment->markeddown_comment ?? $comment->comment,
                'markeddown_comment' => $comment->markeddown_comment,
                'score' => $comment->score ?? 0,
                'upvotes' => $comment->upvotes ?? 0,
                'downvotes' => $comment->downvotes ?? 0,
                'confidence' => $comment->confidence ?? 0,
                'parent_comment_id' => $comment->parent_comment_id,
                'thread_id' => $comment->thread_id,
                'username' => $comment->user->username ?? 'Unknown',
                'user_id' => $comment->user_id,
                'story_id' => $comment->story_id,
                'story_title' => $comment->story->title ?? 'Unknown Story',
                'story_short_id' => $comment->story->short_id ?? '',
                'story_slug' => $this->generateSlug($comment->story->title ?? 'unknown'),
                'is_deleted' => $comment->is_deleted,
                'is_moderated' => $comment->is_moderated,
                'created_at' => $comment->created_at,
                'time_ago' => $this->timeAgo($comment->created_at),
                'created_at_formatted' => $comment->created_at->format('Y-m-d H:i:s'),
            ];
        }
        
        return $formatted;
    }

    public function castVote(Comment $comment, User $user, int $vote): bool
    {
        if ($vote !== 1 && $vote !== -1) {
            throw new \InvalidArgumentException('Vote must be 1 or -1');
        }

        // Check if user has already voted
        $existingVote = Vote::where('comment_id', $comment->id)
                          ->where('user_id', $user->id)
                          ->first();

        if ($existingVote) {
            if ($existingVote->vote === $vote) {
                // Same vote - remove it
                $this->removeVote($comment, $existingVote);
                return false;
            } else {
                // Different vote - update it
                $this->updateVote($comment, $existingVote, $vote);
                return true;
            }
        } else {
            // New vote
            $this->addVote($comment, $user, $vote);
            return true;
        }
    }

    private function addVote(Comment $comment, User $user, int $vote): void
    {
        $voteRecord = new Vote();
        $voteRecord->user_id = $user->id;
        $voteRecord->story_id = $comment->story_id; // Required field - get from comment
        $voteRecord->comment_id = $comment->id;
        $voteRecord->vote = $vote;
        $voteRecord->updated_at = date('Y-m-d H:i:s');
        $voteRecord->save();

        $this->updateCommentScore($comment);
    }

    private function updateVote(Comment $comment, Vote $existingVote, int $vote): void
    {
        $existingVote->vote = $vote;
        $existingVote->updated_at = date('Y-m-d H:i:s');
        $existingVote->save();

        $this->updateCommentScore($comment);
    }

    private function removeVote(Comment $comment, Vote $existingVote): void
    {
        $existingVote->delete();
        $this->updateCommentScore($comment);
    }

    private function updateCommentScore(Comment $comment): void
    {
        $votes = Vote::where('comment_id', $comment->id)->get();

        $upvotes = $votes->where('vote', 1)->count();
        $downvotes = $votes->where('vote', -1)->count();
        $score = $upvotes - $downvotes;

        $comment->upvotes = $upvotes;
        $comment->downvotes = $downvotes;
        $comment->score = $score;
        
        // Calculate confidence score (Wilson score interval)
        $comment->confidence = $this->calculateConfidence($upvotes, $downvotes);
        
        $comment->save();
    }

    private function calculateConfidence(int $upvotes, int $downvotes): float
    {
        $total = $upvotes + $downvotes;
        
        if ($total === 0) {
            return 0.0;
        }

        $z = 1.96; // 95% confidence interval
        $p = $upvotes / $total;
        
        return ($p + $z * $z / (2 * $total) - $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $total)) / $total)) / (1 + $z * $z / $total);
    }

    private function updateStoryCommentCount(Story $story): void
    {
        $count = Comment::where('story_id', $story->id)
                       ->where('is_deleted', false)
                       ->where('is_moderated', false)
                       ->count();

        $story->comments_count = $count;
        $story->save();
    }


    public function getCommentByShortId(string $shortId): ?Comment
    {
        try {
            return Comment::where('short_id', $shortId)
                         ->with(['user', 'story', 'votes'])
                         ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function deleteComment(Comment $comment, User $user): bool
    {
        // Only comment author, moderators, or admins can delete
        if ($comment->user_id !== $user->id && !$user->is_moderator && !$user->is_admin) {
            return false;
        }

        $comment->is_deleted = true;
        $comment->comment = '[deleted]';
        $comment->markeddown_comment = '<p>[deleted]</p>';
        $comment->save();

        // Update story comment count
        $story = Story::find($comment->story_id);
        if ($story) {
            $this->updateStoryCommentCount($story);
        }

        return true;
    }

    public function flagComment(Comment $comment, User $user): bool
    {
        // Users can flag comments for moderation
        $comment->flags = $comment->flags + 1;
        $comment->save();

        return true;
    }

    public function getRecentComments(int $limit = 50): array
    {
        try {
            $comments = Comment::where('is_deleted', false)
                             ->with(['user', 'story'])
                             ->orderBy('created_at', 'desc')
                             ->take($limit)
                             ->get();

            return $this->formatCommentsForView($comments);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRecentCommentsWithPagination(int $limit = 20, int $offset = 0): array
    {
        try {
            // Match Lobste.rs query logic: accessible_to_user, not_on_story_hidden_by, filter_tags, etc.
            $comments = Comment::where('is_deleted', false)
                             ->where('is_moderated', false)
                             ->with(['user', 'story'])
                             ->whereHas('story', function($query) {
                                 $query->where('is_deleted', false);
                             })
                             ->orderBy('id', 'desc') // Match Lobste.rs ordering by id desc
                             ->skip($offset)
                             ->take($limit)
                             ->get();

            return $this->formatCommentsForView($comments);
        } catch (\Exception $e) {
            return [];
        }
    }


    private function timeAgo($date): string
    {
        if (!$date) {
            return 'unknown';
        }
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->diffForHumans();
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    private function validateCommentData(array $data): void
    {
        if (empty($data['comment'])) {
            throw new \Exception('Comment content is required');
        }

        if (strlen($data['comment']) > 65535) {
            throw new \Exception('Comment must be 65535 characters or less');
        }

        if (isset($data['parent_comment_id']) && !empty($data['parent_comment_id'])) {
            $parentExists = Comment::where('id', $data['parent_comment_id'])
                                  ->where('is_deleted', false)
                                  ->exists();
            if (!$parentExists) {
                throw new \Exception('Parent comment not found or deleted');
            }
        }
    }

    /**
     * Get total count of non-deleted comments
     */
    public function getTotalComments(): int
    {
        try {
            return Comment::where('is_deleted', false)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}