<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Controllers\UserController;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class UserServiceTest extends TestCase
{
    private $capsule;
    private $userService;
    private $userController;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up in-memory SQLite database
        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        
        // Create tables
        $this->createTables();
        
        // Initialize services
        $this->userService = new UserService();
        
        // Mock the templates engine for UserController
        $mockTemplates = $this->createMock(\League\Plates\Engine::class);
        $mockFeedService = $this->createMock(\App\Services\FeedService::class);
        $mockTagService = $this->createMock(\App\Services\TagService::class);
        
        $this->userController = new UserController(
            $mockTemplates,
            $mockFeedService,
            $this->userService,
            $mockTagService
        );
    }

    private function createTables(): void
    {
        // Create users table
        Capsule::schema()->create('users', function ($table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password_digest')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_moderator')->default(false);
            $table->integer('karma')->default(0);
            $table->text('about')->nullable();
            $table->string('homepage')->nullable();
            $table->string('github_username')->nullable();
            $table->string('twitter_username')->nullable();
            $table->string('mastodon_username')->nullable();
            $table->string('linkedin_username')->nullable();
            $table->string('bluesky_username')->nullable();
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('pushover_notifications')->default(false);
            $table->string('pushover_user_key')->nullable();
            $table->string('pushover_sound')->nullable();
            $table->boolean('mailing_list_mode')->default(false);
            $table->boolean('show_avatars')->default(true);
            $table->boolean('show_story_previews')->default(true);
            $table->boolean('show_submit_tagging_hints')->default(true);
            $table->boolean('show_read_ribbons')->default(true);
            $table->boolean('hide_dragons')->default(false);
            $table->boolean('post_reply_notifications')->default(true);
            $table->string('theme')->default('default');
            $table->string('session_token')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('banned_reason')->nullable();
            $table->boolean('deleted')->default(false);
            $table->boolean('disabled_invites')->default(false);
            $table->json('filtered_tags')->nullable();
            $table->json('favorite_tags')->nullable();
            $table->timestamps();
            
            $table->foreign('invited_by_user_id')->references('id')->on('users');
        });

        // Create user_profiles table
        Capsule::schema()->create('user_profiles', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('website')->nullable();
            $table->string('twitter_handle')->nullable();
            $table->string('github_handle')->nullable();
            $table->string('linkedin_handle')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->json('expertise_tags')->nullable();
            $table->json('interests')->nullable();
            $table->string('timezone')->nullable();
            $table->string('preferred_language')->default('en');
            $table->enum('profile_visibility', ['public', 'members', 'private'])->default('public');
            $table->boolean('show_email')->default(false);
            $table->boolean('show_real_name')->default(false);
            $table->boolean('show_location')->default(true);
            $table->boolean('show_social_links')->default(true);
            $table->enum('allow_messages_from', ['anyone', 'members', 'followed_users', 'none'])->default('members');
            $table->boolean('email_on_mention')->default(true);
            $table->boolean('email_on_reply')->default(true);
            $table->boolean('email_on_follow')->default(false);
            $table->boolean('push_on_mention')->default(true);
            $table->boolean('push_on_reply')->default(true);
            $table->boolean('push_on_follow')->default(true);
            $table->integer('profile_views')->default(0);
            $table->decimal('reputation_score', 8, 2)->default(0.00);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function testUsernameChangeValidation(): void
    {
        // Create a test user
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'karma' => 10
        ]);

        // Test valid username change
        $result = $this->userService->updateUserSettings($user, ['username' => 'newusername']);
        $this->assertTrue($result);
        $user->refresh();
        $this->assertEquals('newusername', $user->username);

        // Test invalid username format (spaces)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username can only contain letters, numbers, underscore, and dash.');
        $this->userService->updateUserSettings($user, ['username' => 'invalid name']);
    }

    public function testUsernameChangeUniqueness(): void
    {
        // Create two test users
        $user1 = User::create([
            'username' => 'user1',
            'email' => 'user1@example.com',
            'karma' => 10
        ]);

        $user2 = User::create([
            'username' => 'user2',
            'email' => 'user2@example.com',
            'karma' => 15
        ]);

        // Try to change user2's username to user1's username (should fail)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username is already in use.');
        $this->userService->updateUserSettings($user2, ['username' => 'user1']);
    }

    public function testUsernameChangeLengthValidation(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'karma' => 10
        ]);

        // Test username too short
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username must be between 2 and 50 characters.');
        $this->userService->updateUserSettings($user, ['username' => 'a']);
    }

    public function testUsersTreeIntegrityAfterUsernameChange(): void
    {
        // Create a tree of users: root -> child1 -> grandchild
        $root = User::create([
            'username' => 'root',
            'email' => 'root@example.com',
            'karma' => 100,
            'invited_by_user_id' => null
        ]);

        $child1 = User::create([
            'username' => 'child1',
            'email' => 'child1@example.com',
            'karma' => 50,
            'invited_by_user_id' => $root->id
        ]);

        $grandchild = User::create([
            'username' => 'grandchild',
            'email' => 'grandchild@example.com',
            'karma' => 25,
            'invited_by_user_id' => $child1->id
        ]);

        // Get initial tree structure
        $initialTree = $this->getUserTreeFromController();
        
        // Verify initial tree structure is correct
        $this->assertNotEmpty($initialTree);
        $this->assertArrayHasKey(0, $initialTree); // Root should be at index 0
        $this->assertEquals('root', $initialTree[0]['username']);
        $this->assertNotEmpty($initialTree[0]['children']);
        $this->assertEquals('child1', $initialTree[0]['children'][0]['username']);
        $this->assertNotEmpty($initialTree[0]['children'][0]['children']);
        $this->assertEquals('grandchild', $initialTree[0]['children'][0]['children'][0]['username']);

        // Change username of middle user
        $result = $this->userService->updateUserSettings($child1, ['username' => 'renamed_child']);
        $this->assertTrue($result);
        
        // Verify the username was actually changed
        $child1->refresh();
        $this->assertEquals('renamed_child', $child1->username);

        // Get tree structure after username change
        $newTree = $this->getUserTreeFromController();
        
        // Verify tree structure is still intact
        $this->assertNotEmpty($newTree);
        $this->assertArrayHasKey(0, $newTree); // Root should still be at index 0
        $this->assertEquals('root', $newTree[0]['username']);
        $this->assertNotEmpty($newTree[0]['children']);
        
        // The renamed user should now appear with new username
        $this->assertEquals('renamed_child', $newTree[0]['children'][0]['username']);
        $this->assertNotEmpty($newTree[0]['children'][0]['children']);
        $this->assertEquals('grandchild', $newTree[0]['children'][0]['children'][0]['username']);

        // Verify the invitation relationships are preserved (IDs should be the same)
        $this->assertEquals($child1->id, $newTree[0]['children'][0]['id']);
        $this->assertEquals($child1->invited_by_user_id, $newTree[0]['children'][0]['invited_by_user_id']);
        $this->assertEquals($grandchild->invited_by_user_id, $child1->id); // grandchild should still point to child1's ID
    }

    public function testUsersTreeKarmaSortingAfterUsernameChange(): void
    {
        // Create users with different karma values
        $root = User::create([
            'username' => 'root',
            'email' => 'root@example.com',
            'karma' => 100,
            'invited_by_user_id' => null
        ]);

        $child1 = User::create([
            'username' => 'child1',
            'email' => 'child1@example.com',
            'karma' => 75, // Higher karma
            'invited_by_user_id' => $root->id
        ]);

        $child2 = User::create([
            'username' => 'child2',
            'email' => 'child2@example.com',
            'karma' => 25, // Lower karma
            'invited_by_user_id' => $root->id
        ]);

        // Get initial tree (should be sorted by karma)
        $initialTree = $this->getUserTreeFromController();
        
        // Verify initial karma sorting
        $this->assertEquals('child1', $initialTree[0]['children'][0]['username']); // Higher karma first
        $this->assertEquals('child2', $initialTree[0]['children'][1]['username']); // Lower karma second

        // Change username of higher karma child
        $result = $this->userService->updateUserSettings($child1, ['username' => 'zzz_renamed']);
        $this->assertTrue($result);

        // Get tree after username change
        $newTree = $this->getUserTreeFromController();
        
        // Verify karma sorting is still preserved (renamed user should still be first due to higher karma)
        $this->assertEquals('zzz_renamed', $newTree[0]['children'][0]['username']); // Should still be first despite alphabetically last name
        $this->assertEquals('child2', $newTree[0]['children'][1]['username']); // Should still be second
        $this->assertEquals(75, $newTree[0]['children'][0]['karma']); // Karma should be preserved
        $this->assertEquals(25, $newTree[0]['children'][1]['karma']); // Karma should be preserved
    }

    private function getUserTreeFromController(): array
    {
        // Use reflection to call the private buildUserTree method
        $reflection = new \ReflectionClass($this->userController);
        $method = $reflection->getMethod('buildUserTree');
        $method->setAccessible(true);
        
        return $method->invoke($this->userController);
    }

    protected function tearDown(): void
    {
        // Clean up
        Capsule::schema()->dropIfExists('user_profiles');
        Capsule::schema()->dropIfExists('users');
        parent::tearDown();
    }
}