<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\TagService;

class TagServiceTest extends TestCase
{
    private TagService $tagService;

    protected function setUp(): void
    {
        $this->tagService = new TagService();
    }

    public function testNormalizeTagName(): void
    {
        $tag1 = "  Programming  ";
        $normalized1 = $this->tagService->normalizeTagName($tag1);
        $this->assertEquals("programming", $normalized1);

        $tag2 = "Web-Development";
        $normalized2 = $this->tagService->normalizeTagName($tag2);
        $this->assertEquals("web-development", $normalized2);

        $tag3 = "C++";
        $normalized3 = $this->tagService->normalizeTagName($tag3);
        $this->assertEquals("c", $normalized3); // Special chars removed except hyphens
    }

    public function testParseTagsFromString(): void
    {
        $tagString = "programming, web-development, javascript,   python   ";
        $tags = $this->tagService->parseTagsFromString($tagString);

        $expected = ["programming", "web-development", "javascript", "python"];
        $this->assertEquals($expected, $tags);
    }

    public function testParseTagsFromStringWithLimit(): void
    {
        $tagString = "tag1, tag2, tag3, tag4, tag5, tag6, tag7";
        $tags = $this->tagService->parseTagsFromString($tagString);

        $this->assertCount(5, $tags); // Service limits to 5
        $this->assertEquals(["tag1", "tag2", "tag3", "tag4", "tag5"], $tags);
    }

    public function testNormalizeTagValidation(): void
    {
        // Test various tag normalizations
        $this->assertEquals("programming", $this->tagService->normalizeTagName("Programming"));
        $this->assertEquals("web-dev", $this->tagService->normalizeTagName("web-dev"));
        $this->assertEquals("", $this->tagService->normalizeTagName("")); // Empty after normalization
        $this->assertEquals("ab", $this->tagService->normalizeTagName("ab"));
        $this->assertEquals(str_repeat("a", 25), $this->tagService->normalizeTagName(str_repeat("a", 30))); // Truncated to 25
    }

    public function testGetSuggestedTags(): void
    {
        $title = "Building a React Application with JavaScript";
        $description = "This tutorial covers modern JavaScript development";
        $url = "https://developer.mozilla.org/docs/javascript";

        $suggestions = $this->tagService->getSuggestedTags($title, $description, $url);

        $this->assertIsArray($suggestions);
        // Should suggest tags based on keywords
        $this->assertContains("javascript", $suggestions);

        // Test web keyword
        $webTitle = "Web Development Best Practices";
        $webSuggestions = $this->tagService->getSuggestedTags($webTitle, "", "");
        $this->assertContains("web", $webSuggestions);
    }
}
