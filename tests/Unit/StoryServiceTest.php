<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\StoryService;
use App\Services\TagService;
use App\Models\Story;
use App\Models\User;
use Mockery;

class StoryServiceTest extends TestCase
{
    private StoryService $storyService;
    private TagService $tagService;

    protected function setUp(): void
    {
        $this->tagService = Mockery::mock(TagService::class);
        $this->storyService = new StoryService($this->tagService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGenerateSlug(): void
    {
        $title = "This is a Test Title with Special Characters!";
        $slug = $this->storyService->generateSlug($title);

        $this->assertEquals("this_is_a_test_title_with_special_characters", $slug);
    }

    public function testNormalizeUrl(): void
    {
        // Test HTTP to HTTPS conversion
        $url1 = "http://example.com/path";
        $normalized1 = $this->storyService->normalizeUrl($url1);
        $this->assertEquals("https://example.com/path", $normalized1);

        // Test www removal
        $url2 = "https://www.example.com/path";
        $normalized2 = $this->storyService->normalizeUrl($url2);
        $this->assertEquals("https://example.com/path", $normalized2);

        // Test trailing slash removal
        $url3 = "https://example.com/path/";
        $normalized3 = $this->storyService->normalizeUrl($url3);
        $this->assertEquals("https://example.com/path", $normalized3);

        // Test tracking parameter removal
        $url4 = "https://example.com/path?utm_source=test&fbclid=123";
        $normalized4 = $this->storyService->normalizeUrl($url4);
        $this->assertEquals("https://example.com/path", $normalized4);
    }

    public function testExtractDomain(): void
    {
        $url1 = "https://example.com/path/to/page";
        $domain1 = $this->storyService->extractDomain($url1);
        $this->assertEquals("example.com", $domain1);

        $url2 = "https://subdomain.example.org/path";
        $domain2 = $this->storyService->extractDomain($url2);
        $this->assertEquals("subdomain.example.org", $domain2);
    }

    public function testSlugLengthLimit(): void
    {
        $longTitle = str_repeat("Test Title ", 20);
        $slug = $this->storyService->generateSlug($longTitle);

        $this->assertLessThanOrEqual(50, strlen($slug));
    }
}
