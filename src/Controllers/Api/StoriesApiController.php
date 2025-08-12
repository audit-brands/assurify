<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\StoryService;
use App\Services\JwtService;

class StoriesApiController extends BaseApiController
{
    private StoryService $storyService;
    private JwtService $jwtService;
    
    public function __construct(StoryService $storyService, JwtService $jwtService)
    {
        $this->storyService = $storyService;
        $this->jwtService = $jwtService;
    }
    
    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $this->getQueryParams($request);
            
            $page = max(1, (int) ($queryParams['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
            $sort = $queryParams['sort'] ?? 'newest';
            $tag = $queryParams['tag'] ?? null;
            
            $stories = $this->storyService->getStories($page, $perPage, $sort, $tag);
            $total = $this->storyService->getTotalStories($tag);
            
            // Transform stories for API response
            $transformedStories = array_map([$this, 'transformStory'], $stories);
            
            return $this->paginatedResponse(
                $response,
                $transformedStories,
                $total,
                $page,
                $perPage,
                'Stories retrieved successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to retrieve stories',
                500,
                [],
                'STORIES_ERROR'
            );
        }
    }
    
    public function show(Request $request, Response $response): Response
    {
        try {
            $id = (int) $this->getPathParam($request, 'id');
            
            if ($id <= 0) {
                return $this->errorResponse(
                    $response,
                    'Invalid story ID',
                    400,
                    [],
                    'INVALID_ID'
                );
            }
            
            $story = $this->storyService->getStoryById($id);
            
            if (!$story) {
                return $this->errorResponse(
                    $response,
                    'Story not found',
                    404,
                    [],
                    'STORY_NOT_FOUND'
                );
            }
            
            $transformedStory = $this->transformStory($story);
            
            return $this->successResponse($response, ['story' => $transformedStory], 'Story retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to retrieve story',
                500,
                [],
                'STORY_ERROR'
            );
        }
    }
    
    public function store(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $data = $this->getRequestData($request);
            
            // Validate required fields
            $missing = $this->validateRequiredFields($data, ['title']);
            if (!empty($missing)) {
                return $this->errorResponse(
                    $response,
                    'Missing required fields',
                    400,
                    $missing,
                    'VALIDATION_ERROR'
                );
            }
            
            // Check scope
            if (!$this->jwtService->hasScope($user, 'stories:write')) {
                return $this->errorResponse(
                    $response,
                    'Insufficient permissions to create stories',
                    403,
                    [],
                    'INSUFFICIENT_SCOPE'
                );
            }
            
            // Validate story data
            $errors = [];
            
            if (strlen($data['title']) < 3) {
                $errors[] = 'Title must be at least 3 characters long';
            }
            
            if (strlen($data['title']) > 200) {
                $errors[] = 'Title must be no more than 200 characters long';
            }
            
            if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid URL format';
            }
            
            if (!empty($errors)) {
                return $this->errorResponse(
                    $response,
                    'Validation failed',
                    400,
                    $errors,
                    'VALIDATION_ERROR'
                );
            }
            
            $storyData = [
                'title' => $data['title'],
                'url' => $data['url'] ?? null,
                'description' => $data['description'] ?? null,
                'tags' => $data['tags'] ?? [],
                'user_id' => $user['user_id']
            ];
            
            $storyId = $this->storyService->createStory($storyData);
            
            if (!$storyId) {
                return $this->errorResponse(
                    $response,
                    'Failed to create story',
                    500,
                    [],
                    'STORY_CREATION_ERROR'
                );
            }
            
            $story = $this->storyService->getStoryById($storyId);
            $transformedStory = $this->transformStory($story);
            
            return $this->successResponse($response, ['story' => $transformedStory], 'Story created successfully', 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to create story',
                500,
                [],
                'STORY_ERROR'
            );
        }
    }
    
    public function update(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $id = (int) $this->getPathParam($request, 'id');
            $data = $this->getRequestData($request);
            
            if ($id <= 0) {
                return $this->errorResponse(
                    $response,
                    'Invalid story ID',
                    400,
                    [],
                    'INVALID_ID'
                );
            }
            
            // Check scope
            if (!$this->jwtService->hasScope($user, 'stories:write')) {
                return $this->errorResponse(
                    $response,
                    'Insufficient permissions to edit stories',
                    403,
                    [],
                    'INSUFFICIENT_SCOPE'
                );
            }
            
            $story = $this->storyService->getStoryById($id);
            
            if (!$story) {
                return $this->errorResponse(
                    $response,
                    'Story not found',
                    404,
                    [],
                    'STORY_NOT_FOUND'
                );
            }
            
            // Check if user owns the story or is admin/moderator
            if ($story['user_id'] !== $user['user_id'] && !$user['is_admin'] && !$user['is_moderator']) {
                return $this->errorResponse(
                    $response,
                    'You can only edit your own stories',
                    403,
                    [],
                    'UNAUTHORIZED_EDIT'
                );
            }
            
            $updated = $this->storyService->updateStory($id, $data);
            
            if (!$updated) {
                return $this->errorResponse(
                    $response,
                    'Failed to update story',
                    500,
                    [],
                    'STORY_UPDATE_ERROR'
                );
            }
            
            $updatedStory = $this->storyService->getStoryById($id);
            $transformedStory = $this->transformStory($updatedStory);
            
            return $this->successResponse($response, ['story' => $transformedStory], 'Story updated successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to update story',
                500,
                [],
                'STORY_ERROR'
            );
        }
    }
    
    public function delete(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $id = (int) $this->getPathParam($request, 'id');
            
            if ($id <= 0) {
                return $this->errorResponse(
                    $response,
                    'Invalid story ID',
                    400,
                    [],
                    'INVALID_ID'
                );
            }
            
            // Check scope
            if (!$this->jwtService->hasScope($user, 'delete')) {
                return $this->errorResponse(
                    $response,
                    'Insufficient permissions to delete stories',
                    403,
                    [],
                    'INSUFFICIENT_SCOPE'
                );
            }
            
            $story = $this->storyService->getStoryById($id);
            
            if (!$story) {
                return $this->errorResponse(
                    $response,
                    'Story not found',
                    404,
                    [],
                    'STORY_NOT_FOUND'
                );
            }
            
            // Check if user owns the story or is admin/moderator
            if ($story['user_id'] !== $user['user_id'] && !$user['is_admin'] && !$user['is_moderator']) {
                return $this->errorResponse(
                    $response,
                    'You can only delete your own stories',
                    403,
                    [],
                    'UNAUTHORIZED_DELETE'
                );
            }
            
            $deleted = $this->storyService->deleteStory($id);
            
            if (!$deleted) {
                return $this->errorResponse(
                    $response,
                    'Failed to delete story',
                    500,
                    [],
                    'STORY_DELETE_ERROR'
                );
            }
            
            return $this->successResponse($response, [], 'Story deleted successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to delete story',
                500,
                [],
                'STORY_ERROR'
            );
        }
    }
    
    public function vote(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $id = (int) $this->getPathParam($request, 'id');
            $data = $this->getRequestData($request);
            
            if ($id <= 0) {
                return $this->errorResponse(
                    $response,
                    'Invalid story ID',
                    400,
                    [],
                    'INVALID_ID'
                );
            }
            
            // Check scope
            if (!$this->jwtService->hasScope($user, 'votes')) {
                return $this->errorResponse(
                    $response,
                    'Insufficient permissions to vote',
                    403,
                    [],
                    'INSUFFICIENT_SCOPE'
                );
            }
            
            $direction = $data['vote'] ?? 'up';
            if (!in_array($direction, ['up', 'down', 'remove'])) {
                return $this->errorResponse(
                    $response,
                    'Invalid vote direction. Must be "up", "down", or "remove"',
                    400,
                    [],
                    'INVALID_VOTE'
                );
            }
            
            $result = $this->storyService->voteOnStory($id, $user['user_id'], $direction);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $response,
                    $result['message'] ?? 'Failed to vote',
                    400,
                    [],
                    'VOTE_ERROR'
                );
            }
            
            return $this->successResponse($response, ['score' => $result['score']], 'Vote recorded successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to vote on story',
                500,
                [],
                'VOTE_ERROR'
            );
        }
    }
    
    private function transformStory(array $story): array
    {
        return [
            'id' => (int) $story['id'],
            'title' => $story['title'],
            'url' => $story['url'],
            'description' => $story['description'],
            'score' => (int) ($story['score'] ?? 0),
            'comment_count' => (int) ($story['comment_count'] ?? 0),
            'user' => [
                'id' => (int) $story['user_id'],
                'username' => $story['username'] ?? null
            ],
            'tags' => $story['tags'] ?? [],
            'created_at' => $story['created_at'],
            'updated_at' => $story['updated_at'] ?? null,
            'slug' => $story['slug'] ?? null
        ];
    }
}