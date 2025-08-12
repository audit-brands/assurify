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

            $commentData = [
                'comment' => $data['comment'] ?? '',
                'parent_comment_id' => $data['parent_comment_id'] ?? null,
            ];

            $comment = $this->commentService->createComment($user, $story, $commentData);

            return $this->json($response, [
                'success' => true,
                'comment_id' => $comment->id,
                'short_id' => $comment->short_id,
                'redirect' => "/s/{$story->short_id}#comment-{$comment->short_id}"
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

    public function commentsFeed(Request $request, Response $response): Response
    {
        $rssContent = $this->feedService->generateCommentsFeed();
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
