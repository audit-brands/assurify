<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Services\ContentModerationService;
use App\Services\CacheService;
use App\Services\SecurityMonitorService;

class ContentModerationServiceTest extends TestCase
{
    private ContentModerationService $moderationService;
    private CacheService $cacheService;
    private SecurityMonitorService $securityService;
    
    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->securityService = $this->createMock(SecurityMonitorService::class);
        
        $this->moderationService = new ContentModerationService(
            $this->cacheService,
            $this->securityService
        );
    }
    
    public function testModerateContentWithCleanContent(): void
    {
        $content = "This is a perfectly normal comment about technology.";
        $contentType = "comment";
        $contentId = 123;
        $userId = 456;
        $context = ['ip' => '127.0.0.1'];
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId,
            $context
        );
        
        $this->assertArrayHasKey('content_type', $result);
        $this->assertArrayHasKey('content_id', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('ai_analysis', $result);
        $this->assertArrayHasKey('human_review_required', $result);
        $this->assertArrayHasKey('moderation_time', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $this->assertEquals($contentType, $result['content_type']);
        $this->assertEquals($contentId, $result['content_id']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertContains($result['action'], ['auto_approve', 'auto_reject', 'flag_for_review']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsArray($result['ai_analysis']);
        $this->assertIsBool($result['human_review_required']);
        $this->assertIsFloat($result['moderation_time']);
    }
    
    public function testModerateContentWithSpamContent(): void
    {
        $content = "CLICK HERE FOR FREE MONEY! URGENT! Act now and earn $1000 from home!";
        $contentType = "story";
        $contentId = 789;
        $userId = 101;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        $this->assertNotEquals('auto_approve', $result['action']);
        $this->assertGreaterThan(0, $result['ai_analysis']['spam_analysis']['spam_score']);
        $this->assertTrue($result['ai_analysis']['spam_analysis']['is_spam']);
    }
    
    public function testModerateContentWithToxicContent(): void
    {
        $content = "You are such a stupid idiot! I hate you and you should die!";
        $contentType = "comment";
        $contentId = 555;
        $userId = 666;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        $this->assertNotEquals('auto_approve', $result['action']);
        $this->assertGreaterThan(0, $result['ai_analysis']['toxicity_analysis']['toxicity_score']);
        $this->assertTrue($result['ai_analysis']['toxicity_analysis']['is_toxic']);
    }
    
    public function testGetModerationQueueReturnsArray(): void
    {
        $filters = ['content_type' => 'story', 'limit' => 10];
        
        $queue = $this->moderationService->getModerationQueue($filters);
        
        $this->assertIsArray($queue);
        // Empty array is valid for mock implementation
    }
    
    public function testProcessHumanDecisionReturnsBoolean(): void
    {
        $queueId = 123;
        $reviewerId = 456;
        $decision = 'approved';
        $notes = 'Content is acceptable';
        
        $result = $this->moderationService->processHumanDecision(
            $queueId,
            $reviewerId,
            $decision,
            $notes
        );
        
        $this->assertIsBool($result);
        // Will return false due to mocked database
    }
    
    public function testGetModerationStatsReturnsMetrics(): void
    {
        $this->cacheService->method('get')->willReturn(null);
        
        $stats = $this->moderationService->getModerationStats('24h');
        
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('total_moderated', $stats);
        $this->assertArrayHasKey('auto_approved', $stats);
        $this->assertArrayHasKey('auto_rejected', $stats);
        $this->assertArrayHasKey('flagged_for_review', $stats);
        $this->assertArrayHasKey('human_reviewed', $stats);
        $this->assertArrayHasKey('spam_detected', $stats);
        $this->assertArrayHasKey('toxicity_detected', $stats);
        $this->assertArrayHasKey('duplicate_detected', $stats);
        $this->assertArrayHasKey('average_processing_time', $stats);
        $this->assertArrayHasKey('false_positive_rate', $stats);
        $this->assertArrayHasKey('queue_backlog', $stats);
        
        $this->assertEquals('24h', $stats['period']);
        $this->assertIsInt($stats['total_moderated']);
        $this->assertIsInt($stats['auto_approved']);
        $this->assertIsInt($stats['auto_rejected']);
        $this->assertIsFloat($stats['average_processing_time']);
        $this->assertIsFloat($stats['false_positive_rate']);
    }
    
    public function testModerateContentWithEmptyContent(): void
    {
        $content = "";
        $contentType = "comment";
        $contentId = 1;
        $userId = 1;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        // Should handle empty content gracefully
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('ai_analysis', $result);
        $this->assertIsArray($result['ai_analysis']);
    }
    
    public function testModerateContentWithLongContent(): void
    {
        $content = str_repeat("This is a very long piece of content. ", 100);
        $contentType = "story";
        $contentId = 999;
        $userId = 888;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        // Should handle long content without issues
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('moderation_time', $result);
        $this->assertGreaterThan(0, $result['moderation_time']);
    }
    
    public function testSentimentAnalysisWithPositiveContent(): void
    {
        $content = "This is awesome! I love this fantastic article. Great work!";
        $contentType = "comment";
        $contentId = 1;
        $userId = 1;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        $sentiment = $result['ai_analysis']['sentiment_analysis'];
        $this->assertArrayHasKey('sentiment', $sentiment);
        $this->assertArrayHasKey('score', $sentiment);
        $this->assertArrayHasKey('confidence', $sentiment);
        $this->assertEquals('positive', $sentiment['sentiment']);
        $this->assertGreaterThan(0, $sentiment['score']);
    }
    
    public function testSentimentAnalysisWithNegativeContent(): void
    {
        $content = "This is terrible and awful. I hate this horrible disgusting content.";
        $contentType = "comment";
        $contentId = 2;
        $userId = 2;
        
        $result = $this->moderationService->moderateContent(
            $contentType,
            $contentId,
            $content,
            $userId
        );
        
        $sentiment = $result['ai_analysis']['sentiment_analysis'];
        $this->assertEquals('negative', $sentiment['sentiment']);
        $this->assertLessThan(0, $sentiment['score']);
    }
}