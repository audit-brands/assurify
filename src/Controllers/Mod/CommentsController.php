<?php

declare(strict_types=1);

namespace App\Controllers\Mod;

use App\Controllers\ModController;
use App\Models\Comment;
use App\Models\Moderation;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CommentsController extends ModController
{
    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->render($response, 'errors/404', [
                'title' => 'Comment Not Found | Assurify'
            ])->withStatus(404);
        }

        try {
            $comment = Comment::where('short_id', $shortId)->with(['user', 'story'])->firstOrFail();
            
            // Get moderation history for this comment
            $moderations = Moderation::where(function($query) use ($comment) {
                $query->where('comment_id', $comment->id)
                      ->orWhere(function($q) use ($comment) {
                          $q->where('subject_type', 'comment')
                            ->where('subject_id', $comment->id);
                      });
            })
            ->with('moderator')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

            return $this->render($response, 'mod/comments/edit', [
                'title' => 'Edit Comment | Moderation | Assurify',
                'comment' => $comment,
                'moderations' => $moderations
            ]);
        } catch (\Exception $e) {
            return $this->render($response, 'errors/404', [
                'title' => 'Comment Not Found | Assurify'
            ])->withStatus(404);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Comment not found'], 404);
        }

        try {
            $comment = Comment::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            // Store original content for logging
            $originalContent = $comment->comment;
            
            // Update comment content
            $newContent = trim($data['comment'] ?? '');
            if (empty($newContent)) {
                return $this->json($response, [
                    'error' => 'Comment content cannot be empty'
                ], 400);
            }
            
            $comment->comment = $newContent;
            $comment->moderation_reason = trim($data['moderation_reason'] ?? '') ?: null;
            $comment->updated_at = now();
            $comment->save();

            // Log the moderation action if content changed
            if ($originalContent !== $newContent) {
                $this->logModerationAction(
                    Moderation::ACTION_EDITED_COMMENT,
                    $comment,
                    $data['moderation_reason'] ?? null,
                    [
                        'original_content' => $originalContent,
                        'new_content' => $newContent
                    ]
                );
            }

            return $this->json($response, [
                'success' => true,
                'message' => 'Comment updated successfully',
                'redirect' => "/c/{$comment->short_id}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to update comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Comment not found'], 404);
        }

        try {
            $comment = Comment::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            $moderationReason = trim($data['reason'] ?? '');
            
            // Require reason for deleting other users' comments
            $currentUser = $this->getCurrentUser();
            if ($comment->user_id !== $currentUser->id && empty($moderationReason)) {
                return $this->json($response, [
                    'error' => 'Moderation reason is required when deleting other users\' comments'
                ], 400);
            }

            $comment->is_deleted = true;
            $comment->moderation_reason = $moderationReason ?: null;
            $comment->updated_at = now();
            $comment->save();

            // Log the moderation action
            $this->logModerationAction(
                Moderation::ACTION_DELETED_COMMENT,
                $comment,
                $moderationReason,
                ['deleted_by_author' => $comment->user_id === $currentUser->id]
            );

            return $this->json($response, [
                'success' => true,
                'message' => 'Comment deleted successfully',
                'redirect' => "/c/{$comment->short_id}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function undelete(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Comment not found'], 404);
        }

        try {
            $comment = Comment::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            $moderationReason = trim($data['reason'] ?? '');

            $comment->is_deleted = false;
            $comment->moderation_reason = $moderationReason ?: null;
            $comment->updated_at = now();
            $comment->save();

            // Log the moderation action
            $this->logModerationAction(
                Moderation::ACTION_UNDELETED_COMMENT,
                $comment,
                $moderationReason
            );

            return $this->json($response, [
                'success' => true,
                'message' => 'Comment undeleted successfully',
                'redirect' => "/c/{$comment->short_id}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to undelete comment: ' . $e->getMessage()
            ], 500);
        }
    }
}