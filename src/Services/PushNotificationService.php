<?php

declare(strict_types=1);

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\MessageSentReport;

class PushNotificationService
{
    private WebPush $webPush;
    private array $vapidKeys;
    private string $subject;
    
    public function __construct()
    {
        $this->vapidKeys = [
            'publicKey' => $_ENV['VAPID_PUBLIC_KEY'] ?? 'BEl62iUYgUivxIkv69yViEuiBIa40HI0DLdgC3qgW3Dv3Tr-N',
            'privateKey' => $_ENV['VAPID_PRIVATE_KEY'] ?? 'UUxI4O8-FbRouAevSmBQ6o-o_1_qMaPN8rj4w6z5r_w'
        ];
        
        $this->subject = $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@lobste.rs';
        
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->vapidKeys['publicKey'],
                'privateKey' => $this->vapidKeys['privateKey']
            ]
        ]);
    }
    
    public function sendNotification(array $subscription, array $payload): bool
    {
        try {
            $subscriptionObject = Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'publicKey' => $subscription['keys']['p256dh'] ?? null,
                'authToken' => $subscription['keys']['auth'] ?? null,
                'contentEncoding' => $subscription['contentEncoding'] ?? 'aes128gcm'
            ]);
            
            $result = $this->webPush->sendOneNotification(
                $subscriptionObject,
                json_encode($payload)
            );
            
            return $result->isSuccess();
            
        } catch (\Exception $e) {
            error_log('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function sendBulkNotifications(array $subscriptions, array $payload): array
    {
        $results = [];
        
        foreach ($subscriptions as $subscription) {
            try {
                $subscriptionObject = Subscription::create([
                    'endpoint' => $subscription['endpoint'],
                    'publicKey' => $subscription['keys']['p256dh'] ?? null,
                    'authToken' => $subscription['keys']['auth'] ?? null,
                    'contentEncoding' => $subscription['contentEncoding'] ?? 'aes128gcm'
                ]);
                
                $this->webPush->queueNotification(
                    $subscriptionObject,
                    json_encode($payload)
                );
                
            } catch (\Exception $e) {
                $results[] = [
                    'subscription' => $subscription,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Send all queued notifications
        foreach ($this->webPush->flush() as $report) {
            $results[] = [
                'subscription' => $report->getRequest()->getUri(),
                'success' => $report->isSuccess(),
                'error' => $report->isSuccess() ? null : $report->getReason()
            ];
        }
        
        return $results;
    }
    
    public function createStoryNotification(array $story): array
    {
        return [
            'title' => 'New Story: ' . $story['title'],
            'body' => $story['description'] ? substr($story['description'], 0, 100) . '...' : 'Check out this new story',
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-72x72.png',
            'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? ''),
            'tag' => 'story-' . $story['id'],
            'requireInteraction' => false,
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'View Story',
                    'icon' => '/assets/icons/view-icon.png'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Dismiss',
                    'icon' => '/assets/icons/dismiss-icon.png'
                ]
            ],
            'data' => [
                'story_id' => $story['id'],
                'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? ''),
                'type' => 'story'
            ]
        ];
    }
    
    public function createCommentNotification(array $comment, array $story): array
    {
        return [
            'title' => 'New Comment on: ' . $story['title'],
            'body' => substr($comment['comment'], 0, 100) . '...',
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-72x72.png',
            'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? '') . '#c_' . $comment['short_id'],
            'tag' => 'comment-' . $comment['id'],
            'requireInteraction' => false,
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'View Comment',
                    'icon' => '/assets/icons/view-icon.png'
                ],
                [
                    'action' => 'reply',
                    'title' => 'Reply',
                    'icon' => '/assets/icons/reply-icon.png'
                ]
            ],
            'data' => [
                'comment_id' => $comment['id'],
                'story_id' => $story['id'],
                'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? '') . '#c_' . $comment['short_id'],
                'type' => 'comment'
            ]
        ];
    }
    
    public function createMentionNotification(array $comment, array $story, string $mentionedUser): array
    {
        return [
            'title' => 'You were mentioned by ' . $comment['username'],
            'body' => 'In: ' . $story['title'],
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-72x72.png',
            'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? '') . '#c_' . $comment['short_id'],
            'tag' => 'mention-' . $comment['id'],
            'requireInteraction' => true,
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'View Mention',
                    'icon' => '/assets/icons/view-icon.png'
                ],
                [
                    'action' => 'reply',
                    'title' => 'Reply',
                    'icon' => '/assets/icons/reply-icon.png'
                ]
            ],
            'data' => [
                'comment_id' => $comment['id'],
                'story_id' => $story['id'],
                'mentioned_user' => $mentionedUser,
                'url' => '/s/' . $story['id'] . '/' . ($story['slug'] ?? '') . '#c_' . $comment['short_id'],
                'type' => 'mention'
            ]
        ];
    }
    
    public function createSystemNotification(string $title, string $message, string $url = '/'): array
    {
        return [
            'title' => $title,
            'body' => $message,
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-72x72.png',
            'url' => $url,
            'tag' => 'system-' . time(),
            'requireInteraction' => false,
            'data' => [
                'url' => $url,
                'type' => 'system'
            ]
        ];
    }
    
    public function subscribeUser(array $subscriptionData): bool
    {
        try {
            // In a real application, you would store this in the database
            // For now, we'll just validate the subscription format
            
            $required = ['endpoint', 'keys'];
            foreach ($required as $field) {
                if (!isset($subscriptionData[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }
            
            if (!isset($subscriptionData['keys']['p256dh']) || !isset($subscriptionData['keys']['auth'])) {
                throw new \InvalidArgumentException("Missing required keys");
            }
            
            // Store subscription in database (implement based on your database structure)
            return $this->storeSubscription($subscriptionData);
            
        } catch (\Exception $e) {
            error_log('Subscription failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function unsubscribeUser(string $endpoint): bool
    {
        try {
            // Remove subscription from database
            return $this->removeSubscription($endpoint);
            
        } catch (\Exception $e) {
            error_log('Unsubscribe failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getUserSubscriptions(int $userId): array
    {
        try {
            // Get user subscriptions from database
            return $this->getStoredSubscriptions($userId);
            
        } catch (\Exception $e) {
            error_log('Get subscriptions failed: ' . $e->getMessage());
            return [];
        }
    }
    
    public function sendToUser(int $userId, array $payload): bool
    {
        try {
            $subscriptions = $this->getUserSubscriptions($userId);
            
            if (empty($subscriptions)) {
                return false;
            }
            
            $results = $this->sendBulkNotifications($subscriptions, $payload);
            
            // Return true if at least one notification was sent successfully
            return count(array_filter($results, fn($r) => $r['success'])) > 0;
            
        } catch (\Exception $e) {
            error_log('Send to user failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getVapidPublicKey(): string
    {
        return $this->vapidKeys['publicKey'];
    }
    
    private function storeSubscription(array $subscription): bool
    {
        // In a real implementation, store in database
        // For now, just return true to indicate success
        
        // Example SQL:
        // INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, created_at)
        // VALUES (?, ?, ?, ?, NOW())
        
        return true;
    }
    
    private function removeSubscription(string $endpoint): bool
    {
        // In a real implementation, remove from database
        // For now, just return true to indicate success
        
        // Example SQL:
        // DELETE FROM push_subscriptions WHERE endpoint = ?
        
        return true;
    }
    
    private function getStoredSubscriptions(int $userId): array
    {
        // In a real implementation, fetch from database
        // For now, return empty array
        
        // Example SQL:
        // SELECT endpoint, p256dh_key, auth_key FROM push_subscriptions WHERE user_id = ?
        
        return [];
    }
    
    public function validateSubscription(array $subscription): bool
    {
        if (!isset($subscription['endpoint']) || empty($subscription['endpoint'])) {
            return false;
        }
        
        if (!isset($subscription['keys']['p256dh']) || empty($subscription['keys']['p256dh'])) {
            return false;
        }
        
        if (!isset($subscription['keys']['auth']) || empty($subscription['keys']['auth'])) {
            return false;
        }
        
        // Validate endpoint URL
        if (!filter_var($subscription['endpoint'], FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return true;
    }
    
    public function getNotificationStats(): array
    {
        return [
            'total_subscriptions' => 0, // Get from database
            'notifications_sent_today' => 0, // Get from database
            'notifications_sent_total' => 0, // Get from database
            'success_rate' => 0.0, // Calculate from database
            'vapid_public_key' => $this->getVapidPublicKey()
        ];
    }
}