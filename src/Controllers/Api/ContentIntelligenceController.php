<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\RecommendationService;
use App\Services\ContentCategorizationService;
use App\Services\DuplicateDetectionService;
use App\Services\SearchIndexService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Content Intelligence API Controller
 * 
 * Provides intelligent content analysis, recommendations, and insights:
 * - Personalized content recommendations
 * - Automatic content categorization and tagging
 * - Duplicate detection and similarity analysis
 * - Content quality assessment
 * - Engagement prediction and optimization
 */
class ContentIntelligenceController extends BaseApiController
{
    private RecommendationService $recommendationService;
    private ContentCategorizationService $categorizationService;
    private DuplicateDetectionService $duplicateService;
    private SearchIndexService $searchService;
    
    public function __construct(
        RecommendationService $recommendationService,
        ContentCategorizationService $categorizationService,
        DuplicateDetectionService $duplicateService,
        SearchIndexService $searchService
    ) {
        $this->recommendationService = $recommendationService;
        $this->categorizationService = $categorizationService;
        $this->duplicateService = $duplicateService;
        $this->searchService = $searchService;
    }
    
    /**
     * Get personalized content recommendations
     * GET /api/v1/intelligence/recommendations
     */
    public function getRecommendations(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromToken($request);
            $params = $this->getQueryParams($request);
            
            $limit = min((int)($params['limit'] ?? 20), 50);
            $options = [
                'algorithm' => $params['algorithm'] ?? 'hybrid',
                'include_reasons' => isset($params['include_reasons']),
                'exclude_seen' => isset($params['exclude_seen']),
                'categories' => $params['categories'] ?? null
            ];
            
            if ($user) {
                $recommendations = $this->recommendationService->getPersonalizedRecommendations(
                    $user['id'], 
                    $limit, 
                    $options
                );
            } else {
                $recommendations = $this->recommendationService->getGeneralRecommendations($limit, $options);
            }
            
            return $this->successResponse($response, [
                'recommendations' => $recommendations,
                'count' => count($recommendations),
                'personalized' => $user !== null,
                'algorithm' => $options['algorithm']
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to get recommendations: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Analyze content for categorization and quality
     * POST /api/v1/intelligence/analyze
     */
    public function analyzeContent(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $requiredFields = ['title'];
            $missing = $this->validateRequiredFields($data, $requiredFields);
            if (!empty($missing)) {
                return $this->errorResponse($response, 'Missing required fields: ' . implode(', ', $missing), 400);
            }
            
            $content = [
                'title' => $data['title'],
                'url' => $data['url'] ?? '',
                'description' => $data['description'] ?? '',
                'content' => $data['content'] ?? '',
                'tags' => $data['existing_tags'] ?? []
            ];
            
            // Analyze content
            $analysis = $this->categorizationService->analyzeContent($content);
            
            // Check for duplicates
            $duplicateCheck = $this->duplicateService->checkDuplicates($content, [
                'limit' => 10
            ]);
            
            return $this->successResponse($response, [
                'analysis' => $analysis,
                'duplicate_check' => $duplicateCheck,
                'recommendations' => $this->generateContentRecommendations($analysis, $duplicateCheck)
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Content analysis failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get tag suggestions for content
     * POST /api/v1/intelligence/suggest-tags
     */
    public function suggestTags(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $text = trim(($data['title'] ?? '') . ' ' . ($data['content'] ?? '') . ' ' . ($data['description'] ?? ''));
            $url = $data['url'] ?? '';
            $existingTags = $data['existing_tags'] ?? [];
            
            if (empty($text)) {
                return $this->errorResponse($response, 'Content text is required for tag suggestions', 400);
            }
            
            $suggestions = $this->categorizationService->suggestTags($text, $url, $existingTags);
            
            // Also get related tags from search index
            $relatedTags = $this->searchService->getRelatedTags($existingTags, 5);
            
            return $this->successResponse($response, [
                'suggested_tags' => $suggestions,
                'related_tags' => $relatedTags,
                'existing_tags' => $existingTags,
                'total_suggestions' => count($suggestions)
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Tag suggestion failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check for duplicate content
     * POST /api/v1/intelligence/check-duplicates
     */
    public function checkDuplicates(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $content = [
                'title' => $data['title'] ?? '',
                'url' => $data['url'] ?? '',
                'content' => $data['content'] ?? '',
                'description' => $data['description'] ?? ''
            ];
            
            $options = [
                'limit' => min((int)($data['limit'] ?? 20), 50),
                'threshold' => (float)($data['threshold'] ?? 0.8),
                'check_url_variants' => $data['check_url_variants'] ?? true,
                'check_content_similarity' => $data['check_content_similarity'] ?? true
            ];
            
            $duplicateCheck = $this->duplicateService->checkDuplicates($content, $options);
            
            return $this->successResponse($response, $duplicateCheck);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Duplicate check failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate similarity between two pieces of content
     * POST /api/v1/intelligence/similarity
     */
    public function calculateSimilarity(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $requiredFields = ['content1', 'content2'];
            $missing = $this->validateRequiredFields($data, $requiredFields);
            if (!empty($missing)) {
                return $this->errorResponse($response, 'Missing required fields: ' . implode(', ', $missing), 400);
            }
            
            $content1 = $data['content1'];
            $content2 = $data['content2'];
            
            $similarity = [
                'overall' => $this->duplicateService->calculateContentSimilarity($content1, $content2),
                'cosine' => $this->duplicateService->calculateCosineSimilarity($content1, $content2),
                'jaccard' => $this->duplicateService->calculateJaccardSimilarity($content1, $content2)
            ];
            
            // Calculate title similarity if provided
            if (!empty($data['title1']) && !empty($data['title2'])) {
                $similarity['title'] = $this->duplicateService->calculateTitleSimilarity(
                    $data['title1'], 
                    $data['title2']
                );
            }
            
            // Calculate URL similarity if provided
            if (!empty($data['url1']) && !empty($data['url2'])) {
                $similarity['url'] = $this->duplicateService->calculateUrlSimilarity(
                    $data['url1'], 
                    $data['url2']
                );
            }
            
            return $this->successResponse($response, [
                'similarity' => $similarity,
                'interpretation' => $this->interpretSimilarity($similarity['overall'])
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Similarity calculation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Record user interaction for learning
     * POST /api/v1/intelligence/interaction
     */
    public function recordInteraction(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromToken($request);
            if (!$user) {
                return $this->errorResponse($response, 'Authentication required', 401);
            }
            
            $data = $this->getRequestData($request);
            
            $requiredFields = ['story_id', 'action'];
            $missing = $this->validateRequiredFields($data, $requiredFields);
            if (!empty($missing)) {
                return $this->errorResponse($response, 'Missing required fields: ' . implode(', ', $missing), 400);
            }
            
            $validActions = ['view', 'like', 'dislike', 'comment', 'share', 'click', 'bookmark'];
            if (!in_array($data['action'], $validActions)) {
                return $this->errorResponse($response, 'Invalid action. Must be one of: ' . implode(', ', $validActions), 400);
            }
            
            $success = $this->recommendationService->recordInteraction(
                $user['id'],
                (int)$data['story_id'],
                $data['action'],
                $data['metadata'] ?? []
            );
            
            if ($success) {
                return $this->successResponse($response, [
                    'recorded' => true,
                    'message' => 'Interaction recorded successfully'
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to record interaction', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to record interaction: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get explanation for a recommendation
     * GET /api/v1/intelligence/recommendations/{storyId}/explain
     */
    public function explainRecommendation(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $this->getUserFromToken($request);
            if (!$user) {
                return $this->errorResponse($response, 'Authentication required', 401);
            }
            
            $storyId = (int)($args['storyId'] ?? 0);
            if ($storyId <= 0) {
                return $this->errorResponse($response, 'Valid story ID is required', 400);
            }
            
            $explanation = $this->recommendationService->explainRecommendation($user['id'], $storyId);
            
            return $this->successResponse($response, [
                'explanation' => $explanation,
                'story_id' => $storyId
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to explain recommendation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get content insights and analytics
     * GET /api/v1/intelligence/insights
     */
    public function getContentInsights(Request $request, Response $response): Response
    {
        try {
            $params = $this->getQueryParams($request);
            $timeframe = $params['timeframe'] ?? '7d';
            $category = $params['category'] ?? null;
            
            $insights = [
                'trending_topics' => $this->getTrendingTopics($timeframe, $category),
                'popular_tags' => $this->getPopularTags($timeframe, $category),
                'content_quality_stats' => $this->getQualityStats($timeframe, $category),
                'engagement_patterns' => $this->getEngagementPatterns($timeframe, $category),
                'duplicate_stats' => $this->getDuplicateStats($timeframe),
                'recommendation_performance' => $this->getRecommendationStats($timeframe)
            ];
            
            return $this->successResponse($response, [
                'insights' => $insights,
                'timeframe' => $timeframe,
                'category' => $category,
                'generated_at' => date('c')
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to get insights: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get personalized content feed
     * GET /api/v1/intelligence/feed
     */
    public function getPersonalizedFeed(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromToken($request);
            $params = $this->getQueryParams($request);
            
            $limit = min((int)($params['limit'] ?? 20), 100);
            $page = max((int)($params['page'] ?? 1), 1);
            $categories = $params['categories'] ? explode(',', $params['categories']) : null;
            
            $options = [
                'page' => $page,
                'categories' => $categories,
                'exclude_seen' => isset($params['exclude_seen']),
                'min_quality' => (float)($params['min_quality'] ?? 0.0),
                'diversity' => isset($params['diversity'])
            ];
            
            if ($user) {
                $feed = $this->recommendationService->getPersonalizedRecommendations(
                    $user['id'], 
                    $limit, 
                    $options
                );
                $feedType = 'personalized';
            } else {
                $feed = $this->recommendationService->getGeneralRecommendations($limit, $options);
                $feedType = 'general';
            }
            
            return $this->successResponse($response, [
                'feed' => $feed,
                'feed_type' => $feedType,
                'page' => $page,
                'limit' => $limit,
                'count' => count($feed),
                'personalized' => $user !== null
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to get personalized feed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update user preferences for recommendations
     * POST /api/v1/intelligence/preferences
     */
    public function updatePreferences(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromToken($request);
            if (!$user) {
                return $this->errorResponse($response, 'Authentication required', 401);
            }
            
            $data = $this->getRequestData($request);
            
            $preferences = [
                'favorite_categories' => $data['favorite_categories'] ?? [],
                'disliked_categories' => $data['disliked_categories'] ?? [],
                'favorite_tags' => $data['favorite_tags'] ?? [],
                'blocked_domains' => $data['blocked_domains'] ?? [],
                'content_types' => $data['content_types'] ?? [],
                'technical_level' => $data['technical_level'] ?? 'intermediate',
                'diversity_preference' => (float)($data['diversity_preference'] ?? 0.5),
                'freshness_preference' => (float)($data['freshness_preference'] ?? 0.3)
            ];
            
            // Store user preferences (in real implementation, this would update the database)
            $success = $this->updateUserPreferences($user['id'], $preferences);
            
            if ($success) {
                return $this->successResponse($response, [
                    'updated' => true,
                    'preferences' => $preferences,
                    'message' => 'Preferences updated successfully'
                ]);
            } else {
                return $this->errorResponse($response, 'Failed to update preferences', 500);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to update preferences: ' . $e->getMessage(), 500);
        }
    }
    
    // Helper methods
    
    private function generateContentRecommendations(array $analysis, array $duplicateCheck): array
    {
        $recommendations = [];
        
        // Quality recommendations
        $quality = $analysis['quality_score']['overall'] ?? 0;
        if ($quality < 0.5) {
            $recommendations[] = 'Consider improving content quality for better engagement';
        }
        
        // Tag recommendations
        if (count($analysis['suggested_tags'] ?? []) > 0) {
            $recommendations[] = 'Add suggested tags to improve discoverability';
        }
        
        // Duplicate recommendations
        if ($duplicateCheck['is_duplicate']) {
            $recommendations[] = 'Duplicate content detected - consider reviewing similar content';
        } elseif ($duplicateCheck['similarity_score'] > 0.7) {
            $recommendations[] = 'High similarity to existing content - consider adding unique value';
        }
        
        // Engagement recommendations
        $engagement = $analysis['engagement_prediction']['predicted_score'] ?? 0;
        if ($engagement < 0.5) {
            $recommendations[] = 'Consider improving title or content structure for better engagement';
        }
        
        return $recommendations;
    }
    
    private function interpretSimilarity(float $similarity): string
    {
        if ($similarity > 0.9) return 'Nearly identical';
        if ($similarity > 0.8) return 'Very similar';
        if ($similarity > 0.6) return 'Similar';
        if ($similarity > 0.4) return 'Somewhat similar';
        if ($similarity > 0.2) return 'Slightly similar';
        return 'Different';
    }
    
    // Mock methods (would be implemented with real data access)
    private function getTrendingTopics(string $timeframe, ?string $category): array { return []; }
    private function getPopularTags(string $timeframe, ?string $category): array { return []; }
    private function getQualityStats(string $timeframe, ?string $category): array { return []; }
    private function getEngagementPatterns(string $timeframe, ?string $category): array { return []; }
    private function getDuplicateStats(string $timeframe): array { return []; }
    private function getRecommendationStats(string $timeframe): array { return []; }
    private function updateUserPreferences(int $userId, array $preferences): bool { return true; }
}