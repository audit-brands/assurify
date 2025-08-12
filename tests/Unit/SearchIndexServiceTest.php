<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\SearchIndexService;
use App\Services\CacheService;

class SearchIndexServiceTest extends TestCase
{
    private SearchIndexService $searchService;
    private CacheService $cacheService;
    
    protected function setUp(): void
    {
        $this->cacheService = new CacheService();
        $this->searchService = new SearchIndexService($this->cacheService);
    }
    
    public function testIndexContent(): void
    {
        $content = [
            'id' => 123,
            'title' => 'Advanced JavaScript Techniques',
            'content' => 'This article covers modern JavaScript features including async/await, closures, and ES6 modules.',
            'url' => 'https://example.com/js-techniques',
            'tags' => ['javascript', 'programming', 'web'],
            'created_at' => '2024-01-15 10:30:00'
        ];
        
        $result = $this->searchService->indexContentArray($content);
        
        $this->assertTrue($result);
    }
    
    public function testSearch(): void
    {
        $query = 'javascript async await';
        $options = [
            'limit' => 10,
            'offset' => 0,
            'filters' => ['tags' => ['javascript']],
            'sort' => 'relevance'
        ];
        
        $results = $this->searchService->search($query, $options);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('total', $results);
        $this->assertArrayHasKey('query', $results);
        $this->assertArrayHasKey('took', $results);
        
        $this->assertIsArray($results['results']);
        $this->assertIsInt($results['total']);
        $this->assertIsFloat($results['took']);
        $this->assertEquals($query, $results['query']);
    }
    
    public function testAdvancedSearch(): void
    {
        $searchParams = [
            'query' => 'machine learning',
            'title_boost' => 2.0,
            'content_boost' => 1.0,
            'tag_boost' => 1.5,
            'date_range' => [
                'start' => '2024-01-01',
                'end' => '2024-12-31'
            ],
            'categories' => ['ai', 'programming'],
            'exclude_tags' => ['beginner'],
            'min_score' => 0.5
        ];
        
        $results = $this->searchService->advancedSearch($searchParams);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('total', $results);
        $this->assertArrayHasKey('facets', $results);
        $this->assertArrayHasKey('suggestions', $results);
    }
    
    public function testGetSuggestions(): void
    {
        $query = 'javascrpt'; // Intentional typo
        $limit = 5;
        
        $suggestions = $this->searchService->getSuggestions($query, $limit);
        
        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual($limit, count($suggestions));
        
        // Should suggest correct spelling
        $suggestionTexts = array_column($suggestions, 'text');
        $this->assertContains('javascript', $suggestionTexts);
    }
    
    public function testGetRelatedTags(): void
    {
        $tags = ['javascript', 'programming'];
        $limit = 5;
        
        $relatedTags = $this->searchService->getRelatedTags($tags, $limit);
        
        $this->assertIsArray($relatedTags);
        $this->assertLessThanOrEqual($limit, count($relatedTags));
        
        foreach ($relatedTags as $tag) {
            $this->assertIsArray($tag);
            $this->assertArrayHasKey('tag', $tag);
            $this->assertArrayHasKey('score', $tag);
            $this->assertArrayHasKey('frequency', $tag);
        }
    }
    
    public function testCalculateRelevanceScore(): void
    {
        $searchTerms = ['machine', 'learning', 'algorithms'];
        $content = [
            'title' => 'Advanced Machine Learning Algorithms',
            'content' => 'This comprehensive guide covers various machine learning algorithms including neural networks, decision trees, and support vector machines.',
            'tags' => ['ml', 'algorithms', 'ai']
        ];
        $engagementData = [
            'score' => 10,
            'comments_count' => 5,
            'user_karma' => 100
        ];
        
        $score = $this->searchService->calculateRelevanceScore($searchTerms, $content, $engagementData);
        
        $this->assertIsFloat($score);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
    
    public function testRemoveFromIndex(): void
    {
        $contentId = 123;
        
        $result = $this->searchService->removeContentFromIndex($contentId);
        
        $this->assertTrue($result);
    }
    
    public function testBulkIndex(): void
    {
        $contents = [
            [
                'id' => 124,
                'title' => 'React Hooks Tutorial',
                'content' => 'Learn React hooks including useState, useEffect, and custom hooks.',
                'tags' => ['react', 'javascript', 'frontend']
            ],
            [
                'id' => 125,
                'title' => 'Vue.js Composition API',
                'content' => 'Explore Vue 3 composition API for better code organization.',
                'tags' => ['vue', 'javascript', 'frontend']
            ]
        ];
        
        $result = $this->searchService->bulkIndexArray($contents);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('indexed', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('total', $result);
        
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['indexed']);
        $this->assertEquals(0, $result['failed']);
    }
    
    public function testSearchFacets(): void
    {
        $query = 'programming';
        
        $facets = $this->searchService->getFacets($query);
        
        $this->assertIsArray($facets);
        $this->assertArrayHasKey('tags', $facets);
        $this->assertArrayHasKey('categories', $facets);
        $this->assertArrayHasKey('authors', $facets);
        $this->assertArrayHasKey('date_ranges', $facets);
        
        foreach ($facets['tags'] as $tag) {
            $this->assertArrayHasKey('value', $tag);
            $this->assertArrayHasKey('count', $tag);
        }
    }
    
    public function testAutoComplete(): void
    {
        $prefix = 'java';
        $limit = 10;
        
        $completions = $this->searchService->autoComplete($prefix, $limit);
        
        $this->assertIsArray($completions);
        $this->assertLessThanOrEqual($limit, count($completions));
        
        foreach ($completions as $completion) {
            $this->assertIsArray($completion);
            $this->assertArrayHasKey('text', $completion);
            $this->assertArrayHasKey('score', $completion);
            $this->assertStringStartsWith($prefix, strtolower($completion['text']));
        }
    }
    
    public function testSearchAnalytics(): void
    {
        $query = 'machine learning';
        
        // Record some search queries
        $this->searchService->recordSearchQuery($query, 10, 0.5);
        $this->searchService->recordSearchQuery('deep learning', 5, 0.3);
        $this->searchService->recordSearchQuery($query, 8, 0.7);
        
        $analytics = $this->searchService->getSearchAnalytics(['timeframe' => '7d']);
        
        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_searches', $analytics);
        $this->assertArrayHasKey('unique_queries', $analytics);
        $this->assertArrayHasKey('top_queries', $analytics);
        $this->assertArrayHasKey('zero_result_queries', $analytics);
        $this->assertArrayHasKey('average_results', $analytics);
        $this->assertArrayHasKey('average_relevance', $analytics);
    }
    
    public function testSearchWithFilters(): void
    {
        $query = 'web development';
        $filters = [
            'tags' => ['javascript', 'html', 'css'],
            'date_range' => [
                'start' => '2024-01-01',
                'end' => '2024-12-31'
            ],
            'categories' => ['tutorial', 'guide'],
            'exclude_authors' => ['spam_user'],
            'min_score' => 0.5
        ];
        
        $results = $this->searchService->searchWithFilters($query, $filters);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('applied_filters', $results);
        
        $this->assertIsArray($results['applied_filters']);
        $this->assertEquals($filters, $results['applied_filters']);
    }
    
    public function testEmptyQueryHandling(): void
    {
        $results = $this->searchService->search('');
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEmpty($results['results']);
        $this->assertEquals(0, $results['total']);
    }
    
    public function testSpecialCharacterHandling(): void
    {
        $queries = [
            'C++',
            '.NET Framework',
            'React.js',
            '@angular/core',
            'search-term',
            'term_with_underscore'
        ];
        
        foreach ($queries as $query) {
            $results = $this->searchService->search($query);
            
            $this->assertIsArray($results);
            $this->assertArrayHasKey('results', $results);
            $this->assertArrayHasKey('total', $results);
        }
    }
    
    public function testSearchPerformance(): void
    {
        $query = 'performance test query with multiple terms';
        
        $startTime = microtime(true);
        $results = $this->searchService->search($query, ['limit' => 50]);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        $this->assertLessThan(2.0, $executionTime); // Should complete within 2 seconds
        $this->assertIsArray($results);
        $this->assertArrayHasKey('took', $results);
        $this->assertIsFloat($results['took']);
    }
    
    public function testIndexUpdates(): void
    {
        $originalContent = [
            'id' => 126,
            'title' => 'Original Title',
            'content' => 'Original content about programming.',
            'tags' => ['programming']
        ];
        
        $updatedContent = [
            'id' => 126,
            'title' => 'Updated Title',
            'content' => 'Updated content about advanced programming techniques.',
            'tags' => ['programming', 'advanced']
        ];
        
        // Index original content
        $result1 = $this->searchService->indexContentArray($originalContent);
        $this->assertTrue($result1);
        
        // Update with new content
        $result2 = $this->searchService->updateContent($updatedContent);
        $this->assertTrue($result2);
        
        // Search should return updated content
        $searchResults = $this->searchService->search('advanced programming');
        $this->assertIsArray($searchResults);
    }
}