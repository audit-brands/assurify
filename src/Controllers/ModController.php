<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Moderation;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class ModController extends BaseController
{
    protected function requireModerator(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $user = User::find($_SESSION['user_id']);
            return $user && ($user->is_admin || $user->is_moderator);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCurrentUser(): ?User
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            return User::find($_SESSION['user_id']);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function logModerationAction(
        string $action,
        $subject = null,
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        $moderator = $this->getCurrentUser();
        if (!$moderator) {
            return;
        }

        try {
            Moderation::log(
                $moderator,
                $action,
                $subject,
                $reason,
                $metadata,
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        } catch (\Exception $e) {
            // Log silently, don't fail the moderation action
            error_log("Failed to log moderation action: " . $e->getMessage());
        }
    }

    protected function requireModeratorResponse(Response $response): Response
    {
        return $this->render($response, 'errors/forbidden', [
            'title' => 'Access Denied | Assurify'
        ])->withStatus(403);
    }

    protected function requireModeratorJsonResponse(Response $response): Response
    {
        return $this->json($response, ['error' => 'Access denied'], 403);
    }
}