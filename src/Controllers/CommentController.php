<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CommentService;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class CommentController extends BaseController
{
    public function __construct(
        Engine $templates,
        private CommentService $commentService,
        private FeedService $feedService
    ) {
        parent::__construct($templates);
    }

    public function store(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Authentication required'], 401);
        }

        $data = $request->getParsedBody();
        
        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $story = \App\Models\Story::find($data['story_id'] ?? 0);

            if (!$story) {
                return $this->json($response, ['error' => 'Story not found'], 404);
            }

            // Add submission token to prevent duplicate requests (optional for now)
            $submissionToken = $data['submission_token'] ?? null;
            
            if ($submissionToken) {
                // Check if this token was already used (store in session temporarily)
                $sessionKey = 'comment_tokens';
                if (!isset($_SESSION[$sessionKey])) {
                    $_SESSION[$sessionKey] = [];
                }
                
                if (in_array($submissionToken, $_SESSION[$sessionKey])) {
                    return $this->json($response, ['error' => 'Duplicate submission detected'], 400);
                }
                
                // Mark token as used
                $_SESSION[$sessionKey][] = $submissionToken;
                
                // Clean up old tokens (keep only last 10)
                $_SESSION[$sessionKey] = array_slice($_SESSION[$sessionKey], -10);
            }

            $commentData = [
                'comment' => $data['comment'] ?? '',
                'parent_comment_id' => $data['parent_comment_id'] ?? null,
            ];

            $comment = $this->commentService->createComment($user, $story, $commentData);

            // Generate story slug for proper URL
            $storySlug = $this->generateSlug($story->title);
            
            return $this->json($response, [
                'success' => true,
                'comment_id' => $comment->id,
                'short_id' => $comment->short_id,
                'redirect' => "/s/{$story->short_id}/{$storySlug}#comment-{$comment->short_id}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
    }

    public function vote(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Authentication required'], 401);
        }

        $data = $request->getParsedBody();
        $commentId = (int) $args['id'];
        $vote = (int) ($data['vote'] ?? 0);

        if (!in_array($vote, [1, -1])) {
            return $this->json($response, ['error' => 'Invalid vote value'], 400);
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $comment = \App\Models\Comment::find($commentId);

            if (!$comment) {
                return $this->json($response, ['error' => 'Comment not found'], 404);
            }

            // Users can't vote on their own comments
            if ($comment->user_id === $user->id) {
                return $this->json($response, ['error' => 'Cannot vote on your own comment'], 403);
            }

            $voted = $this->commentService->castVote($comment, $user, $vote);

            return $this->json($response, [
                'success' => true,
                'voted' => $voted,
                'score' => $comment->fresh()->score,
                'upvotes' => $comment->fresh()->upvotes,
                'downvotes' => $comment->fresh()->downvotes,
            ]);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $shortId = $args['id'];
        
        $comment = $this->commentService->getCommentByShortId($shortId);
        if (!$comment) {
            return $this->render($response, 'comments/not-found', [
                'title' => 'Comment Not Found | Lobsters'
            ]);
        }

        $commentData = $this->commentService->formatCommentsForView(collect([$comment]))[0];

        return $this->render($response, 'comments/show', [
            'title' => 'Comment | Lobsters',
            'comment' => $commentData,
            'story' => $comment->story
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Authentication required'], 401);
        }

        $commentId = (int) $args['id'];
        
        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $comment = \App\Models\Comment::find($commentId);

            if (!$comment) {
                return $this->json($response, ['error' => 'Comment not found'], 404);
            }

            $deleted = $this->commentService->deleteComment($comment, $user);

            if (!$deleted) {
                return $this->json($response, ['error' => 'Permission denied'], 403);
            }

            return $this->json($response, ['success' => true]);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function flag(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Authentication required'], 401);
        }

        $commentId = (int) $args['id'];
        
        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $comment = \App\Models\Comment::find($commentId);

            if (!$comment) {
                return $this->json($response, ['error' => 'Comment not found'], 404);
            }

            $flagged = $this->commentService->flagComment($comment, $user);

            return $this->json($response, [
                'success' => $flagged,
                'message' => 'Comment flagged for moderation'
            ]);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request, Response $response): Response
    {
        // Match Lobste.rs COMMENTS_PER_PAGE constant
        $commentsPerPage = 20;
        
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        
        // Validate page bounds (matching Lobste.rs logic)
        if ($page < 1 || $page > (2**31)) {
            throw new \InvalidArgumentException('page out of bounds');
        }
        
        try {
            // Get comments with pagination
            $offset = ($page - 1) * $commentsPerPage;
            $comments = $this->commentService->getRecentCommentsWithPagination(
                $commentsPerPage, 
                $offset
            );
            
            // Check if there are more comments for next page
            $hasMore = count($comments) >= $commentsPerPage;
            
        } catch (\Exception $e) {
            $comments = [];
            $hasMore = false;
        }

        // Calculate total comments for proper pagination
        try {
            $totalComments = $this->commentService->getTotalComments();
            $totalPages = (int) ceil($totalComments / $commentsPerPage);
        } catch (\Exception $e) {
            $totalComments = 0;
            $totalPages = 1;
        }

        // Create pagination data matching Lobste.rs pattern
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'base_url' => '/comments'
        ];

        return $this->render($response, 'comments/index', [
            'title' => 'Comments | Assurify',
            'comments' => $comments,
            'pagination' => $pagination
        ]);
    }

    public function commentsFeed(Request $request, Response $response): Response
    {
        $rssContent = $this->feedService->generateCommentsFeed();
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }
}
