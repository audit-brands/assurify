<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FeedService;

class FeedServiceTest extends TestCase
{
    private FeedService $feedService;

    protected function setUp(): void
    {
        $this->feedService = new FeedService();
    }

    public function testGenerateStoriesFeedReturnsXml(): void
    {
        $feed = $this->feedService->generateStoriesFeed();
        
        $this->assertIsString($feed);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $feed);
        $this->assertStringContains('<rss version="2.0"', $feed);
        $this->assertStringContains('<channel>', $feed);
        $this->assertStringContains('<title>Lobsters</title>', $feed);
    }

    public function testGenerateStoriesFeedWithTag(): void
    {
        $feed = $this->feedService->generateStoriesFeed('php');
        
        $this->assertIsString($feed);
        $this->assertStringContains('<title>Lobsters: php</title>', $feed);
    }

    public function testGenerateCommentsFeedReturnsXml(): void
    {
        $feed = $this->feedService->generateCommentsFeed();
        
        $this->assertIsString($feed);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $feed);
        $this->assertStringContains('<rss version="2.0"', $feed);
        $this->assertStringContains('<channel>', $feed);
        $this->assertStringContains('<title>Lobsters: Comments</title>', $feed);
    }

    public function testGenerateUserActivityFeed(): void
    {
        $feed = $this->feedService->generateUserActivityFeed('testuser');
        
        $this->assertIsString($feed);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $feed);
        $this->assertStringContains('<rss version="2.0"', $feed);
        $this->assertStringContains('<channel>', $feed);
        $this->assertStringContains('<title>Lobsters: testuser\'s activity</title>', $feed);
    }

    public function testGetAvailableTagFeedsReturnsArray(): void
    {
        $tags = $this->feedService->getAvailableTagFeeds();
        
        $this->assertIsArray($tags);
    }

    public function testFeedIsValidXml(): void
    {
        $feed = $this->feedService->generateStoriesFeed();
        
        // Test that the XML is valid
        $doc = new \DOMDocument();
        $result = $doc->loadXML($feed);
        
        $this->assertTrue($result, 'Generated feed should be valid XML');
    }

    public function testFeedContainsRequiredElements(): void
    {
        $feed = $this->feedService->generateStoriesFeed();
        $doc = new \DOMDocument();
        $doc->loadXML($feed);
        
        // Check for required RSS elements
        $this->assertEquals(1, $doc->getElementsByTagName('rss')->length);
        $this->assertEquals(1, $doc->getElementsByTagName('channel')->length);
        $this->assertEquals(1, $doc->getElementsByTagName('title')->length);
        $this->assertEquals(1, $doc->getElementsByTagName('description')->length);
        $this->assertEquals(1, $doc->getElementsByTagName('link')->length);
    }
}