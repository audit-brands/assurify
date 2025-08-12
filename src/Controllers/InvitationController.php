<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvitationService;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class InvitationController extends BaseController
{
    public function __construct(
        Engine $templates,
        private InvitationService $invitationService,
        private AuthService $authService
    ) {
        parent::__construct($templates);
    }

    public function index(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['auth_redirect'] = '/invitations';
            return $this->redirect($response, '/auth/login');
        }

        $user = \App\Models\User::find($_SESSION['user_id']);
        $invitations = $this->invitationService->getUserInvitations($user);
        $stats = $this->invitationService->getInvitationStats($user);

        return $this->render($response, 'invitations/index', [
            'title' => 'Invitations | Lobsters',
            'user' => $user,
            'invitations' => $invitations,
            'stats' => $stats,
            'success' => $_SESSION['invitation_success'] ?? null,
            'error' => $_SESSION['invitation_error'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['auth_redirect'] = '/invitations';
            return $this->redirect($response, '/auth/login');
        }

        $user = \App\Models\User::find($_SESSION['user_id']);
        $stats = $this->invitationService->getInvitationStats($user);

        if (!$stats['can_invite']) {
            $_SESSION['invitation_error'] = 'You cannot send invitations at this time. Check your karma and recent invitation limits.';
            return $this->redirect($response, '/invitations');
        }

        return $this->render($response, 'invitations/create', [
            'title' => 'Send Invitation | Lobsters',
            'user' => $user,
            'stats' => $stats,
            'error' => $_SESSION['invitation_error'] ?? null,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/auth/login');
        }

        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $memo = trim($data['memo'] ?? '');

        // Clear previous messages
        unset($_SESSION['invitation_success'], $_SESSION['invitation_error']);

        // Validate input
        if (empty($email)) {
            $_SESSION['invitation_error'] = 'Email address is required';
            return $this->redirect($response, '/invitations/create');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['invitation_error'] = 'Invalid email address';
            return $this->redirect($response, '/invitations/create');
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $invitation = $this->invitationService->createInvitation($user, $email, $memo);

            $_SESSION['invitation_success'] = "Invitation sent to {$email}";
            return $this->redirect($response, '/invitations');
        } catch (\Exception $e) {
            $_SESSION['invitation_error'] = $e->getMessage();
            return $this->redirect($response, '/invitations/create');
        }
    }

    public function tree(Request $request, Response $response): Response
    {
        // Show invitation tree - who invited whom
        $users = \App\Models\User::with(['invitations' => function ($query) {
            $query->whereNotNull('used_at');
        }])->get();

        return $this->render($response, 'invitations/tree', [
            'title' => 'Invitation Tree | Lobsters',
            'users' => $users,
        ]);
    }
}
