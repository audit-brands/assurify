<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Services\RateLimitingService;
use App\Services\CacheService;
use App\Services\PerformanceMonitorService;

class RateLimitingServiceTest extends TestCase
{
    private RateLimitingService $rateLimitService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->rateLimitService = new RateLimitingService($this->cacheService);
    }
    
    public function testIsAllowedReturnsTrueForNewRequest(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        $this->cacheService->method('set')->willReturn(true);
        
        $result = $this->rateLimitService->isAllowed('test_user', 'api');
        
        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('remaining', $result);
        $this->assertArrayHasKey('reset_times', $result);
        $this->assertNull($result['retry_after']);
    }
    
    public function testGetAdaptiveLimitReturnsAdjustedLimits(): void
    {
        $limit = $this->rateLimitService->getAdaptiveLimit('test_user', 'user');
        
        $this->assertArrayHasKey('requests', $limit);
        $this->assertArrayHasKey('window', $limit);
        $this->assertArrayHasKey('burst', $limit);
        $this->assertArrayHasKey('factors', $limit);
        $this->assertIsInt($limit['requests']);
        $this->assertGreaterThan(0, $limit['requests']);
    }
    
    public function testDetectAnomaliesReturnsAnalysis(): void
    {
        $result = $this->rateLimitService->detectAnomalies('test_user', ['ip' => '127.0.0.1']);
        
        $this->assertArrayHasKey('anomalies_detected', $result);
        $this->assertArrayHasKey('anomaly_count', $result);
        $this->assertArrayHasKey('anomalies', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('recommended_action', $result);
        $this->assertIsBool($result['anomalies_detected']);
        $this->assertIsInt($result['anomaly_count']);
        $this->assertIsArray($result['anomalies']);
        $this->assertIsFloat($result['risk_score']);
    }
    
    public function testCheckTokenBucketAllowsRequestWithTokens(): void
    {
        $limitConfig = [
            'capacity' => 10,
            'refill_rate' => 1.0,
            'window' => 3600
        ];
        
        $this->cacheService->method('get')->willReturn(null);
        $this->cacheService->method('set')->willReturn(true);
        
        $result = $this->rateLimitService->checkTokenBucket('test_bucket', $limitConfig);
        
        $this->assertTrue($result['allowed']);
        $this->assertGreaterThanOrEqual(0, $result['remaining']);
        $this->assertArrayHasKey('capacity', $result);
        $this->assertArrayHasKey('reset_time', $result);
    }
    
    public function testCheckSlidingWindowAllowsRequestWithinLimit(): void
    {
        $limitConfig = [
            'requests' => 100,
            'window' => 3600
        ];
        
        $this->cacheService->method('get')->willReturn([]);
        $this->cacheService->method('set')->willReturn(true);
        
        $result = $this->rateLimitService->checkSlidingWindow('test_window', $limitConfig);
        
        $this->assertTrue($result['allowed']);
        $this->assertGreaterThanOrEqual(0, $result['remaining']);
        $this->assertArrayHasKey('current_count', $result);
        $this->assertArrayHasKey('reset_time', $result);
    }
    
    public function testGetStatisticsReturnsMetrics(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        
        $stats = $this->rateLimitService->getStatistics('1h');
        
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('blocked_requests', $stats);
        $this->assertArrayHasKey('block_rate', $stats);
        $this->assertArrayHasKey('top_blocked_ips', $stats);
        $this->assertEquals('1h', $stats['period']);
        $this->assertIsFloat($stats['block_rate']);
    }
    
    public function testOptimizeConfigurationReturnsRecommendations(): void
    {
        $optimizations = $this->rateLimitService->optimizeConfiguration();
        
        $this->assertIsArray($optimizations);
        // Empty array is valid if no optimizations needed
    }
    
    public function testCoordinateDistributedLimitsHandlesNonDistributedMode(): void
    {
        $result = $this->rateLimitService->coordinateDistributedLimits('test_user', 'api', 1);
        
        $this->assertArrayHasKey('coordinated', $result);
        $this->assertFalse($result['coordinated']); // Should be false for non-distributed mode
        $this->assertTrue($result['local_only']);
    }
}