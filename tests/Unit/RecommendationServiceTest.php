<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\RecommendationService;
use App\Services\CacheService;

class RecommendationServiceTest extends TestCase
{
    private RecommendationService $recommendationService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->recommendationService = new RecommendationService($this->cacheService);
    }
    
    public function testGetPersonalizedRecommendationsForNewUser(): void
    {
        $userId = 999; // Non-existent user
        $recommendations = $this->recommendationService->getPersonalizedRecommendations($userId, 10);
        
        $this->assertIsArray($recommendations);
        $this->assertLessThanOrEqual(10, count($recommendations));
    }
    
    public function testGetGeneralRecommendations(): void
    {
        $recommendations = $this->recommendationService->getGeneralRecommendations(5);
        
        $this->assertIsArray($recommendations);
        $this->assertLessThanOrEqual(5, count($recommendations));
    }
    
    public function testRecordInteraction(): void
    {
        $userId = 1;
        $storyId = 123;
        $action = 'like';
        $metadata = ['source' => 'recommendation'];
        
        $result = $this->recommendationService->recordInteraction($userId, $storyId, $action, $metadata);
        
        $this->assertTrue($result);
    }
    
    public function testGetPersonalizedRecommendationsWithOptions(): void
    {
        $userId = 1;
        $options = [
            'algorithm' => 'collaborative',
            'include_reasons' => true,
            'exclude_seen' => true,
            'categories' => ['tech', 'science']
        ];
        
        $recommendations = $this->recommendationService->getPersonalizedRecommendations($userId, 10, $options);
        
        $this->assertIsArray($recommendations);
        $this->assertLessThanOrEqual(10, count($recommendations));
    }
    
    public function testCollaborativeFiltering(): void
    {
        $userId = 1;
        $recommendations = $this->recommendationService->getCollaborativeRecommendations($userId, 5);
        
        $this->assertIsArray($recommendations);
        $this->assertLessThanOrEqual(5, count($recommendations));
    }
    
    public function testContentBasedFiltering(): void
    {
        $userId = 1;
        $recommendations = $this->recommendationService->getContentBasedRecommendations($userId, 5);
        
        $this->assertIsArray($recommendations);
        $this->assertLessThanOrEqual(5, count($recommendations));
    }
    
    public function testHybridRanking(): void
    {
        $recommendations = [
            'collaborative' => [['id' => 1, 'score' => 0.8], ['id' => 2, 'score' => 0.7]],
            'content_based' => [['id' => 3, 'score' => 0.9], ['id' => 1, 'score' => 0.6]]
        ];
        $userProfile = ['preferences' => ['diversity' => 0.5]];
        
        $result = $this->recommendationService->hybridRanking($recommendations, $userProfile, 10);
        
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(10, count($result));
    }
    
    public function testRecordInvalidInteraction(): void
    {
        $result = $this->recommendationService->recordInteraction(0, 0, '', []);
        
        // Should handle invalid data gracefully
        $this->assertIsBool($result);
    }
    
    public function testExplainRecommendation(): void
    {
        $userId = 1;
        $storyId = 123;
        
        $explanation = $this->recommendationService->explainRecommendation($userId, $storyId);
        
        $this->assertIsArray($explanation);
        $this->assertArrayHasKey('explanation', $explanation);
    }
    
    public function testRecommendationLimits(): void
    {
        // Test with various limits
        $limits = [1, 5, 10, 20, 50, 100];
        
        foreach ($limits as $limit) {
            $recommendations = $this->recommendationService->getGeneralRecommendations($limit);
            $this->assertLessThanOrEqual($limit, count($recommendations));
        }
    }
    
    public function testRecommendationOptions(): void
    {
        $options = [
            'algorithm' => 'collaborative',
            'include_reasons' => true,
            'exclude_seen' => true
        ];
        
        $recommendations = $this->recommendationService->getGeneralRecommendations(10, $options);
        
        $this->assertIsArray($recommendations);
    }
    
    public function testEmptyRecommendations(): void
    {
        $recommendations = $this->recommendationService->getGeneralRecommendations(0);
        
        $this->assertIsArray($recommendations);
        $this->assertEmpty($recommendations);
    }
}