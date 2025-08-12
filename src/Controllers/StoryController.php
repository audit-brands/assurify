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

        return $this->render($response, 'stories/show', [
            'title' => $story->title . ' | Lobsters',
            'story' => $storyData,
            'comments' => $commentTree,
            'total_comments' => count($comments)
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
            $suggestedTags = $this->tagService->getSuggestedTags('', '', $url);
        }

        return $this->render($response, 'stories/create', [
            'title' => 'Submit Story | Lobsters',
            'url' => $url,
            'suggested_tags' => $suggestedTags,
            'error' => $_SESSION['story_error'] ?? null,
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
            $user = \App\Models\User::find($_SESSION['user_id']);

            $storyData = [
                'title' => $data['title'] ?? '',
                'url' => $data['url'] ?? '',
                'description' => $data['description'] ?? '',
                'tags' => $data['tags'] ?? '',
                'user_is_author' => isset($data['user_is_author']),
            ];

            $story = $this->storyService->createStory($user, $storyData);

            return $this->redirect($response, "/s/{$story->short_id}/" . $this->storyService->generateSlug($story->title));
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

        // Only story author or moderators can edit
        if ($story->user_id !== $user->id && !$user->is_moderator && !$user->is_admin) {
            return $this->render($response, 'errors/403', [
                'title' => 'Access Denied | Lobsters'
            ]);
        }

        $storyData = $this->storyService->formatStoriesForView(collect([$story]))[0];

        return $this->render($response, 'stories/edit', [
            'title' => 'Edit Story | Lobsters',
            'story' => $storyData,
            'error' => $_SESSION['story_error'] ?? null,
        ]);
    }
}
