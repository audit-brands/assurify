<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\DuplicateDetectionService;
use App\Services\CacheService;

class DuplicateDetectionServiceTest extends TestCase
{
    private DuplicateDetectionService $duplicateService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->duplicateService = new DuplicateDetectionService($this->cacheService);
    }
    
    public function testCheckDuplicates(): void
    {
        $content = [
            'title' => 'Introduction to Machine Learning',
            'url' => 'https://example.com/ml-intro',
            'content' => 'Machine learning is a subset of artificial intelligence that focuses on algorithms that can learn from data.',
            'description' => 'A comprehensive guide to ML basics'
        ];
        
        $result = $this->duplicateService->checkDuplicates($content);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_duplicate', $result);
        $this->assertArrayHasKey('similarity_score', $result);
        $this->assertArrayHasKey('duplicate_type', $result);
        $this->assertArrayHasKey('similar_content', $result);
        $this->assertArrayHasKey('exact_duplicates', $result);
        $this->assertArrayHasKey('url_variants', $result);
        $this->assertArrayHasKey('content_fingerprint', $result);
        $this->assertArrayHasKey('recommendations', $result);
        
        $this->assertIsBool($result['is_duplicate']);
        $this->assertIsFloat($result['similarity_score']);
        $this->assertIsArray($result['similar_content']);
        $this->assertIsArray($result['exact_duplicates']);
        $this->assertIsString($result['content_fingerprint']);
    }
    
    public function testCalculateContentSimilarity(): void
    {
        $text1 = 'Machine learning algorithms can analyze large datasets to find patterns.';
        $text2 = 'ML algorithms analyze big data to discover patterns and insights.';
        $text3 = 'Cooking recipes require fresh ingredients and proper techniques.';
        
        $similarity12 = $this->duplicateService->calculateContentSimilarity($text1, $text2);
        $similarity13 = $this->duplicateService->calculateContentSimilarity($text1, $text3);
        
        $this->assertGreaterThanOrEqual(0, $similarity12);
        $this->assertLessThanOrEqual(1, $similarity12);
        $this->assertGreaterThanOrEqual(0, $similarity13);
        $this->assertLessThanOrEqual(1, $similarity13);
        
        // Similar content should have higher similarity
        $this->assertGreaterThan($similarity13, $similarity12);
    }
    
    public function testCalculateCosineSimilarity(): void
    {
        $text1 = 'artificial intelligence machine learning';
        $text2 = 'machine learning artificial intelligence';
        $text3 = 'cooking recipes food preparation';
        
        $similarity12 = $this->duplicateService->calculateCosineSimilarity($text1, $text2);
        $similarity13 = $this->duplicateService->calculateCosineSimilarity($text1, $text3);
        
        $this->assertGreaterThanOrEqual(0, $similarity12);
        $this->assertLessThanOrEqual(1, $similarity12);
        
        // Identical words in different order should have high similarity
        $this->assertGreaterThan(0.9, $similarity12);
        $this->assertGreaterThan($similarity13, $similarity12);
    }
    
    public function testCalculateJaccardSimilarity(): void
    {
        $text1 = 'machine learning artificial intelligence';
        $text2 = 'artificial intelligence machine learning data science';
        $text3 = 'cooking recipes food preparation';
        
        $similarity12 = $this->duplicateService->calculateJaccardSimilarity($text1, $text2);
        $similarity13 = $this->duplicateService->calculateJaccardSimilarity($text1, $text3);
        
        $this->assertGreaterThanOrEqual(0, $similarity12);
        $this->assertLessThanOrEqual(1, $similarity12);
        
        // Should have some overlap
        $this->assertGreaterThan(0.5, $similarity12);
        $this->assertGreaterThan($similarity13, $similarity12);
    }
    
    public function testCalculateLevenshteinSimilarity(): void
    {
        $text1 = 'hello world';
        $text2 = 'hello world';
        $text3 = 'hello word';
        $text4 = 'goodbye universe';
        
        $similarity12 = $this->duplicateService->calculateLevenshteinSimilarity($text1, $text2);
        $similarity13 = $this->duplicateService->calculateLevenshteinSimilarity($text1, $text3);
        $similarity14 = $this->duplicateService->calculateLevenshteinSimilarity($text1, $text4);
        
        // Identical strings should have similarity 1.0
        $this->assertEquals(1.0, $similarity12);
        
        // Small difference should have high similarity
        $this->assertGreaterThan(0.8, $similarity13);
        
        // Completely different should have low similarity
        $this->assertLessThan(0.5, $similarity14);
    }
    
    public function testCalculateShingleSimilarity(): void
    {
        $text1 = 'the quick brown fox jumps over lazy dog';
        $text2 = 'quick brown fox jumps over the lazy dog';
        $text3 = 'the slow white cat walks under busy mouse';
        
        $similarity12 = $this->duplicateService->calculateShingleSimilarity($text1, $text2);
        $similarity13 = $this->duplicateService->calculateShingleSimilarity($text1, $text3);
        
        $this->assertGreaterThanOrEqual(0, $similarity12);
        $this->assertLessThanOrEqual(1, $similarity12);
        
        // Similar word sequences should have high similarity
        $this->assertGreaterThan($similarity13, $similarity12);
    }
    
    public function testCalculateTitleSimilarity(): void
    {
        $title1 = 'Introduction to Machine Learning';
        $title2 = 'Intro to Machine Learning';
        $title3 = 'Machine Learning Introduction';
        $title4 = 'Advanced Database Management';
        
        $similarity12 = $this->duplicateService->calculateTitleSimilarity($title1, $title2);
        $similarity13 = $this->duplicateService->calculateTitleSimilarity($title1, $title3);
        $similarity14 = $this->duplicateService->calculateTitleSimilarity($title1, $title4);
        
        $this->assertGreaterThanOrEqual(0, $similarity12);
        $this->assertLessThanOrEqual(1, $similarity12);
        
        // Similar titles should have reasonable similarity
        $this->assertGreaterThan(0.5, $similarity12);
        $this->assertGreaterThan(0.35, $similarity13);
        $this->assertLessThan(0.5, $similarity14);
    }
    
    public function testCalculateUrlSimilarity(): void
    {
        $url1 = 'https://example.com/article/ml-intro';
        $url2 = 'http://example.com/article/ml-intro';
        $url3 = 'https://www.example.com/article/ml-intro/';
        $url4 = 'https://different.com/other-article';
        
        $similarity12 = $this->duplicateService->calculateUrlSimilarity($url1, $url2);
        $similarity13 = $this->duplicateService->calculateUrlSimilarity($url1, $url3);
        $similarity14 = $this->duplicateService->calculateUrlSimilarity($url1, $url4);
        
        // Same URL with different protocol should be very similar
        $this->assertGreaterThan(0.9, $similarity12);
        
        // Same URL with www and trailing slash should be very similar
        $this->assertGreaterThan(0.9, $similarity13);
        
        // Different domain should have low similarity
        $this->assertLessThan(0.8, $similarity14);
    }
    
    public function testGenerateContentFingerprint(): void
    {
        $content1 = [
            'title' => 'Test Article',
            'content' => 'This is test content for fingerprinting',
            'url' => 'https://example.com/test'
        ];
        
        $content2 = [
            'title' => 'Test Article',
            'content' => 'This is test content for fingerprinting',
            'url' => 'https://example.com/test'
        ];
        
        $content3 = [
            'title' => 'Different Article',
            'content' => 'Completely different content here',
            'url' => 'https://example.com/different'
        ];
        
        $fingerprint1 = $this->duplicateService->generateContentFingerprint($content1);
        $fingerprint2 = $this->duplicateService->generateContentFingerprint($content2);
        $fingerprint3 = $this->duplicateService->generateContentFingerprint($content3);
        
        $this->assertIsString($fingerprint1);
        $this->assertEquals($fingerprint1, $fingerprint2); // Same content should have same fingerprint
        $this->assertNotEquals($fingerprint1, $fingerprint3); // Different content should have different fingerprints
        
        // Validate JSON structure
        $decoded = json_decode($fingerprint1, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('title_hash', $decoded);
        $this->assertArrayHasKey('content_hash', $decoded);
        $this->assertArrayHasKey('url_hash', $decoded);
        $this->assertArrayHasKey('combined_hash', $decoded);
    }
    
    public function testEmptyContentHandling(): void
    {
        $emptyContent = [
            'title' => '',
            'content' => '',
            'url' => '',
            'description' => ''
        ];
        
        $result = $this->duplicateService->checkDuplicates($emptyContent);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['is_duplicate']);
        $this->assertEquals(0.0, $result['similarity_score']);
        $this->assertEmpty($result['similar_content']);
    }
    
    public function testSimilarityThresholds(): void
    {
        $content = [
            'title' => 'Test Content for Thresholds',
            'content' => 'This is content to test similarity thresholds and duplicate detection',
            'url' => 'https://example.com/test-thresholds'
        ];
        
        $options = [
            'limit' => 10,
            'threshold' => 0.9
        ];
        
        $result = $this->duplicateService->checkDuplicates($content, $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_duplicate', $result);
        $this->assertArrayHasKey('similarity_score', $result);
    }
    
    public function testUrlNormalization(): void
    {
        $urls = [
            'HTTPS://WWW.EXAMPLE.COM/PATH/',
            'http://example.com/path',
            'https://example.com/path/',
            'https://www.example.com/path?param=1',
            'https://example.com/path#fragment'
        ];
        
        foreach ($urls as $url) {
            $similarity = $this->duplicateService->calculateUrlSimilarity($urls[0], $url);
            $this->assertGreaterThan(0.7, $similarity); // All should be similar after normalization
        }
    }
    
    public function testLargeContentPerformance(): void
    {
        $largeContent = str_repeat('This is a large content string for performance testing. ', 1000);
        
        $content1 = [
            'title' => 'Performance Test Article',
            'content' => $largeContent,
            'url' => 'https://example.com/performance-test'
        ];
        
        $content2 = [
            'title' => 'Performance Test Article Modified',
            'content' => $largeContent . ' Additional content.',
            'url' => 'https://example.com/performance-test-2'
        ];
        
        $startTime = microtime(true);
        $similarity = $this->duplicateService->calculateContentSimilarity($content1['content'], $content2['content']);
        $endTime = microtime(true);
        
        $this->assertLessThan(5.0, $endTime - $startTime); // Should complete within 5 seconds
        $this->assertGreaterThan(0.8, $similarity); // Should detect high similarity
    }
}