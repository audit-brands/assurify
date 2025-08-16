<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StoryService;
use App\Services\TagService;
use App\Services\CommentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class StoryController extends BaseController
{
    public function __construct(
        Engine $templates,
        private StoryService $storyService,
        private TagService $tagService,
        private CommentService $commentService
    ) {
        parent::__construct($templates);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $shortId = $args['id'];
        $slug = $args['slug'];

        $story = $this->storyService->getStoryByShortId($shortId);
        if (!$story) {
            return $this->render($response, 'stories/not-found', [
                'title' => 'Story Not Found | Lobsters'
            ]);
        }

        $storyData = $this->storyService->formatStoriesForView(collect([$story]))[0];
        
        // Load comments for this story
        $comments = $this->commentService->getCommentsForStory($story);
        $commentTree = $this->commentService->buildCommentTree($comments);

        // Check if current user can edit this story and if they're a moderator
        $canEdit = false;
        $isModerator = false;
        if (isset($_SESSION['user_id'])) {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $canEdit = $story->isEditableByUser($user);
            $isModerator = $user && ($user->is_admin || $user->is_moderator);
        }

        return $this->render($response, 'stories/show', [
            'title' => $story->title . ' | Lobsters',
            'story' => $storyData,
            'comments' => $commentTree,
            'total_comments' => count($comments),
            'can_edit' => $canEdit,
            'is_moderator' => $isModerator
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['auth_redirect'] = '/stories';
            return $this->redirect($response, '/auth/login');
        }

        $suggestedTags = [];
        $url = $request->getQueryParams()['url'] ?? '';

        if ($url) {
            try {
                $suggestedTags = $this->tagService->getSuggestedTags('', '', $url);
            } catch (\Exception $e) {
                // If tag service fails, just use empty array
                $suggestedTags = [];
            }
        }

        // Get and clear any stored error
        $error = $_SESSION['story_error'] ?? null;
        unset($_SESSION['story_error']);

        // Get all tags for autocomplete
        $allTags = $this->tagService->getAllTags();

        return $this->render($response, 'stories/create', [
            'title' => 'Submit Story | Assurify',
            'url' => $url,
            'suggested_tags' => $suggestedTags,
            'all_tags' => $allTags,
            'error' => $error,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/auth/login');
        }

        $data = $request->getParsedBody();

        // Clear previous errors
        unset($_SESSION['story_error']);

        try {
            $storyData = [
                'title' => trim($data['title'] ?? ''),
                'url' => trim($data['url'] ?? ''),
                'description' => trim($data['description'] ?? ''),
                'tags' => trim($data['tags'] ?? ''),
                'user_is_author' => isset($data['user_is_author']),
            ];

            // Basic validation
            if (empty($storyData['title'])) {
                $_SESSION['story_error'] = 'Title is required';
                return $this->redirect($response, '/stories');
            }

            if (strlen($storyData['title']) > 150) {
                $_SESSION['story_error'] = 'Title must be 150 characters or less';
                return $this->redirect($response, '/stories');
            }

            // Get current user
            $user = \App\Models\User::find($_SESSION['user_id']);
            if (!$user) {
                $_SESSION['story_error'] = 'User not found';
                return $this->redirect($response, '/auth/login');
            }

            // Create the story using StoryService
            $story = $this->storyService->createStory($user, $storyData);

            $_SESSION['story_success'] = 'Story "' . $storyData['title'] . '" has been submitted successfully!';
            
            return $this->redirect($response, '/');
        } catch (\Exception $e) {
            $_SESSION['story_error'] = $e->getMessage();
            return $this->redirect($response, '/stories');
        }
    }

    public function vote(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Authentication required'], 401);
        }

        $data = $request->getParsedBody();
        $storyId = (int) $args['id'];
        $vote = (int) ($data['vote'] ?? 0);

        if (!in_array($vote, [1, -1])) {
            return $this->json($response, ['error' => 'Invalid vote value'], 400);
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $story = \App\Models\Story::find($storyId);

            if (!$story) {
                return $this->json($response, ['error' => 'Story not found'], 404);
            }

            // Users can't vote on their own stories
            if ($story->user_id === $user->id) {
                return $this->json($response, ['error' => 'Cannot vote on your own story'], 403);
            }

            $voted = $this->storyService->castVote($story, $user, $vote);

            return $this->json($response, [
                'success' => true,
                'voted' => $voted,
                'score' => $story->fresh()->score,
                'upvotes' => $story->fresh()->upvotes,
                'downvotes' => $story->fresh()->downvotes,
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/auth/login');
        }

        $shortId = $args['id'];
        $story = $this->storyService->getStoryByShortId($shortId);

        if (!$story) {
            return $this->render($response, 'stories/not-found', [
                'title' => 'Story Not Found | Lobsters'
            ]);
        }

        $user = \App\Models\User::find($_SESSION['user_id']);

        // Check if story can be edited by user (includes time limits and moderation checks)
        if (!$story->isEditableByUser($user)) {
            $message = 'You can only edit your own stories within 6 hours of posting, unless they have been moderated.';
            if ($story->user_id !== $user->id) {
                $message = 'You can only edit your own stories.';
            } elseif ($story->is_moderated) {
                $message = 'This story has been moderated and cannot be edited.';
            }
            
            return $this->render($response, 'errors/403', [
                'title' => 'Access Denied | Assurify',
                'message' => $message
            ]);
        }

        $storyData = $this->storyService->formatStoriesForView(collect([$story]))[0];

        return $this->render($response, 'stories/edit', [
            'title' => 'Edit Story | Lobsters',
            'story' => $storyData,
            'error' => $_SESSION['story_error'] ?? null,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/auth/login');
        }

        $shortId = $args['id'];
        $story = $this->storyService->getStoryByShortId($shortId);

        if (!$story) {
            return $this->render($response, 'stories/not-found', [
                'title' => 'Story Not Found | Lobsters'
            ]);
        }

        $user = \App\Models\User::find($_SESSION['user_id']);

        // Check if story can be edited by user
        if (!$story->isEditableByUser($user)) {
            $_SESSION['story_error'] = 'You can only edit your own stories within 6 hours of posting.';
            return $this->redirect($response, "/s/{$shortId}");
        }

        try {
            $data = $request->getParsedBody();
            
            // Validate input
            if (empty($data['title'])) {
                $_SESSION['story_error'] = 'Title is required';
                return $this->redirect($response, "/s/{$shortId}/edit");
            }

            if (strlen($data['title']) > 150) {
                $_SESSION['story_error'] = 'Title must be 150 characters or less';
                return $this->redirect($response, "/s/{$shortId}/edit");
            }

            // Update story fields
            $story->title = trim($data['title']);
            $story->description = trim($data['description'] ?? '');
            $story->user_is_author = isset($data['user_is_author']) ? true : false;
            
            // Process markdown for description
            if ($story->description) {
                $story->markeddown_description = \Michelf\Markdown::defaultTransform($story->description);
            } else {
                $story->markeddown_description = null;
            }
            
            // Note: slug is generated dynamically in views, not stored in database
            $story->updated_at = date('Y-m-d H:i:s');
            
            $story->save();

            // Handle tags update
            if (isset($data['tags'])) {
                $this->storyService->updateStoryTags($story, $data['tags']);
            }

            $_SESSION['story_success'] = 'Story updated successfully!';
            $slug = $this->storyService->generateSlug($story->title);
            return $this->redirect($response, "/s/{$shortId}/{$slug}");

        } catch (\Exception $e) {
            $_SESSION['story_error'] = $e->getMessage();
            return $this->redirect($response, "/s/{$shortId}/edit");
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Not authenticated'], 401);
        }

        $storyId = $args['id'] ?? null;
        if (!$storyId) {
            return $this->json($response, ['error' => 'Story ID required'], 400);
        }

        try {
            // Find story by short_id or regular id
            $story = \App\Models\Story::where('short_id', $storyId)
                        ->orWhere('id', $storyId)
                        ->first();

            if (!$story) {
                return $this->json($response, ['error' => 'Story not found'], 404);
            }

            // Check if user owns this story or is admin
            $user = \App\Models\User::find($_SESSION['user_id']);
            $canDelete = $story->user_id === $_SESSION['user_id'] || 
                        ($user && ($user->is_admin ?? false));

            if (!$canDelete) {
                return $this->json($response, ['error' => 'You can only delete your own stories'], 403);
            }

            // Soft delete the story
            $story->is_deleted = true;
            $story->deleted_at = date('Y-m-d H:i:s');
            $story->save();

            return $this->json($response, [
                'success' => true,
                'message' => 'Story deleted successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to delete story: ' . $e->getMessage()
            ], 500);
        }
    }
}
