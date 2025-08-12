<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ModerationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class ModerationController extends BaseController
{
    public function __construct(
        Engine $templates,
        private ModerationService $moderationService
    ) {
        parent::__construct($templates);
    }

    public function dashboard(Request $request, Response $response): Response
    {
        // Check if user is moderator
        if (!$this->requireModerator()) {
            return $this->render($response, 'errors/forbidden', [
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
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $type = $queryParams['type'] ?? 'all';

        $flaggedContent = $this->moderationService->getFlaggedContent();

        return $this->render($response, 'moderation/flagged', [
            'title' => 'Flagged Content | Lobsters',
            'type' => $type,
            'flagged_stories' => $flaggedContent['stories'],
            'flagged_comments' => $flaggedContent['comments']
        ]);
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
        if (!$this->requireModerator()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $limit = 50;

        $moderationLog = $this->moderationService->getModerationLog($limit);

        return $this->render($response, 'moderation/log', [
            'title' => 'Moderation Log | Lobsters',
            'log_entries' => $moderationLog,
            'page' => $page
        ]);
    }

    private function requireModerator(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            return $user && $this->moderationService->isUserModerator($user);
        } catch (\Exception $e) {
            return false;
        }
    }
}