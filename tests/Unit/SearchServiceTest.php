<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\SearchService;

class SearchServiceTest extends TestCase
{
    private SearchService $searchService;

    protected function setUp(): void
    {
        $this->searchService = new SearchService();
    }

    public function testSearchWithEmptyQuery(): void
    {
        $result = $this->searchService->search('');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals('all', $result['type']);
    }

    public function testSearchReturnsCorrectStructure(): void
    {
        $result = $this->searchService->search('test', 'stories', 'newest', 1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('query', $result);
        
        $this->assertEquals('stories', $result['type']);
        $this->assertEquals('test', $result['query']);
        $this->assertEquals(1, $result['page']);
    }

    public function testSearchTagsReturnsArray(): void
    {
        $tags = $this->searchService->searchTags('ph');
        
        $this->assertIsArray($tags);
    }

    public function testSearchTagsWithShortQuery(): void
    {
        $tags = $this->searchService->searchTags('a');
        
        $this->assertEmpty($tags);
    }

    public function testGetPopularSearches(): void
    {
        $popular = $this->searchService->getPopularSearches();
        
        $this->assertIsArray($popular);
        $this->assertNotEmpty($popular);
        $this->assertContains('php', $popular);
    }

    public function testValidSearchTypes(): void
    {
        $validTypes = ['all', 'stories', 'comments'];
        
        foreach ($validTypes as $type) {
            $result = $this->searchService->search('test', $type);
            $this->assertEquals($type, $result['type']);
        }
    }

    public function testValidSearchOrders(): void
    {
        $validOrders = ['newest', 'relevance', 'score'];
        
        foreach ($validOrders as $order) {
            $result = $this->searchService->search('test', 'all', $order);
            // Test passes if no exception is thrown
            $this->assertIsArray($result);
        }
    }
}