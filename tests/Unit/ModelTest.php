<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Story;
use App\Models\Comment;

class ModelTest extends TestCase
{
    public function testUserModelCanBeInstantiated(): void
    {
        $user = new User();
        $this->assertInstanceOf(User::class, $user);
    }

    public function testStoryModelCanBeInstantiated(): void
    {
        $story = new Story();
        $this->assertInstanceOf(Story::class, $story);
    }

    public function testCommentModelCanBeInstantiated(): void
    {
        $comment = new Comment();
        $this->assertInstanceOf(Comment::class, $comment);
    }

    public function testUserModelHasExpectedFillableFields(): void
    {
        $user = new User();
        $expected = [
            'username',
            'email',
            'password_hash',
            'created_at',
            'is_admin',
            'is_moderator',
            'karma',
            'about',
            'email_notifications',
            'pushover_notifications',
            'pushover_user_key',
            'pushover_sound',
            'mailing_list_mode',
            'show_avatars',
            'show_story_previews',
            'show_submit_tagging_hints',
            'show_read_ribbons',
            'hide_dragons',
            'post_reply_notifications',
            'theme',
            'session_token',
            'password_reset_token',
            'banned_at',
            'banned_reason',
            'deleted',
            'disabled_invites'
        ];

        $this->assertEquals($expected, $user->getFillable());
    }
}
