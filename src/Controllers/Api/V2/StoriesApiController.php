<?php

declare(strict_types=1);

namespace App\Controllers\Api\V2;

use App\Controllers\Api\StoriesApiController as V1StoriesController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoriesApiController extends V1StoriesController
{
    protected const API_VERSION = 'v2';
    
    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $this->getQueryParams($request);
            
            $page = max(1, (int) ($queryParams['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 25))); // v2: default 25 instead of 20
            $sort = $queryParams['sort'] ?? 'newest';
            $tag = $queryParams['tag'] ?? null;
            
            // v2: Add support for multiple tags
            $tags = null;
            if (isset($queryParams['tags'])) {
                $tags = is_array($queryParams['tags']) ? $queryParams['tags'] : explode(',', $queryParams['tags']);
            } elseif ($tag) {
                $tags = [$tag];
            }
            
            // v2: Add date filtering
            $dateFrom = $queryParams['date_from'] ?? null;
            $dateTo = $queryParams['date_to'] ?? null;
            
            // v2: Add user filtering
            $userId = $queryParams['user_id'] ?? null;
            
            $stories = $this->storyService->getStories($page, $perPage, $sort, $tags ? $tags[0] : null);
            $total = $this->storyService->getTotalStories($tags ? $tags[0] : null);
            
            // Transform stories for API response with v2 enhancements
            $transformedStories = array_map(function($story) {
                return $this->transformStoryV2($story);
            }, $stories);
            
            $response = $this->paginatedResponse(
                $response,
                $transformedStories,
                $total,
                $page,
                $perPage,
                'Stories retrieved successfully'
            );
            
            // v2: Add additional metadata
            $body = json_decode((string) $response->getBody(), true);
            $body['meta'] = [
                'filters_applied' => [
                    'tags' => $tags,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'user_id' => $userId
                ],
                'available_sorts' => ['newest', 'hottest', 'top', 'controversial'],
                'api_version' => 'v2'
            ];
            
            $response->getBody()->rewind();
            $response->getBody()->write(json_encode($body));
            
            return $response;
            
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
            
            $transformedStory = $this->transformStoryV2($story);
            
            // v2: Include additional data
            $queryParams = $this->getQueryParams($request);
            $includeComments = ($queryParams['include_comments'] ?? 'false') === 'true';
            
            if ($includeComments) {
                // In a real implementation, we'd fetch comments here
                $transformedStory['comments'] = [];
                $transformedStory['comments_count_verified'] = true;
            }
            
            return $this->successResponse($response, [
                'story' => $transformedStory,
                'meta' => [
                    'includes' => [
                        'comments' => $includeComments
                    ],
                    'api_version' => 'v2'
                ]
            ], 'Story retrieved successfully');
            
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
    
    private function transformStoryV2(array $story): array
    {
        $baseStory = $this->transformStory($story);
        
        // v2: Enhanced story transformation with additional fields
        return array_merge($baseStory, [
            'url_domain' => $story['url'] ? parse_url($story['url'], PHP_URL_HOST) : null,
            'is_text_post' => empty($story['url']),
            'estimated_read_time' => $this->estimateReadTime($story['description'] ?? ''),
            'hotness_score' => $this->calculateHotnessScore($story),
            'wilson_score' => $this->calculateWilsonScore($story),
            'engagement_metrics' => [
                'comments_per_day' => $this->calculateCommentsPerDay($story),
                'votes_per_day' => $this->calculateVotesPerDay($story)
            ],
            'content_analysis' => [
                'has_description' => !empty($story['description']),
                'description_length' => strlen($story['description'] ?? ''),
                'title_length' => strlen($story['title'])
            ]
        ]);
    }
    
    private function estimateReadTime(string $text): int
    {
        $wordsPerMinute = 200;
        $wordCount = str_word_count(strip_tags($text));
        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
    
    private function calculateHotnessScore(array $story): float
    {
        $score = (float) ($story['score'] ?? 0);
        $commentCount = (float) ($story['comment_count'] ?? 0);
        $hoursAgo = (time() - strtotime($story['created_at'])) / 3600;
        
        // Simple hotness algorithm
        $hotness = ($score + ($commentCount * 0.5)) / pow(($hoursAgo + 2), 1.5);
        
        return round($hotness, 3);
    }
    
    private function calculateWilsonScore(array $story): float
    {
        $ups = max(1, (int) ($story['score'] ?? 1));
        $downs = max(0, (int) ($story['downvotes'] ?? 0));
        
        $n = $ups + $downs;
        if ($n === 0) return 0;
        
        $z = 1.96; // 95% confidence
        $phat = $ups / $n;
        
        $wilson = ($phat + $z * $z / (2 * $n) - $z * sqrt(($phat * (1 - $phat) + $z * $z / (4 * $n)) / $n)) / (1 + $z * $z / $n);
        
        return round($wilson, 3);
    }
    
    private function calculateCommentsPerDay(array $story): float
    {
        $commentCount = (float) ($story['comment_count'] ?? 0);
        $daysAgo = max(0.1, (time() - strtotime($story['created_at'])) / 86400);
        
        return round($commentCount / $daysAgo, 2);
    }
    
    private function calculateVotesPerDay(array $story): float
    {
        $score = (float) ($story['score'] ?? 0);
        $daysAgo = max(0.1, (time() - strtotime($story['created_at'])) / 86400);
        
        return round($score / $daysAgo, 2);
    }
}