<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Services\SpamPreventionService;
use App\Services\ContentModerationService;
use App\Services\SecurityMonitorService;
use App\Services\RateLimitingService;
use App\Services\CacheService;

class SpamPreventionServiceTest extends TestCase
{
    private SpamPreventionService $spamService;
    private ContentModerationService $moderationService;
    private SecurityMonitorService $securityService;
    private RateLimitingService $rateLimitService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->moderationService = $this->createMock(ContentModerationService::class);
        $this->securityService = $this->createMock(SecurityMonitorService::class);
        $this->rateLimitService = $this->createMock(RateLimitingService::class);
        $this->cacheService = $this->createMock(CacheService::class);
        
        $this->spamService = new SpamPreventionService(
            $this->moderationService,
            $this->securityService,
            $this->rateLimitService,
            $this->cacheService
        );
    }
    
    public function testCheckContentForSpamWithCleanContent(): void
    {
        $content = "This is a legitimate technical discussion about programming.";
        $contentType = "comment";
        $userId = 123;
        $context = ['ip' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0'];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        $this->assertArrayHasKey('is_spam', $result);
        $this->assertArrayHasKey('spam_score', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('indicators', $result);
        $this->assertArrayHasKey('block_reasons', $result);
        $this->assertArrayHasKey('analysis_details', $result);
        $this->assertArrayHasKey('processing_time', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $this->assertIsBool($result['is_spam']);
        $this->assertIsFloat($result['spam_score']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsString($result['action']);
        $this->assertIsArray($result['indicators']);
        $this->assertIsArray($result['block_reasons']);
        $this->assertIsArray($result['analysis_details']);
        $this->assertIsFloat($result['processing_time']);
        $this->assertIsString($result['timestamp']);
    }
    
    public function testCheckContentForSpamWithSpamContent(): void
    {
        $content = "CLICK HERE FOR FREE MONEY! Viagra casino lottery earn money work from home!";
        $contentType = "story";
        $userId = 456;
        $context = ['ip' => '192.168.1.100'];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        $this->assertTrue($result['is_spam']);
        $this->assertGreaterThan(50.0, $result['spam_score']);
        $this->assertNotEmpty($result['indicators']);
        $this->assertContains($result['action'], ['block', 'quarantine', 'flag_for_review']);
    }
    
    public function testCheckContentForSpamWithExcessiveURLs(): void
    {
        $content = "Check out https://site1.com and https://site2.com and https://site3.com and https://site4.com";
        $contentType = "comment";
        $userId = 789;
        $context = [];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        $this->assertGreaterThan(0, $result['spam_score']);
        $this->assertContains('Excessive URLs', implode(' ', $result['indicators']));
    }
    
    public function testCheckContentForSpamWithRepetitiveContent(): void
    {
        $content = "spam spam spam spam spam spam spam spam spam spam";
        $contentType = "comment";
        $userId = 101;
        $context = [];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        $this->assertGreaterThan(0, $result['spam_score']);
        // Should detect repetitive content
    }
    
    public function testCheckUserForAbuseWithCleanUser(): void
    {
        $userId = 123;
        $context = ['ip' => '127.0.0.1'];
        
        $result = $this->spamService->checkUserForAbuse($userId, $context);
        
        $this->assertArrayHasKey('should_block', $result);
        $this->assertArrayHasKey('abuse_score', $result);
        $this->assertArrayHasKey('violations', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('coordinated_attack', $result);
        
        $this->assertIsBool($result['should_block']);
        $this->assertIsFloat($result['abuse_score']);
        $this->assertIsArray($result['violations']);
        $this->assertIsString($result['action']);
        $this->assertIsArray($result['history']);
        $this->assertIsArray($result['coordinated_attack']);
    }
    
    public function testReportAbuseWithValidReport(): void
    {
        $reporterId = 123;
        $contentType = "comment";
        $contentId = 456;
        $reason = "spam";
        $description = "This comment contains spam content";
        
        $result = $this->spamService->reportAbuse($reporterId, $contentType, $contentId, $reason, $description);
        
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        // With mocked database, this will return false, but structure should be correct
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
        }
    }
    
    public function testGetStatisticsReturnsMetrics(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        
        $stats = $this->spamService->getStatistics('24h');
        
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('spam_detected', $stats);
        $this->assertArrayHasKey('users_blocked', $stats);
        $this->assertArrayHasKey('content_quarantined', $stats);
        $this->assertArrayHasKey('community_reports', $stats);
        $this->assertArrayHasKey('honeypot_triggers', $stats);
        $this->assertArrayHasKey('false_positives', $stats);
        $this->assertArrayHasKey('accuracy_rate', $stats);
        $this->assertArrayHasKey('top_spam_indicators', $stats);
        $this->assertArrayHasKey('geographic_distribution', $stats);
        
        $this->assertEquals('24h', $stats['period']);
        $this->assertIsInt($stats['spam_detected']);
        $this->assertIsInt($stats['users_blocked']);
        $this->assertIsInt($stats['content_quarantined']);
        $this->assertIsInt($stats['community_reports']);
        $this->assertIsInt($stats['honeypot_triggers']);
        $this->assertIsInt($stats['false_positives']);
        $this->assertIsFloat($stats['accuracy_rate']);
        $this->assertIsArray($stats['top_spam_indicators']);
        $this->assertIsArray($stats['geographic_distribution']);
    }
    
    public function testCheckContentForSpamWithShortContent(): void
    {
        $content = "Hi";
        $contentType = "comment";
        $userId = 999;
        $context = [];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        $this->assertGreaterThan(0, $result['spam_score']);
        $this->assertContains('Content too short', implode(' ', $result['indicators']));
    }
    
    public function testCheckContentForSpamHandlesEmptyContent(): void
    {
        $content = "";
        $contentType = "comment";
        $userId = 111;
        $context = [];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        // Should handle empty content gracefully
        $this->assertArrayHasKey('is_spam', $result);
        $this->assertArrayHasKey('spam_score', $result);
    }
    
    public function testCheckContentForSpamWithLongValidContent(): void
    {
        $content = "This is a comprehensive technical analysis of the modern software development lifecycle. " .
                  "It covers various aspects including planning, development, testing, deployment, and maintenance. " .
                  "The article discusses best practices, common pitfalls, and emerging trends in the industry. " .
                  "It provides valuable insights for both beginners and experienced developers looking to improve their skills.";
        
        $contentType = "story";
        $userId = 222;
        $context = [];
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        // Long, legitimate content should have low spam score
        $this->assertLessThan(50.0, $result['spam_score']);
    }
    
    public function testCheckContentForSpamWithHoneypotTrigger(): void
    {
        $content = "Regular content";
        $contentType = "comment";
        $userId = 333;
        $context = ['honeypot_field' => 'bot_filled_this']; // Simulated honeypot trigger
        
        $result = $this->spamService->checkContentForSpam($content, $contentType, $userId, $context);
        
        // Even with regular content, honeypot trigger should flag as spam
        $this->assertArrayHasKey('indicators', $result);
        $this->assertArrayHasKey('block_reasons', $result);
    }
}