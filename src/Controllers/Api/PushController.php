<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\PushNotificationService;

class PushController extends BaseApiController
{
    private PushNotificationService $pushService;
    
    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }
    
    public function subscribe(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validate subscription data
            if (!$this->pushService->validateSubscription($data)) {
                return $this->errorResponse(
                    $response,
                    'Invalid subscription data',
                    400,
                    [],
                    'INVALID_SUBSCRIPTION'
                );
            }
            
            // Subscribe user
            $success = $this->pushService->subscribeUser($data);
            
            if (!$success) {
                return $this->errorResponse(
                    $response,
                    'Failed to subscribe to push notifications',
                    500,
                    [],
                    'SUBSCRIPTION_FAILED'
                );
            }
            
            return $this->successResponse(
                $response,
                ['subscribed' => true],
                'Successfully subscribed to push notifications',
                201
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Subscription error',
                500,
                [],
                'SUBSCRIPTION_ERROR'
            );
        }
    }
    
    public function unsubscribe(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (empty($data['endpoint'])) {
                return $this->errorResponse(
                    $response,
                    'Endpoint is required',
                    400,
                    [],
                    'MISSING_ENDPOINT'
                );
            }
            
            $success = $this->pushService->unsubscribeUser($data['endpoint']);
            
            if (!$success) {
                return $this->errorResponse(
                    $response,
                    'Failed to unsubscribe from push notifications',
                    500,
                    [],
                    'UNSUBSCRIBE_FAILED'
                );
            }
            
            return $this->successResponse(
                $response,
                ['unsubscribed' => true],
                'Successfully unsubscribed from push notifications'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Unsubscribe error',
                500,
                [],
                'UNSUBSCRIBE_ERROR'
            );
        }
    }
    
    public function getPublicKey(Request $request, Response $response): Response
    {
        try {
            $publicKey = $this->pushService->getVapidPublicKey();
            
            return $this->successResponse(
                $response,
                ['public_key' => $publicKey],
                'VAPID public key retrieved'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to get public key',
                500,
                [],
                'PUBLIC_KEY_ERROR'
            );
        }
    }
    
    public function sendTestNotification(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $data = $this->getRequestData($request);
            
            // Create test notification payload
            $payload = $this->pushService->createSystemNotification(
                $data['title'] ?? 'Test Notification',
                $data['message'] ?? 'This is a test notification from Lobsters',
                $data['url'] ?? '/'
            );
            
            // Send to user (assuming we have their ID from the token)
            $success = $this->pushService->sendToUser($user['user_id'], $payload);
            
            if (!$success) {
                return $this->errorResponse(
                    $response,
                    'Failed to send test notification',
                    400,
                    [],
                    'NOTIFICATION_FAILED'
                );
            }
            
            return $this->successResponse(
                $response,
                ['sent' => true],
                'Test notification sent successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to send test notification',
                500,
                [],
                'NOTIFICATION_ERROR'
            );
        }
    }
    
    public function getUserSubscriptions(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            $subscriptions = $this->pushService->getUserSubscriptions($user['user_id']);
            
            // Don't expose sensitive subscription details
            $publicSubscriptions = array_map(function($sub) {
                return [
                    'endpoint' => substr($sub['endpoint'], 0, 50) . '...',
                    'created_at' => $sub['created_at'] ?? null
                ];
            }, $subscriptions);
            
            return $this->successResponse(
                $response,
                [
                    'subscriptions' => $publicSubscriptions,
                    'count' => count($subscriptions)
                ],
                'User subscriptions retrieved'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to get subscriptions',
                500,
                [],
                'SUBSCRIPTIONS_ERROR'
            );
        }
    }
    
    public function getNotificationStats(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            
            // Check if user has admin privileges
            if (!$user['is_admin']) {
                return $this->errorResponse(
                    $response,
                    'Admin access required',
                    403,
                    [],
                    'ADMIN_REQUIRED'
                );
            }
            
            $stats = $this->pushService->getNotificationStats();
            
            return $this->successResponse(
                $response,
                $stats,
                'Notification statistics retrieved'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to get notification stats',
                500,
                [],
                'STATS_ERROR'
            );
        }
    }
    
    public function sendBulkNotification(Request $request, Response $response): Response
    {
        // Check authentication
        $authError = $this->requireAuth($request, $response);
        if ($authError) {
            return $authError;
        }
        
        try {
            $user = $this->getUserFromToken($request);
            
            // Check if user has admin privileges
            if (!$user['is_admin']) {
                return $this->errorResponse(
                    $response,
                    'Admin access required',
                    403,
                    [],
                    'ADMIN_REQUIRED'
                );
            }
            
            $data = $this->getRequestData($request);
            
            // Validate required fields
            $missing = $this->validateRequiredFields($data, ['title', 'message']);
            if (!empty($missing)) {
                return $this->errorResponse(
                    $response,
                    'Missing required fields',
                    400,
                    $missing,
                    'VALIDATION_ERROR'
                );
            }
            
            // Create notification payload
            $payload = $this->pushService->createSystemNotification(
                $data['title'],
                $data['message'],
                $data['url'] ?? '/'
            );
            
            // In a real implementation, you would get all subscriptions
            // and send bulk notifications
            $subscriptions = []; // Get all active subscriptions from database
            
            $results = $this->pushService->sendBulkNotifications($subscriptions, $payload);
            
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $totalCount = count($results);
            
            return $this->successResponse(
                $response,
                [
                    'sent' => $successCount,
                    'total' => $totalCount,
                    'success_rate' => $totalCount > 0 ? ($successCount / $totalCount) : 0
                ],
                "Bulk notification sent to {$successCount} of {$totalCount} subscribers"
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Failed to send bulk notification',
                500,
                [],
                'BULK_NOTIFICATION_ERROR'
            );
        }
    }
}