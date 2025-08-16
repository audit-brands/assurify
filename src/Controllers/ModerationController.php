<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ModerationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class ModerationController extends ModController
{
    private ModerationService $moderationService;

    public function __construct(Engine $templates, ModerationService $moderationService)
    {
        parent::__construct($templates);
        $this->moderationService = $moderationService;
    }

    public function dashboard(Request $request, Response $response): Response
    {
        // Check if user is moderator
        if (!$this->requireModerator()) {
            return $this->render($response, 'errors/403', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $stats = $this->moderationService->getModerationStats();
        $flaggedContent = $this->moderationService->getFlaggedContent();
        $moderationLog = $this->moderationService->getModerationLog(20);

        return $this->render($response, 'moderation/dashboard', [
            'title' => 'Moderation Dashboard | Lobsters',
            'stats' => $stats,
            'flagged_stories' => $flaggedContent['stories'],
            'flagged_comments' => $flaggedContent['comments'],
            'moderation_log' => $moderationLog
        ]);
    }

    public function flaggedContent(Request $request, Response $response): Response
    {
        if (!$this->requireModerator()) {
            return $this->render($response, 'errors/403', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $type = $queryParams['type'] ?? 'all';

        try {
            $flaggedContent = $this->moderationService->getFlaggedContent();
            
            return $this->render($response, 'moderation/flagged', [
                'title' => 'Flagged Content | Lobsters',
                'type' => $type,
                'flagged_stories' => $flaggedContent['stories'] ?? [],
                'flagged_comments' => $flaggedContent['comments'] ?? []
            ]);
        } catch (\Exception $e) {
            // If there's an error, return a simple error page
            return $this->render($response, 'moderation/flagged', [
                'title' => 'Flagged Content | Lobsters',
                'type' => $type,
                'flagged_stories' => [],
                'flagged_comments' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    public function moderateStory(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $storyId = (int) $args['id'];
        $data = $request->getParsedBody();
        $action = $data['action'] ?? '';
        $reason = $data['reason'] ?? null;

        if (!in_array($action, ['approve', 'delete', 'flag', 'unflag'])) {
            return $this->json($response, ['error' => 'Invalid action'], 400);
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $success = $this->moderationService->moderateStory($storyId, $action, $user, $reason);

            if ($success) {
                return $this->json($response, [
                    'success' => true,
                    'message' => "Story {$action}d successfully"
                ]);
            } else {
                return $this->json($response, ['error' => 'Moderation action failed'], 500);
            }
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'An error occurred'], 500);
        }
    }

    public function moderateComment(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $commentId = (int) $args['id'];
        $data = $request->getParsedBody();
        $action = $data['action'] ?? '';
        $reason = $data['reason'] ?? null;

        if (!in_array($action, ['approve', 'delete', 'flag', 'unflag'])) {
            return $this->json($response, ['error' => 'Invalid action'], 400);
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $success = $this->moderationService->moderateComment($commentId, $action, $user, $reason);

            if ($success) {
                return $this->json($response, [
                    'success' => true,
                    'message' => "Comment {$action}d successfully"
                ]);
            } else {
                return $this->json($response, ['error' => 'Moderation action failed'], 500);
            }
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'An error occurred'], 500);
        }
    }

    public function banUser(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $userId = (int) $args['id'];
        $data = $request->getParsedBody();
        $reason = $data['reason'] ?? 'No reason provided';
        $duration = (int) ($data['duration'] ?? 0);

        try {
            $moderator = \App\Models\User::find($_SESSION['user_id']);
            $success = $this->moderationService->banUser($userId, $moderator, $reason, $duration);

            if ($success) {
                return $this->json($response, [
                    'success' => true,
                    'message' => 'User banned successfully'
                ]);
            } else {
                return $this->json($response, ['error' => 'Ban action failed'], 500);
            }
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'An error occurred'], 500);
        }
    }

    public function unbanUser(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $userId = (int) $args['id'];

        try {
            $moderator = \App\Models\User::find($_SESSION['user_id']);
            $success = $this->moderationService->unbanUser($userId, $moderator);

            if ($success) {
                return $this->json($response, [
                    'success' => true,
                    'message' => 'User unbanned successfully'
                ]);
            } else {
                return $this->json($response, ['error' => 'Unban action failed'], 500);
            }
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'An error occurred'], 500);
        }
    }

    public function moderationLog(Request $request, Response $response): Response
    {
        // Make moderation log public for transparency (like Lobste.rs)
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $moderator = $queryParams['moderator'] ?? null;
        $action = $queryParams['action'] ?? null;
        $subjectType = $queryParams['subject_type'] ?? null;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Build query for moderation log
        $query = \App\Models\Moderation::with(['moderator'])
                                      ->orderBy('created_at', 'desc');

        // Apply filters
        if ($moderator) {
            $user = \App\Models\User::where('username', $moderator)->first();
            if ($user) {
                $query->byModerator($user);
            }
        }

        if ($action) {
            $query->byAction($action);
        }

        if ($subjectType) {
            $query->bySubjectType($subjectType);
        }

        // Get total count for pagination
        $totalCount = $query->count();
        
        // Get paginated results
        $moderations = $query->skip($offset)->take($limit)->get();

        // Get available moderators and actions for filters
        $moderators = \App\Models\User::whereHas('moderations', function($query) {
            $query->where('moderator_user_id', '!=', null);
        })->distinct()->pluck('username')->sort();

        $actions = \App\Models\Moderation::distinct()->pluck('action')->sort();
        $subjectTypes = \App\Models\Moderation::distinct()->pluck('subject_type')->filter()->sort();

        return $this->render($response, 'pages/moderation-log', [
            'title' => 'Moderation Log | Assurify',
            'moderations' => $moderations,
            'page' => $page,
            'total_pages' => (int) ceil($totalCount / $limit),
            'has_prev' => $page > 1,
            'has_next' => $page < ceil($totalCount / $limit),
            'filters' => [
                'moderator' => $moderator,
                'action' => $action,
                'subject_type' => $subjectType
            ],
            'available_moderators' => $moderators,
            'available_actions' => $actions,
            'available_subject_types' => $subjectTypes
        ]);
    }
}

