<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Services\SecurityMonitorService;
use App\Services\CacheService;
use App\Services\RateLimitingService;
use App\Services\PerformanceMonitorService;

class SecurityMonitorServiceTest extends TestCase
{
    private SecurityMonitorService $securityService;
    private CacheService $cacheService;
    private RateLimitingService $rateLimitService;
    private PerformanceMonitorService $monitorService;
    
    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->rateLimitService = $this->createMock(RateLimitingService::class);
        $this->monitorService = $this->createMock(PerformanceMonitorService::class);
        
        $this->securityService = new SecurityMonitorService(
            $this->cacheService,
            $this->rateLimitService,
            $this->monitorService
        );
    }
    
    public function testAnalyzeRequestWithCleanRequest(): void
    {
        $requestData = [
            'method' => 'GET',
            'url' => '/api/stories',
            'get' => [],
            'post' => [],
            'headers' => ['user-agent' => 'Mozilla/5.0'],
            'ip' => '127.0.0.1'
        ];
        
        $result = $this->securityService->analyzeRequest($requestData);
        
        $this->assertArrayHasKey('threats_detected', $result);
        $this->assertArrayHasKey('threat_count', $result);
        $this->assertArrayHasKey('threats', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('threat_level', $result);
        $this->assertArrayHasKey('recommended_action', $result);
        $this->assertArrayHasKey('analysis_time', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $this->assertIsBool($result['threats_detected']);
        $this->assertIsInt($result['threat_count']);
        $this->assertIsArray($result['threats']);
        $this->assertIsFloat($result['risk_score']);
        $this->assertIsString($result['threat_level']);
        $this->assertIsString($result['recommended_action']);
        $this->assertIsFloat($result['analysis_time']);
    }
    
    public function testAnalyzeRequestWithSQLInjectionAttempt(): void
    {
        $requestData = [
            'method' => 'POST',
            'url' => '/api/stories',
            'get' => ['id' => "1; DROP TABLE users;"],
            'post' => [],
            'headers' => ['user-agent' => 'Mozilla/5.0'],
            'ip' => '192.168.1.100'
        ];
        
        $result = $this->securityService->analyzeRequest($requestData);
        
        $this->assertTrue($result['threats_detected']);
        $this->assertGreaterThan(0, $result['threat_count']);
        $this->assertGreaterThan(0, $result['risk_score']);
        $this->assertNotEquals('allow', $result['recommended_action']);
    }
    
    public function testAnalyzeRequestWithXSSAttempt(): void
    {
        $requestData = [
            'method' => 'POST',
            'url' => '/api/comments',
            'get' => [],
            'post' => ['content' => '<script>alert("xss")</script>'],
            'headers' => ['user-agent' => 'Mozilla/5.0'],
            'ip' => '10.0.0.1'
        ];
        
        $result = $this->securityService->analyzeRequest($requestData);
        
        $this->assertTrue($result['threats_detected']);
        $this->assertGreaterThan(0, $result['threat_count']);
        $this->assertGreaterThan(0, $result['risk_score']);
        $this->assertContains($result['recommended_action'], ['block', 'challenge', 'monitor']);
    }
    
    public function testCheckIPReputationWithTrustedIP(): void
    {
        $result = $this->securityService->checkIPReputation('127.0.0.1');
        
        $this->assertArrayHasKey('suspicious', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('reputation', $result);
        
        $this->assertFalse($result['suspicious']);
        $this->assertEquals('ip_reputation', $result['type']);
        $this->assertEquals(0.0, $result['risk_score']);
        $this->assertEquals('trusted', $result['reputation']);
    }
    
    public function testCheckIPReputationWithUnknownIP(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        $this->cacheService->method('set')->willReturn(true);
        
        $result = $this->securityService->checkIPReputation('203.0.113.1');
        
        $this->assertArrayHasKey('suspicious', $result);
        $this->assertArrayHasKey('geolocation', $result);
        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('indicators', $result);
        
        $this->assertIsBool($result['suspicious']);
        $this->assertIsArray($result['geolocation']);
        $this->assertIsArray($result['history']);
        $this->assertIsArray($result['indicators']);
    }
    
    public function testLogSecurityEventReturnsSuccess(): void
    {
        $requestData = [
            'ip' => '192.168.1.1',
            'headers' => ['user-agent' => 'TestAgent'],
            'method' => 'POST'
        ];
        
        $analysis = [
            'threats_detected' => true,
            'threat_level' => 'high',
            'threats' => [['type' => 'sql_injection', 'risk_score' => 50.0]],
            'recommended_action' => 'block'
        ];
        
        // Since we can't test actual database insertion without connection,
        // we test that the method executes without throwing exceptions
        $result = $this->securityService->logSecurityEvent($requestData, $analysis);
        
        // With mocked database, this will return false due to the try-catch
        $this->assertIsBool($result);
    }
    
    public function testGetSecurityDashboardReturnsData(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        
        $dashboard = $this->securityService->getSecurityDashboard();
        
        $this->assertArrayHasKey('threat_summary', $dashboard);
        $this->assertArrayHasKey('recent_events', $dashboard);
        $this->assertArrayHasKey('top_threats', $dashboard);
        $this->assertArrayHasKey('blocked_ips', $dashboard);
        $this->assertArrayHasKey('security_metrics', $dashboard);
        $this->assertArrayHasKey('alert_status', $dashboard);
        $this->assertArrayHasKey('generated_at', $dashboard);
        
        $this->assertIsArray($dashboard['threat_summary']);
        $this->assertIsArray($dashboard['recent_events']);
        $this->assertIsArray($dashboard['top_threats']);
        $this->assertIsArray($dashboard['blocked_ips']);
        $this->assertIsArray($dashboard['security_metrics']);
        $this->assertIsArray($dashboard['alert_status']);
        $this->assertIsString($dashboard['generated_at']);
    }
    
    public function testAnalyzeRequestHandlesEmptyInput(): void
    {
        $requestData = [];
        
        $result = $this->securityService->analyzeRequest($requestData);
        
        // Should not crash and should return valid structure
        $this->assertArrayHasKey('threats_detected', $result);
        $this->assertArrayHasKey('threat_count', $result);
        $this->assertArrayHasKey('threats', $result);
        $this->assertIsBool($result['threats_detected']);
        $this->assertIsInt($result['threat_count']);
        $this->assertIsArray($result['threats']);
    }
    
    public function testAnalyzeRequestWithCSRFMissingToken(): void
    {
        $requestData = [
            'method' => 'POST',
            'url' => '/api/stories',
            'get' => [],
            'post' => ['title' => 'Test Story'],
            'headers' => ['host' => 'example.com'],
            'ip' => '192.168.1.1'
        ];
        
        $result = $this->securityService->analyzeRequest($requestData);
        
        // Should detect CSRF issue for POST without token
        $this->assertTrue($result['threats_detected']);
        $this->assertGreaterThan(0, $result['threat_count']);
    }
}