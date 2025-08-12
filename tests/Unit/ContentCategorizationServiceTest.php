<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\ContentCategorizationService;
use App\Services\CacheService;

class ContentCategorizationServiceTest extends TestCase
{
    private ContentCategorizationService $categorizationService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->categorizationService = new ContentCategorizationService($this->cacheService);
    }
    
    public function testAnalyzeContent(): void
    {
        $content = [
            'title' => 'Advanced Machine Learning Techniques',
            'url' => 'https://example.com/ml-article',
            'description' => 'Deep dive into modern ML algorithms',
            'content' => 'This article explores neural networks, deep learning, and AI applications in various industries.',
            'tags' => ['ai', 'programming']
        ];
        
        $analysis = $this->categorizationService->analyzeContent($content);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('categories', $analysis);
        $this->assertArrayHasKey('suggested_tags', $analysis);
        $this->assertArrayHasKey('quality_score', $analysis);
        $this->assertArrayHasKey('sentiment', $analysis);
        $this->assertArrayHasKey('technical_level', $analysis);
    }
    
    public function testDetectCategories(): void
    {
        $text = 'Machine learning and artificial intelligence are revolutionizing technology';
        $url = 'https://techblog.com/ai-revolution';
        
        $categories = $this->categorizationService->detectCategories($text, $url);
        
        $this->assertIsArray($categories);
        // Should detect technology-related categories
        $this->assertNotEmpty($categories);
    }
    
    public function testSuggestTags(): void
    {
        $text = 'JavaScript frameworks like React and Vue are popular for web development';
        $url = 'https://webdev.com/js-frameworks';
        $existingTags = ['javascript'];
        
        $suggestions = $this->categorizationService->suggestTags($text, $url, $existingTags);
        
        $this->assertIsArray($suggestions);
        $this->assertNotContains('javascript', $suggestions); // Should not suggest existing tags
    }
    
    public function testCalculateQualityScore(): void
    {
        $content = [
            'title' => 'Comprehensive Guide to Modern Web Development',
            'content' => 'This detailed article covers HTML5, CSS3, JavaScript ES6, and modern frameworks. It includes practical examples and best practices for building responsive web applications.',
            'url' => 'https://example.com/web-dev-guide',
            'tags' => ['web', 'development', 'javascript', 'html', 'css']
        ];
        
        $score = $this->categorizationService->calculateQualityScore($content);
        
        $this->assertIsArray($score);
        $this->assertArrayHasKey('overall', $score);
        $this->assertArrayHasKey('content_length', $score);
        $this->assertArrayHasKey('readability', $score);
        $this->assertArrayHasKey('structure', $score);
        $this->assertArrayHasKey('uniqueness', $score);
        
        $this->assertGreaterThanOrEqual(0, $score['overall']);
        $this->assertLessThanOrEqual(1, $score['overall']);
    }
    
    public function testAnalyzeSentiment(): void
    {
        $positiveText = 'This is an amazing and wonderful article about great technologies';
        $neutralText = 'This article describes various programming languages and their features';
        $negativeText = 'This terrible article is poorly written and confusing';
        
        $positiveSentiment = $this->categorizationService->analyzeSentiment($positiveText);
        $neutralSentiment = $this->categorizationService->analyzeSentiment($neutralText);
        $negativeSentiment = $this->categorizationService->analyzeSentiment($negativeText);
        
        $this->assertIsArray($positiveSentiment);
        $this->assertArrayHasKey('score', $positiveSentiment);
        $this->assertArrayHasKey('label', $positiveSentiment);
        
        $this->assertGreaterThan(0, $positiveSentiment['score']);
        $this->assertLessThan(0, $negativeSentiment['score']);
        $this->assertEquals('positive', $positiveSentiment['label']);
        $this->assertEquals('negative', $negativeSentiment['label']);
    }
    
    public function testAssessTechnicalLevel(): void
    {
        $beginnerText = 'HTML is a markup language used to create web pages. It uses tags to structure content.';
        $advancedText = 'Implementing microservices architecture with Kubernetes orchestration, event sourcing patterns, and CQRS implementation requires understanding distributed systems complexity.';
        
        $beginnerLevel = $this->categorizationService->assessTechnicalLevel($beginnerText);
        $advancedLevel = $this->categorizationService->assessTechnicalLevel($advancedText);
        
        $this->assertIsString($beginnerLevel);
        $this->assertIsString($advancedLevel);
        $this->assertContains($beginnerLevel, ['beginner', 'intermediate', 'advanced']);
        $this->assertContains($advancedLevel, ['beginner', 'intermediate', 'advanced']);
    }
    
    public function testCategoryDomainMapping(): void
    {
        $githubUrl = 'https://github.com/user/repo';
        $stackoverflowUrl = 'https://stackoverflow.com/questions/123/title';
        $mediumUrl = 'https://medium.com/publication/article-title';
        
        $githubCategories = $this->categorizationService->getCategoryFromDomain($githubUrl);
        $stackCategories = $this->categorizationService->getCategoryFromDomain($stackoverflowUrl);
        $mediumCategories = $this->categorizationService->getCategoryFromDomain($mediumUrl);
        
        $this->assertIsArray($githubCategories);
        $this->assertIsArray($stackCategories);
        $this->assertIsArray($mediumCategories);
        
        $this->assertContains('programming', $githubCategories);
        $this->assertContains('programming', $stackCategories);
        $this->assertContains('blog', $mediumCategories);
    }
    
    public function testEmptyContentHandling(): void
    {
        $emptyContent = [
            'title' => '',
            'content' => '',
            'url' => '',
            'tags' => []
        ];
        
        $analysis = $this->categorizationService->analyzeContent($emptyContent);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('categories', $analysis);
        $this->assertArrayHasKey('quality_score', $analysis);
        
        // Quality should be low for empty content
        $this->assertLessThan(0.3, $analysis['quality_score']['overall']);
    }
    
    public function testContentWithMixedLanguages(): void
    {
        $mixedContent = [
            'title' => 'Programming Tutorial - Tutoriel de Programmation',
            'content' => 'This is English content. Ceci est du contenu français. This continues in English.',
            'url' => 'https://example.com/mixed-lang',
            'tags' => ['tutorial', 'programming']
        ];
        
        $analysis = $this->categorizationService->analyzeContent($mixedContent);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('language', $analysis);
        $this->assertArrayHasKey('language_confidence', $analysis);
    }
    
    public function testTagFrequencyAnalysis(): void
    {
        $tags = ['javascript', 'react', 'vue', 'angular', 'web', 'frontend'];
        $frequency = $this->categorizationService->analyzeTagFrequency($tags);
        
        $this->assertIsArray($frequency);
        $this->assertArrayHasKey('related_tags', $frequency);
        $this->assertArrayHasKey('category_mapping', $frequency);
    }
    
    public function testContentStructureAnalysis(): void
    {
        $structuredContent = [
            'title' => 'Well Structured Article',
            'content' => "# Introduction\n\nThis is the introduction.\n\n## Section 1\n\nFirst section content.\n\n### Subsection\n\nSubsection content.\n\n## Section 2\n\nSecond section content.\n\n## Conclusion\n\nFinal thoughts."
        ];
        
        $unstructuredContent = [
            'title' => 'Poor Structure',
            'content' => 'Lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation'
        ];
        
        $structuredScore = $this->categorizationService->calculateQualityScore($structuredContent);
        $unstructuredScore = $this->categorizationService->calculateQualityScore($unstructuredContent);
        
        $this->assertGreaterThan($unstructuredScore['structure'], $structuredScore['structure']);
    }
}