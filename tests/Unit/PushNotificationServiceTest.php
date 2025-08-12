<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\PushNotificationService;

class PushNotificationServiceTest extends TestCase
{
    private PushNotificationService $pushService;
    
    protected function setUp(): void
    {
        $this->pushService = new PushNotificationService();
    }
    
    public function testGetVapidPublicKey(): void
    {
        $publicKey = $this->pushService->getVapidPublicKey();
        
        $this->assertIsString($publicKey);
        $this->assertNotEmpty($publicKey);
    }
    
    public function testValidateSubscriptionWithValidData(): void
    {
        $validSubscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key'
            ]
        ];
        
        $this->assertTrue($this->pushService->validateSubscription($validSubscription));
    }
    
    public function testValidateSubscriptionWithMissingEndpoint(): void
    {
        $invalidSubscription = [
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key'
            ]
        ];
        
        $this->assertFalse($this->pushService->validateSubscription($invalidSubscription));
    }
    
    public function testValidateSubscriptionWithMissingKeys(): void
    {
        $invalidSubscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123'
        ];
        
        $this->assertFalse($this->pushService->validateSubscription($invalidSubscription));
    }
    
    public function testValidateSubscriptionWithMissingP256dhKey(): void
    {
        $invalidSubscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
            'keys' => [
                'auth' => 'test-auth-key'
            ]
        ];
        
        $this->assertFalse($this->pushService->validateSubscription($invalidSubscription));
    }
    
    public function testValidateSubscriptionWithMissingAuthKey(): void
    {
        $invalidSubscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
            'keys' => [
                'p256dh' => 'test-p256dh-key'
            ]
        ];
        
        $this->assertFalse($this->pushService->validateSubscription($invalidSubscription));
    }
    
    public function testValidateSubscriptionWithInvalidEndpoint(): void
    {
        $invalidSubscription = [
            'endpoint' => 'not-a-valid-url',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key'
            ]
        ];
        
        $this->assertFalse($this->pushService->validateSubscription($invalidSubscription));
    }
    
    public function testCreateStoryNotification(): void
    {
        $story = [
            'id' => 123,
            'title' => 'Test Story',
            'description' => 'This is a test story description that is quite long and should be truncated in the notification body.',
            'slug' => 'test-story'
        ];
        
        $notification = $this->pushService->createStoryNotification($story);
        
        $this->assertIsArray($notification);
        $this->assertEquals('New Story: Test Story', $notification['title']);
        $this->assertStringContains('This is a test story', $notification['body']);
        $this->assertEquals('/s/123/test-story', $notification['url']);
        $this->assertEquals('story-123', $notification['tag']);
        $this->assertArrayHasKey('actions', $notification);
        $this->assertCount(2, $notification['actions']);
    }
    
    public function testCreateCommentNotification(): void
    {
        $comment = [
            'id' => 456,
            'short_id' => 'abc123',
            'comment' => 'This is a test comment that should be truncated if it is too long for the notification body.'
        ];
        
        $story = [
            'id' => 123,
            'title' => 'Test Story',
            'slug' => 'test-story'
        ];
        
        $notification = $this->pushService->createCommentNotification($comment, $story);
        
        $this->assertIsArray($notification);
        $this->assertEquals('New Comment on: Test Story', $notification['title']);
        $this->assertStringContains('This is a test comment', $notification['body']);
        $this->assertEquals('/s/123/test-story#c_abc123', $notification['url']);
        $this->assertEquals('comment-456', $notification['tag']);
        $this->assertArrayHasKey('actions', $notification);
        $this->assertCount(2, $notification['actions']);
    }
    
    public function testCreateMentionNotification(): void
    {
        $comment = [
            'id' => 789,
            'short_id' => 'def456',
            'username' => 'testuser'
        ];
        
        $story = [
            'id' => 123,
            'title' => 'Test Story',
            'slug' => 'test-story'
        ];
        
        $mentionedUser = 'mentioned_user';
        
        $notification = $this->pushService->createMentionNotification($comment, $story, $mentionedUser);
        
        $this->assertIsArray($notification);
        $this->assertEquals('You were mentioned by testuser', $notification['title']);
        $this->assertEquals('In: Test Story', $notification['body']);
        $this->assertEquals('/s/123/test-story#c_def456', $notification['url']);
        $this->assertEquals('mention-789', $notification['tag']);
        $this->assertTrue($notification['requireInteraction']);
        $this->assertEquals($mentionedUser, $notification['data']['mentioned_user']);
    }
    
    public function testCreateSystemNotification(): void
    {
        $title = 'System Notification';
        $message = 'This is a system notification';
        $url = '/admin';
        
        $notification = $this->pushService->createSystemNotification($title, $message, $url);
        
        $this->assertIsArray($notification);
        $this->assertEquals($title, $notification['title']);
        $this->assertEquals($message, $notification['body']);
        $this->assertEquals($url, $notification['url']);
        $this->assertStringStartsWith('system-', $notification['tag']);
        $this->assertEquals('system', $notification['data']['type']);
    }
    
    public function testCreateSystemNotificationWithDefaultUrl(): void
    {
        $title = 'System Notification';
        $message = 'This is a system notification';
        
        $notification = $this->pushService->createSystemNotification($title, $message);
        
        $this->assertEquals('/', $notification['url']);
        $this->assertEquals('/', $notification['data']['url']);
    }
    
    public function testSubscribeUserWithValidData(): void
    {
        $validSubscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key'
            ]
        ];
        
        // This will return true in our mock implementation
        $result = $this->pushService->subscribeUser($validSubscription);
        
        $this->assertTrue($result);
    }
    
    public function testSubscribeUserWithInvalidData(): void
    {
        $invalidSubscription = [
            'endpoint' => 'invalid-url',
            'keys' => [
                'p256dh' => 'test-p256dh-key'
                // Missing auth key
            ]
        ];
        
        $result = $this->pushService->subscribeUser($invalidSubscription);
        
        $this->assertFalse($result);
    }
    
    public function testUnsubscribeUser(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/123';
        
        // This will return true in our mock implementation
        $result = $this->pushService->unsubscribeUser($endpoint);
        
        $this->assertTrue($result);
    }
    
    public function testGetUserSubscriptions(): void
    {
        $userId = 123;
        
        // This returns empty array in our mock implementation
        $subscriptions = $this->pushService->getUserSubscriptions($userId);
        
        $this->assertIsArray($subscriptions);
    }
    
    public function testGetNotificationStats(): void
    {
        $stats = $this->pushService->getNotificationStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_subscriptions', $stats);
        $this->assertArrayHasKey('notifications_sent_today', $stats);
        $this->assertArrayHasKey('notifications_sent_total', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('vapid_public_key', $stats);
        
        $this->assertIsInt($stats['total_subscriptions']);
        $this->assertIsInt($stats['notifications_sent_today']);
        $this->assertIsInt($stats['notifications_sent_total']);
        $this->assertIsFloat($stats['success_rate']);
        $this->assertIsString($stats['vapid_public_key']);
    }
}