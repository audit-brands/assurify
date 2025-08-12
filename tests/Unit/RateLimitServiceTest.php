<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\RateLimitService;
use App\Services\CacheService;
use App\Services\LoggerService;

class RateLimitServiceTest extends TestCase
{
    private RateLimitService $rateLimitService;
    private CacheService $cacheService;
    private LoggerService $loggerService;

    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->loggerService = new LoggerService();
        $this->rateLimitService = new RateLimitService($this->cacheService, $this->loggerService);
        
        // Clean cache before each test
        $this->cacheService->flush();
    }

    protected function tearDown(): void
    {
        $this->cacheService->flush();
    }

    public function testCheckLimitAllowsWithinLimit(): void
    {
        $identifier = 'test_user';
        $limit = 5;
        $window = 3600;
        $action = 'test_action';
        
        // Should allow requests within limit
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue(
                $this->rateLimitService->checkLimit($identifier, $limit, $window, $action),
                "Request {$i} should be allowed"
            );
        }
    }

    public function testCheckLimitBlocksOverLimit(): void
    {
        $identifier = 'test_user';
        $limit = 3;
        $window = 3600;
        $action = 'test_action';
        
        // Use up the limit
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->rateLimitService->checkLimit($identifier, $limit, $window, $action));
        }
        
        // Next request should be blocked
        $this->assertFalse($this->rateLimitService->checkLimit($identifier, $limit, $window, $action));
    }

    public function testGetRemainingAttempts(): void
    {
        $identifier = 'test_user';
        $limit = 5;
        $window = 3600;
        $action = 'test_action';
        
        // Initially should have full limit
        $remaining = $this->rateLimitService->getRemainingAttempts($identifier, $limit, $window, $action);
        $this->assertEquals($limit, $remaining);
        
        // After one request, should have one less
        $this->rateLimitService->checkLimit($identifier, $limit, $window, $action);
        $remaining = $this->rateLimitService->getRemainingAttempts($identifier, $limit, $window, $action);
        $this->assertEquals($limit - 1, $remaining);
    }

    public function testResetLimit(): void
    {
        $identifier = 'test_user';
        $limit = 3;
        $window = 3600;
        $action = 'test_action';
        
        // Use up the limit
        for ($i = 0; $i < $limit; $i++) {
            $this->rateLimitService->checkLimit($identifier, $limit, $window, $action);
        }
        
        // Should be blocked
        $this->assertFalse($this->rateLimitService->checkLimit($identifier, $limit, $window, $action));
        
        // Reset the limit
        $this->rateLimitService->resetLimit($identifier, $action);
        
        // Should be allowed again
        $this->assertTrue($this->rateLimitService->checkLimit($identifier, $limit, $window, $action));
    }

    public function testCheckIpLimit(): void
    {
        // Mock IP address
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $this->assertTrue($this->rateLimitService->checkIpLimit('test_action', 5, 3600));
    }

    public function testCheckUserLimit(): void
    {
        $userId = 123;
        
        $this->assertTrue($this->rateLimitService->checkUserLimit($userId, 'test_action', 5, 3600));
    }

    public function testCheckLoginAttempts(): void
    {
        $identifier = 'user@example.com';
        $limit = 3;
        
        // Should allow attempts within limit
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->rateLimitService->checkLoginAttempts($identifier, $limit));
        }
        
        // Should block further attempts
        $this->assertFalse($this->rateLimitService->checkLoginAttempts($identifier, $limit));
    }

    public function testCheckApiRequests(): void
    {
        $apiKey = 'test_api_key_123';
        
        $this->assertTrue($this->rateLimitService->checkApiRequests($apiKey, 10, 3600));
    }

    public function testCheckStorySubmission(): void
    {
        $userId = 456;
        
        // Should allow story submissions within daily limit
        $this->assertTrue($this->rateLimitService->checkStorySubmission($userId, 3, 86400));
    }

    public function testCheckCommentSubmission(): void
    {
        $userId = 789;
        
        $this->assertTrue($this->rateLimitService->checkCommentSubmission($userId, 10, 3600));
    }

    public function testCheckVoting(): void
    {
        $userId = 101;
        
        $this->assertTrue($this->rateLimitService->checkVoting($userId, 50, 3600));
    }

    public function testCheckPasswordReset(): void
    {
        $identifier = 'user@example.com';
        
        $this->assertTrue($this->rateLimitService->checkPasswordReset($identifier, 2, 3600));
    }

    public function testCheckSearchRequests(): void
    {
        $identifier = 'search_user';
        
        $this->assertTrue($this->rateLimitService->checkSearchRequests($identifier, 20, 3600));
    }

    public function testIsIpWhitelisted(): void
    {
        $this->assertTrue($this->rateLimitService->isIpWhitelisted('127.0.0.1'));
        $this->assertTrue($this->rateLimitService->isIpWhitelisted('::1'));
        $this->assertFalse($this->rateLimitService->isIpWhitelisted('192.168.1.1'));
    }

    public function testIsIpBlacklisted(): void
    {
        // Initially should not be blacklisted
        $ip = '192.168.1.200';
        $this->assertFalse($this->rateLimitService->isIpBlacklisted($ip));
    }

    public function testAddAndRemoveFromBlacklist(): void
    {
        $ip = '192.168.1.201';
        $reason = 'Suspicious activity';
        
        // Add to blacklist
        $this->rateLimitService->addToBlacklist($ip, $reason);
        
        // Remove from blacklist
        $this->rateLimitService->removeFromBlacklist($ip);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testResetUserLimits(): void
    {
        $userId = 202;
        
        // Use some limits first
        $this->rateLimitService->checkUserLimit($userId, 'story_submission', 5);
        $this->rateLimitService->checkUserLimit($userId, 'comment_submission', 10);
        
        // Reset all user limits
        $this->rateLimitService->resetUserLimits($userId);
        
        // Should be able to make requests again
        $this->assertTrue($this->rateLimitService->checkUserLimit($userId, 'story_submission', 5));
        $this->assertTrue($this->rateLimitService->checkUserLimit($userId, 'comment_submission', 10));
    }

    public function testResetIpLimits(): void
    {
        $ip = '192.168.1.202';
        
        // Reset IP limits (should not throw any errors)
        $this->rateLimitService->resetIpLimits($ip);
        
        $this->assertTrue(true);
    }

    public function testGetRateLimitStats(): void
    {
        $stats = $this->rateLimitService->getRateLimitStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_rate_limits', $stats);
        $this->assertArrayHasKey('active_limits', $stats);
        $this->assertArrayHasKey('blocked_requests_today', $stats);
        $this->assertArrayHasKey('top_limited_ips', $stats);
        $this->assertArrayHasKey('top_limited_actions', $stats);
    }

    public function testCheckSuspiciousActivity(): void
    {
        $identifier = 'suspicious_user';
        
        // Check for suspicious activity (should not throw errors)
        $result = $this->rateLimitService->checkSuspiciousActivity($identifier);
        
        $this->assertIsBool($result);
    }

    public function testApplyDynamicLimits(): void
    {
        $identifier = 'dynamic_user';
        $action = 'general';
        
        $limits = $this->rateLimitService->applyDynamicLimits($identifier, $action);
        
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('limit', $limits);
        $this->assertArrayHasKey('window', $limits);
        $this->assertIsInt($limits['limit']);
        $this->assertIsInt($limits['window']);
        $this->assertGreaterThan(0, $limits['limit']);
        $this->assertGreaterThan(0, $limits['window']);
    }
}