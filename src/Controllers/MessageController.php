<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Services\UserService;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class MessageController extends BaseController
{
    public function __construct(
        Engine $templates,
        private MessageService $messageService,
        private UserService $userService
    ) {
        parent::__construct($templates);
    }

    /**
     * Display user's message inbox
     */
    public function inbox(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $page = max(1, $page);

        try {
            $messages = $this->messageService->getInboxMessages($user, $page);
            $unreadCount = $this->messageService->getUnreadMessageCount($user);

            return $this->render($response, 'messages/inbox.php', [
                'title' => 'Messages',
                'messages' => $messages,
                'unread_count' => $unreadCount,
                'current_page' => $page,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return $this->render($response, 'messages/inbox.php', [
                'title' => 'Messages',
                'messages' => [],
                'unread_count' => 0,
                'current_page' => 1,
                'user' => $user,
                'error' => 'Failed to load messages: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display user's sent messages
     */
    public function sent(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $page = max(1, $page);

        try {
            $messages = $this->messageService->getSentMessages($user, $page);

            return $this->render($response, 'messages/sent.php', [
                'title' => 'Sent Messages',
                'messages' => $messages,
                'current_page' => $page,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return $this->render($response, 'messages/sent.php', [
                'title' => 'Sent Messages',
                'messages' => [],
                'current_page' => 1,
                'user' => $user,
                'error' => 'Failed to load sent messages: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display compose message form
     */
    public function compose(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $recipientUsername = $queryParams['to'] ?? '';
        $subject = $queryParams['subject'] ?? '';

        // Validate recipient if provided
        $recipient = null;
        if ($recipientUsername) {
            $recipient = User::where('username', $recipientUsername)->first();
            if (!$recipient) {
                return $this->render($response, 'messages/compose.php', [
                    'title' => 'Compose Message',
                    'user' => $user,
                    'error' => "User '{$recipientUsername}' not found."
                ]);
            }
        }

        return $this->render($response, 'messages/compose.php', [
            'title' => 'Compose Message',
            'user' => $user,
            'recipient_username' => $recipientUsername,
            'subject' => $subject,
            'recipient' => $recipient
        ]);
    }

    /**
     * Handle sending a new message
     */
    public function send(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $recipientUsername = trim($data['recipient_username'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $body = trim($data['body'] ?? '');

        try {
            // Find recipient
            $recipient = User::where('username', $recipientUsername)->first();
            if (!$recipient) {
                throw new \Exception("User '{$recipientUsername}' not found.");
            }

            if ($recipient->id === $user->id) {
                throw new \Exception("You cannot send a message to yourself.");
            }

            // Send message
            $message = $this->messageService->sendMessage($user, $recipient, $subject, $body);

            // Redirect to message thread
            return $response->withHeader('Location', "/messages/{$message->short_id}")
                          ->withStatus(302);

        } catch (\Exception $e) {
            return $this->render($response, 'messages/compose.php', [
                'title' => 'Compose Message',
                'user' => $user,
                'recipient_username' => $recipientUsername,
                'subject' => $subject,
                'body' => $body,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display a specific message thread
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $shortId = $args['id'] ?? '';

        try {
            $thread = $this->messageService->getMessageThread($shortId, $user);

            if (!$thread) {
                return $this->render($response, 'error.php', [
                    'title' => 'Message Not Found',
                    'message' => 'The requested message was not found or you do not have permission to view it.',
                    'user' => $user
                ])->withStatus(404);
            }

            return $this->render($response, 'messages/show.php', [
                'title' => $thread['message']['subject'],
                'thread' => $thread,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return $this->render($response, 'error.php', [
                'title' => 'Error',
                'message' => 'Failed to load message: ' . $e->getMessage(),
                'user' => $user
            ])->withStatus(500);
        }
    }

    /**
     * Handle replying to a message
     */
    public function reply(Request $request, Response $response, array $args): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $shortId = $args['id'] ?? '';
        $data = $request->getParsedBody();
        $body = trim($data['body'] ?? '');

        try {
            // Find original message
            $message = \App\Models\Message::where('short_id', $shortId)->first();
            if (!$message || !$message->isVisibleTo($user->id)) {
                throw new \Exception('Message not found or access denied.');
            }

            // Send reply
            $this->messageService->replyToMessage($message, $user, $body);

            // Redirect back to message thread
            return $response->withHeader('Location', "/messages/{$shortId}")
                          ->withStatus(302);

        } catch (\Exception $e) {
            // Redirect back with error
            return $response->withHeader('Location', "/messages/{$shortId}?error=" . urlencode($e->getMessage()))
                          ->withStatus(302);
        }
    }

    /**
     * Delete a message for the current user
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $shortId = $args['id'] ?? '';

        try {
            $success = $this->messageService->deleteMessage($shortId, $user);

            if ($success) {
                return $response->withHeader('Location', '/messages')
                              ->withStatus(302);
            } else {
                throw new \Exception('Failed to delete message.');
            }

        } catch (\Exception $e) {
            return $response->withHeader('Location', "/messages/{$shortId}?error=" . urlencode($e->getMessage()))
                          ->withStatus(302);
        }
    }

    /**
     * Search messages
     */
    public function search(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $query = trim($queryParams['q'] ?? '');
        $page = (int) ($queryParams['page'] ?? 1);
        $page = max(1, $page);

        $results = [];
        if ($query) {
            try {
                $results = $this->messageService->searchMessages($user, $query, $page);
            } catch (\Exception $e) {
                $results = [];
            }
        }

        return $this->render($response, 'messages/search.php', [
            'title' => 'Search Messages',
            'query' => $query,
            'results' => $results,
            'current_page' => $page,
            'user' => $user
        ]);
    }

    /**
     * Get unread message count (AJAX endpoint)
     */
    public function unreadCount(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user_id'])) {
            $response->getBody()->write('{"error":"Not authenticated"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            $response->getBody()->write('{"error":"User not found"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            $count = $this->messageService->getUnreadMessageCount($user);
            $response->getBody()->write(json_encode(['count' => $count]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write('{"error":"' . $e->getMessage() . '"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}