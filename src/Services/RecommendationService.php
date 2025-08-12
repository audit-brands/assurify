<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\Comment;
use App\Models\User;

/**
 * Intelligent Content Recommendation Service
 * 
 * Provides personalized story recommendations using multiple algorithms:
 * - Collaborative filtering (user-based and item-based)
 * - Content-based filtering (tag similarity, topic matching)
 * - Hybrid approach combining multiple strategies
 * - Machine learning-enhanced scoring
 */
class RecommendationService
{
    private CacheService $cache;
    private array $config;
    
    // Recommendation algorithms
    private const ALGO_COLLABORATIVE = 'collaborative';
    private const ALGO_CONTENT_BASED = 'content_based';
    private const ALGO_POPULARITY = 'popularity';
    private const ALGO_TRENDING = 'trending';
    private const ALGO_HYBRID = 'hybrid';
    
    public function __construct(CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
        $this->config = $this->loadConfig();
    }
    
    /**
     * Get personalized recommendations for a user
     */
    public function getPersonalizedRecommendations(int $userId, int $limit = 20, array $options = []): array
    {
        $cacheKey = "user_recommendations_{$userId}_{$limit}_" . md5(serialize($options));
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $user = $this->getUserProfile($userId);
        if (!$user) {
            return $this->getGeneralRecommendations($limit, $options);
        }
        
        // Get recommendations from multiple algorithms
        $recommendations = [];
        
        if ($this->config['algorithms']['collaborative']['enabled']) {
            $collaborative = $this->getCollaborativeRecommendations($userId, $limit * 2);
            $recommendations['collaborative'] = $this->scoreRecommendations($collaborative, $user, 'collaborative');
        }
        
        if ($this->config['algorithms']['content_based']['enabled']) {
            $contentBased = $this->getContentBasedRecommendations($userId, $limit * 2);
            $recommendations['content_based'] = $this->scoreRecommendations($contentBased, $user, 'content_based');
        }
        
        if ($this->config['algorithms']['trending']['enabled']) {
            $trending = $this->getTrendingRecommendations($limit);
            $recommendations['trending'] = $this->scoreRecommendations($trending, $user, 'trending');
        }
        
        // Combine and rank recommendations using hybrid approach
        $finalRecommendations = $this->hybridRanking($recommendations, $user, $limit);
        
        // Add diversity and freshness
        $finalRecommendations = $this->addDiversity($finalRecommendations, $userId);
        $finalRecommendations = $this->addFreshness($finalRecommendations);
        
        // Cache results
        $this->cache->set($cacheKey, $finalRecommendations, $this->config['cache_ttl']);
        
        return $finalRecommendations;
    }
    
    /**
     * Get collaborative filtering recommendations
     */
    public function getCollaborativeRecommendations(int $userId, int $limit): array
    {
        // Find similar users based on voting patterns and interests
        $similarUsers = $this->findSimilarUsers($userId);
        
        if (empty($similarUsers)) {
            return [];
        }
        
        $recommendations = [];
        
        // Get stories liked by similar users that target user hasn't seen
        foreach ($similarUsers as $similarUser) {
            $userStories = $this->getUserLikedStories($similarUser['user_id']);
            $unseenStories = $this->filterUnseenStories($userStories, $userId);
            
            foreach ($unseenStories as $story) {
                $storyId = $story['id'];
                
                if (!isset($recommendations[$storyId])) {
                    $recommendations[$storyId] = [
                        'story' => $story,
                        'score' => 0,
                        'similarity_score' => 0,
                        'reasons' => []
                    ];
                }
                
                $weight = $similarUser['similarity'] * $this->config['weights']['collaborative'];
                $recommendations[$storyId]['score'] += $weight;
                $recommendations[$storyId]['similarity_score'] += $similarUser['similarity'];
                $recommendations[$storyId]['reasons'][] = "Users similar to you liked this";
            }
        }
        
        // Sort by score and limit results
        uasort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($recommendations, 0, $limit, true);
    }
    
    /**
     * Get content-based filtering recommendations
     */
    public function getContentBasedRecommendations(int $userId, int $limit): array
    {
        $userProfile = $this->getUserProfile($userId);
        if (!$userProfile) {
            return [];
        }
        
        $preferences = $userProfile['preferences'] ?? [];
        $recommendations = [];
        
        // Get stories based on user's tag preferences
        if (!empty($preferences['favorite_tags'])) {
            $tagBasedStories = $this->getStoriesByTags($preferences['favorite_tags'], $userId);
            foreach ($tagBasedStories as $story) {
                $recommendations[$story['id']] = [
                    'story' => $story,
                    'score' => $this->calculateTagSimilarity($story['tags'], $preferences['favorite_tags']),
                    'reasons' => ["Based on your interest in " . implode(', ', array_intersect($story['tags'], $preferences['favorite_tags']))]
                ];
            }
        }
        
        // Get stories from frequently interacted domains
        if (!empty($preferences['favorite_domains'])) {
            $domainBasedStories = $this->getStoriesByDomains($preferences['favorite_domains'], $userId);
            foreach ($domainBasedStories as $story) {
                $storyId = $story['id'];
                $domainScore = $this->calculateDomainScore($story['domain'], $preferences['favorite_domains']);
                
                if (isset($recommendations[$storyId])) {
                    $recommendations[$storyId]['score'] += $domainScore * $this->config['weights']['content_domain'];
                } else {
                    $recommendations[$storyId] = [
                        'story' => $story,
                        'score' => $domainScore * $this->config['weights']['content_domain'],
                        'reasons' => ["From {$story['domain']} which you often read"]
                    ];
                }
            }
        }
        
        // Get stories similar to recently liked content
        $recentLikedStories = $this->getUserRecentLikes($userId, 10);
        foreach ($recentLikedStories as $likedStory) {
            $similarStories = $this->findSimilarStories($likedStory['id'], $userId);
            foreach ($similarStories as $story) {
                $storyId = $story['id'];
                $similarity = $story['similarity_score'];
                
                if (isset($recommendations[$storyId])) {
                    $recommendations[$storyId]['score'] += $similarity * $this->config['weights']['content_similarity'];
                } else {
                    $recommendations[$storyId] = [
                        'story' => $story,
                        'score' => $similarity * $this->config['weights']['content_similarity'],
                        'reasons' => ["Similar to '{$likedStory['title']}' which you liked"]
                    ];
                }
            }
        }
        
        // Sort by score and limit results
        uasort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($recommendations, 0, $limit, true);
    }
    
    /**
     * Get trending recommendations based on recent activity
     */
    private function getTrendingRecommendations(int $limit): array
    {
        $cacheKey = "trending_recommendations_{$limit}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // Calculate trending score based on multiple factors
        $timeWindow = $this->config['trending']['time_window_hours'];
        $stories = $this->getRecentStories($timeWindow);
        
        $recommendations = [];
        
        foreach ($stories as $story) {
            $trendingScore = $this->calculateTrendingScore($story);
            
            if ($trendingScore > $this->config['trending']['min_score']) {
                $recommendations[$story['id']] = [
                    'story' => $story,
                    'score' => $trendingScore,
                    'reasons' => $this->getTrendingReasons($story)
                ];
            }
        }
        
        // Sort by trending score
        uasort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        $result = array_slice($recommendations, 0, $limit, true);
        
        $this->cache->set($cacheKey, $result, $this->config['trending']['cache_ttl']);
        
        return $result;
    }
    
    /**
     * Combine recommendations using hybrid ranking
     */
    public function hybridRanking(array $algorithmResults, array $user, int $limit): array
    {
        $combined = [];
        $weights = $this->config['hybrid_weights'];
        
        // Normalize scores for each algorithm
        foreach ($algorithmResults as $algorithm => $recommendations) {
            if (empty($recommendations)) continue;
            
            $maxScore = max(array_column($recommendations, 'score'));
            if ($maxScore == 0) continue;
            
            foreach ($recommendations as $storyId => $recommendation) {
                $normalizedScore = ($recommendation['score'] / $maxScore) * $weights[$algorithm];
                
                if (!isset($combined[$storyId])) {
                    $combined[$storyId] = [
                        'story' => $recommendation['story'],
                        'total_score' => 0,
                        'algorithm_scores' => [],
                        'reasons' => []
                    ];
                }
                
                $combined[$storyId]['total_score'] += $normalizedScore;
                $combined[$storyId]['algorithm_scores'][$algorithm] = $normalizedScore;
                $combined[$storyId]['reasons'] = array_merge(
                    $combined[$storyId]['reasons'], 
                    $recommendation['reasons'] ?? []
                );
            }
        }
        
        // Apply user-specific adjustments
        $combined = $this->applyUserAdjustments($combined, $user);
        
        // Sort by total score and limit
        uasort($combined, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        return array_slice($combined, 0, $limit, true);
    }
    
    /**
     * Add diversity to prevent echo chamber effect
     */
    private function addDiversity(array $recommendations, int $userId): array
    {
        if (empty($recommendations) || !$this->config['diversity']['enabled']) {
            return $recommendations;
        }
        
        $diversified = [];
        $usedTags = [];
        $usedDomains = [];
        $maxPerTag = $this->config['diversity']['max_per_tag'];
        $maxPerDomain = $this->config['diversity']['max_per_domain'];
        
        foreach ($recommendations as $storyId => $recommendation) {
            $story = $recommendation['story'];
            $tags = $story['tags'] ?? [];
            $domain = $story['domain'] ?? '';
            
            // Check tag diversity
            $tagCount = 0;
            foreach ($tags as $tag) {
                $tagCount += $usedTags[$tag] ?? 0;
            }
            
            $domainCount = $usedDomains[$domain] ?? 0;
            
            // Apply diversity penalty
            if ($tagCount >= $maxPerTag * count($tags) || $domainCount >= $maxPerDomain) {
                $recommendation['total_score'] *= $this->config['diversity']['penalty_factor'];
            }
            
            $diversified[$storyId] = $recommendation;
            
            // Update counters
            foreach ($tags as $tag) {
                $usedTags[$tag] = ($usedTags[$tag] ?? 0) + 1;
            }
            $usedDomains[$domain] = ($usedDomains[$domain] ?? 0) + 1;
        }
        
        // Re-sort after applying diversity
        uasort($diversified, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        return $diversified;
    }
    
    /**
     * Add freshness boost to recent content
     */
    private function addFreshness(array $recommendations): array
    {
        if (!$this->config['freshness']['enabled']) {
            return $recommendations;
        }
        
        $freshnessHours = $this->config['freshness']['boost_hours'];
        $boostFactor = $this->config['freshness']['boost_factor'];
        $now = time();
        
        foreach ($recommendations as $storyId => $recommendation) {
            $story = $recommendation['story'];
            $createdAt = strtotime($story['created_at']);
            $ageHours = ($now - $createdAt) / 3600;
            
            if ($ageHours <= $freshnessHours) {
                $freshnessBoost = (1 - ($ageHours / $freshnessHours)) * $boostFactor;
                $recommendation['total_score'] *= (1 + $freshnessBoost);
                $recommendation['reasons'][] = "Recently posted";
                $recommendations[$storyId] = $recommendation;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get general recommendations for new or anonymous users
     */
    public function getGeneralRecommendations(int $limit = 20, array $options = []): array
    {
        $cacheKey = "general_recommendations_{$limit}_" . md5(serialize($options));
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // Mix of popular and trending content
        $popular = $this->getPopularStories(intval($limit / 2));
        $trending = $this->getTrendingRecommendations(intval($limit / 2));
        
        $recommendations = [];
        
        // Add popular stories
        foreach ($popular as $story) {
            $recommendations[$story['id']] = [
                'story' => $story,
                'score' => $story['score'],
                'reasons' => ['Popular in the community']
            ];
        }
        
        // Add trending stories
        foreach ($trending as $storyId => $recommendation) {
            if (!isset($recommendations[$storyId])) {
                $recommendations[$storyId] = $recommendation;
            }
        }
        
        // Sort and limit
        uasort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        $result = array_slice($recommendations, 0, $limit, true);
        
        $this->cache->set($cacheKey, $result, $this->config['cache_ttl']);
        
        return $result;
    }
    
    /**
     * Record user interaction for learning
     */
    public function recordInteraction(int $userId, int $storyId, string $action, array $metadata = []): bool
    {
        try {
            // Store interaction for learning
            $interaction = [
                'user_id' => $userId,
                'story_id' => $storyId,
                'action' => $action, // view, like, comment, share, click
                'metadata' => json_encode($metadata),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // In a real implementation, this would be stored in a database
            $this->storeInteraction($interaction);
            
            // Invalidate relevant caches
            $this->invalidateUserCaches($userId);
            
            return true;
        } catch (\Exception $e) {
            error_log("Failed to record interaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recommendation explanations
     */
    public function explainRecommendation(int $userId, int $storyId): array
    {
        $recommendations = $this->getPersonalizedRecommendations($userId, 100);
        
        if (!isset($recommendations[$storyId])) {
            return ['explanation' => 'This story was not recommended to you.'];
        }
        
        $recommendation = $recommendations[$storyId];
        
        return [
            'total_score' => $recommendation['total_score'],
            'algorithm_scores' => $recommendation['algorithm_scores'] ?? [],
            'reasons' => array_unique($recommendation['reasons'] ?? []),
            'factors' => $this->getRecommendationFactors($userId, $storyId)
        ];
    }
    
    // Helper methods
    
    private function findSimilarUsers(int $userId, int $limit = 50): array
    {
        // Implement user similarity calculation based on:
        // - Voting patterns
        // - Commented stories
        // - Tag preferences
        // - Time-based activity patterns
        
        // Mock implementation
        return [];
    }
    
    private function getUserProfile(int $userId): ?array
    {
        $cacheKey = "user_profile_{$userId}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // Calculate user profile from interactions
        $profile = [
            'user_id' => $userId,
            'preferences' => [
                'favorite_tags' => $this->getUserFavoriteTags($userId),
                'favorite_domains' => $this->getUserFavoriteDomains($userId),
                'reading_time' => $this->getUserReadingTime($userId),
                'activity_pattern' => $this->getUserActivityPattern($userId)
            ],
            'computed_at' => time()
        ];
        
        $this->cache->set($cacheKey, $profile, $this->config['user_profile_ttl']);
        
        return $profile;
    }
    
    private function calculateTrendingScore(array $story): float
    {
        $now = time();
        $createdAt = strtotime($story['created_at']);
        $ageHours = ($now - $createdAt) / 3600;
        
        if ($ageHours > $this->config['trending']['max_age_hours']) {
            return 0;
        }
        
        // Calculate trending score using gravity formula
        $score = $story['score'] ?? 0;
        $comments = $story['comment_count'] ?? 0;
        
        $points = ($score + $comments) * $this->config['trending']['engagement_weight'];
        $gravity = $this->config['trending']['gravity'];
        
        return $points / pow(($ageHours + 2), $gravity);
    }
    
    private function getTrendingReasons(array $story): array
    {
        $reasons = [];
        
        if ($story['score'] > 10) {
            $reasons[] = "High score ({$story['score']} points)";
        }
        
        if ($story['comment_count'] > 5) {
            $reasons[] = "Active discussion ({$story['comment_count']} comments)";
        }
        
        $ageHours = (time() - strtotime($story['created_at'])) / 3600;
        if ($ageHours < 6) {
            $reasons[] = "Posted recently";
        }
        
        return $reasons;
    }
    
    private function loadConfig(): array
    {
        return [
            'algorithms' => [
                'collaborative' => ['enabled' => true],
                'content_based' => ['enabled' => true],
                'trending' => ['enabled' => true],
                'popularity' => ['enabled' => true]
            ],
            'weights' => [
                'collaborative' => 1.0,
                'content_domain' => 0.8,
                'content_similarity' => 0.9
            ],
            'hybrid_weights' => [
                'collaborative' => 0.4,
                'content_based' => 0.4,
                'trending' => 0.2
            ],
            'diversity' => [
                'enabled' => true,
                'max_per_tag' => 3,
                'max_per_domain' => 2,
                'penalty_factor' => 0.7
            ],
            'freshness' => [
                'enabled' => true,
                'boost_hours' => 24,
                'boost_factor' => 0.3
            ],
            'trending' => [
                'time_window_hours' => 48,
                'min_score' => 0.1,
                'max_age_hours' => 168, // 1 week
                'engagement_weight' => 1.5,
                'gravity' => 1.8,
                'cache_ttl' => 1800 // 30 minutes
            ],
            'cache_ttl' => 3600,
            'user_profile_ttl' => 7200
        ];
    }
    
    // Mock helper methods (would be implemented with real data access)
    
    private function getUserLikedStories(int $userId): array { return []; }
    private function filterUnseenStories(array $stories, int $userId): array { return $stories; }
    private function getStoriesByTags(array $tags, int $userId): array { return []; }
    private function getStoriesByDomains(array $domains, int $userId): array { return []; }
    private function getUserRecentLikes(int $userId, int $limit): array { return []; }
    private function findSimilarStories(int $storyId, int $userId): array { return []; }
    private function getRecentStories(int $hours): array { return []; }
    private function getPopularStories(int $limit): array { 
        // Mock implementation returning sample data
        $stories = [];
        for ($i = 1; $i <= $limit; $i++) {
            $stories[] = [
                'id' => $i,
                'title' => "Popular Story {$i}",
                'score' => 0.8 - ($i * 0.05),
                'tags' => ['popular', 'general']
            ];
        }
        return $stories;
    }
    private function calculateTagSimilarity(array $storyTags, array $userTags): float { return 0.5; }
    private function calculateDomainScore(string $domain, array $userDomains): float { return 0.5; }
    private function applyUserAdjustments(array $recommendations, array $user): array { return $recommendations; }
    private function getUserFavoriteTags(int $userId): array { return []; }
    private function getUserFavoriteDomains(int $userId): array { return []; }
    private function getUserReadingTime(int $userId): array { return []; }
    private function getUserActivityPattern(int $userId): array { return []; }
    private function storeInteraction(array $interaction): void {}
    private function invalidateUserCaches(int $userId): void {}
    private function getRecommendationFactors(int $userId, int $storyId): array { return []; }
    private function scoreRecommendations(array $recommendations, array $user, string $algorithm): array { return $recommendations; }
}