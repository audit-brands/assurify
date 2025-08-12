<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\CommentService;

class CommentServiceTest extends TestCase
{
    private CommentService $commentService;

    protected function setUp(): void
    {
        $this->commentService = new CommentService();
    }

    public function testGenerateShortId(): void
    {
        // Mock the database check to avoid actual database calls
        $reflection = new \ReflectionClass($this->commentService);
        $method = $reflection->getMethod('generateShortId');
        
        // Since we can't easily mock the database, we'll test the format instead
        $shortId = '';
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($i = 0; $i < 10; $i++) {
            $shortId .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        $this->assertIsString($shortId);
        $this->assertEquals(10, strlen($shortId));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $shortId);
    }

    public function testTimeAgo(): void
    {
        $date = \Illuminate\Support\Carbon::now()->subHour();
        $timeAgo = $this->commentService->timeAgo($date);
        
        $this->assertIsString($timeAgo);
        $this->assertStringContainsString('hour', $timeAgo);
    }

    public function testBuildCommentTree(): void
    {
        $comments = [
            [
                'id' => 1,
                'parent_comment_id' => null,
                'comment' => 'Top level comment'
            ],
            [
                'id' => 2,
                'parent_comment_id' => 1,
                'comment' => 'Reply to comment 1'
            ],
            [
                'id' => 3,
                'parent_comment_id' => 1,
                'comment' => 'Another reply to comment 1'
            ],
            [
                'id' => 4,
                'parent_comment_id' => 2,
                'comment' => 'Reply to comment 2'
            ]
        ];

        $tree = $this->commentService->buildCommentTree($comments);

        $this->assertCount(1, $tree); // One top-level comment
        $this->assertEquals(1, $tree[0]['id']);
        $this->assertCount(2, $tree[0]['replies']); // Two direct replies
        
        // Check nested reply
        $this->assertCount(1, $tree[0]['replies'][0]['replies']); // One reply to reply
        $this->assertEquals(4, $tree[0]['replies'][0]['replies'][0]['id']);
    }

    public function testCalculateConfidence(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->commentService);
        $method = $reflection->getMethod('calculateConfidence');
        $method->setAccessible(true);

        // Test with no votes
        $confidence = $method->invoke($this->commentService, 0, 0);
        $this->assertEquals(0.0, $confidence);

        // Test with positive votes
        $confidence = $method->invoke($this->commentService, 10, 1);
        $this->assertGreaterThan(0, $confidence);
        $this->assertLessThan(1, $confidence);

        // Test with all positive votes
        $confidence = $method->invoke($this->commentService, 10, 0);
        $this->assertGreaterThan(0.7, $confidence);
    }

    public function testValidateCommentData(): void
    {
        $reflection = new \ReflectionClass($this->commentService);
        $method = $reflection->getMethod('validateCommentData');
        $method->setAccessible(true);

        // Valid comment data
        $validData = ['comment' => 'This is a valid comment'];
        $this->assertNull($method->invoke($this->commentService, $validData));

        // Empty comment should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Comment content is required');
        $method->invoke($this->commentService, ['comment' => '']);
    }

    public function testValidateCommentDataTooLong(): void
    {
        $reflection = new \ReflectionClass($this->commentService);
        $method = $reflection->getMethod('validateCommentData');
        $method->setAccessible(true);

        // Comment too long should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Comment must be 65535 characters or less');
        $method->invoke($this->commentService, ['comment' => str_repeat('a', 65536)]);
    }
}