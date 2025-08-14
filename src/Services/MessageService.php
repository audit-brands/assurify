<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Models\MessageReply;
use App\Models\User;
use Michelf\Markdown;

class MessageService
{
    /**
     * Send a new message
     */
    public function sendMessage(User $author, User $recipient, string $subject, string $body): Message
    {
        // Check if recipient accepts messages based on their privacy settings
        if (!$this->canUserReceiveMessage($author, $recipient)) {
            $profile = $recipient->getProfile();
            $setting = $profile->allow_messages_from ?? 'members';
            
            $message = match ($setting) {
                'none' => "User {$recipient->username} is not accepting private messages.",
                'followed_users' => "User {$recipient->username} only accepts messages from users they follow.",
                'members' => "User {$recipient->username} only accepts messages from registered members.",
                default => "You cannot send a message to {$recipient->username}."
            };
            
            throw new \Exception($message);
        }

        // Validate message data
        $this->validateMessageData($subject, $body);

        // Create message
        $message = new Message();
        $message->author_user_id = $author->id;
        $message->recipient_user_id = $recipient->id;
        $message->subject = trim($subject);
        $message->body = trim($body);
        $message->short_id = Message::generateShortId();
        $message->token = bin2hex(random_bytes(16));
        $message->has_been_read = false;
        $message->deleted_by_author = false;
        $message->deleted_by_recipient = false;
        $message->created_at = date('Y-m-d H:i:s');
        $message->updated_at = date('Y-m-d H:i:s');

        $message->save();

        // Send notification if enabled
        if ($recipient->email_message_notifications) {
            $this->sendEmailNotification($message);
        }

        return $message;
    }

    /**
     * Reply to an existing message
     */
    public function replyToMessage(Message $message, User $author, string $body): MessageReply
    {
        // Determine recipient (the other participant in the conversation)
        $recipient = $message->getOtherParticipant($author->id);
        
        if (!$recipient) {
            throw new \Exception("Invalid message thread.");
        }

        // Check if recipient accepts messages
        if ($recipient->disable_private_messages) {
            throw new \Exception("User {$recipient->username} is not accepting private messages.");
        }

        // Validate reply body
        if (empty(trim($body))) {
            throw new \Exception("Reply body cannot be empty.");
        }

        if (strlen($body) > 65535) {
            throw new \Exception("Reply body is too long (maximum 65,535 characters).");
        }

        // Create reply
        $reply = new MessageReply();
        $reply->message_id = $message->id;
        $reply->author_user_id = $author->id;
        $reply->recipient_user_id = $recipient->id;
        $reply->body = trim($body);
        $reply->short_id = MessageReply::generateShortId();
        $reply->has_been_read = false;
        $reply->deleted_by_author = false;
        $reply->deleted_by_recipient = false;
        $reply->created_at = date('Y-m-d H:i:s');
        $reply->updated_at = date('Y-m-d H:i:s');

        $reply->save();

        // Update parent message timestamp
        $message->updated_at = date('Y-m-d H:i:s');
        $message->save();

        // Send notification if enabled
        if ($recipient->email_message_notifications) {
            $this->sendReplyEmailNotification($message, $reply);
        }

        return $reply;
    }

    /**
     * Get user's inbox messages
     */
    public function getInboxMessages(User $user, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;

        $messages = Message::where(function($query) use ($user) {
                        $query->where('recipient_user_id', $user->id)
                              ->where('deleted_by_recipient', false);
                    })
                    ->orWhere(function($query) use ($user) {
                        $query->where('author_user_id', $user->id)
                              ->where('deleted_by_author', false);
                    })
                    ->with(['author', 'recipient', 'replies'])
                    ->orderBy('updated_at', 'desc')
                    ->skip($offset)
                    ->take($limit)
                    ->get();

        return $this->formatMessagesForView($messages, $user->id);
    }

    /**
     * Get user's sent messages
     */
    public function getSentMessages(User $user, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;

        $messages = Message::where('author_user_id', $user->id)
                          ->where('deleted_by_author', false)
                          ->with(['author', 'recipient', 'replies'])
                          ->orderBy('created_at', 'desc')
                          ->skip($offset)
                          ->take($limit)
                          ->get();

        return $this->formatMessagesForView($messages, $user->id);
    }

    /**
     * Get a specific message thread for viewing
     */
    public function getMessageThread(string $shortId, User $user): ?array
    {
        $message = Message::where('short_id', $shortId)
                         ->with(['author', 'recipient', 'replies.author', 'replies.recipient'])
                         ->first();

        if (!$message || !$message->isVisibleTo($user->id)) {
            return null;
        }

        // Mark as read if user is recipient
        if ($message->recipient_user_id === $user->id && !$message->has_been_read) {
            $message->markAsRead();
        }

        // Mark replies as read
        $unreadReplies = $message->replies()
                               ->where('recipient_user_id', $user->id)
                               ->where('has_been_read', false)
                               ->get();

        foreach ($unreadReplies as $reply) {
            $reply->markAsRead();
        }

        return $this->formatMessageThreadForView($message, $user->id);
    }

    /**
     * Delete a message for a specific user
     */
    public function deleteMessage(string $shortId, User $user): bool
    {
        $message = Message::where('short_id', $shortId)->first();

        if (!$message) {
            return false;
        }

        return $message->deleteForUser($user->id);
    }

    /**
     * Get count of unread messages for user
     */
    public function getUnreadMessageCount(User $user): int
    {
        // Count unread messages where user is recipient
        $unreadMessages = Message::where('recipient_user_id', $user->id)
                                ->where('deleted_by_recipient', false)
                                ->where('has_been_read', false)
                                ->count();

        // Count unread replies where user is recipient
        $unreadReplies = MessageReply::where('recipient_user_id', $user->id)
                                   ->where('deleted_by_recipient', false)
                                   ->where('has_been_read', false)
                                   ->count();

        return $unreadMessages + $unreadReplies;
    }

    /**
     * Search messages
     */
    public function searchMessages(User $user, string $query, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;

        $messages = Message::where(function($queryBuilder) use ($user) {
                        $queryBuilder->where('recipient_user_id', $user->id)
                                   ->where('deleted_by_recipient', false)
                                   ->orWhere(function($subQuery) use ($user) {
                                       $subQuery->where('author_user_id', $user->id)
                                              ->where('deleted_by_author', false);
                                   });
                    })
                    ->where(function($queryBuilder) use ($query) {
                        $queryBuilder->where('subject', 'LIKE', "%{$query}%")
                                   ->orWhere('body', 'LIKE', "%{$query}%");
                    })
                    ->with(['author', 'recipient', 'replies'])
                    ->orderBy('updated_at', 'desc')
                    ->skip($offset)
                    ->take($limit)
                    ->get();

        return $this->formatMessagesForView($messages, $user->id);
    }

    /**
     * Get conversation between two users
     */
    public function getConversation(User $user1, User $user2, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;

        $messages = Message::where(function($query) use ($user1, $user2) {
                        $query->where('author_user_id', $user1->id)
                              ->where('recipient_user_id', $user2->id);
                    })
                    ->orWhere(function($query) use ($user1, $user2) {
                        $query->where('author_user_id', $user2->id)
                              ->where('recipient_user_id', $user1->id);
                    })
                    ->where(function($query) use ($user1) {
                        $query->where('author_user_id', $user1->id)
                              ->where('deleted_by_author', false)
                              ->orWhere('recipient_user_id', $user1->id)
                              ->where('deleted_by_recipient', false);
                    })
                    ->with(['author', 'recipient', 'replies'])
                    ->orderBy('created_at', 'desc')
                    ->skip($offset)
                    ->take($limit)
                    ->get();

        return $this->formatMessagesForView($messages, $user1->id);
    }

    /**
     * Format messages for display
     */
    private function formatMessagesForView($messages, int $currentUserId): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $otherParticipant = $message->getOtherParticipant($currentUserId);
            
            $formatted[] = [
                'id' => $message->id,
                'short_id' => $message->short_id,
                'subject' => $message->subject,
                'body' => $message->body,
                'author_id' => $message->author_user_id,
                'recipient_id' => $message->recipient_user_id,
                'author_username' => $message->author->username ?? 'Unknown',
                'recipient_username' => $message->recipient->username ?? 'Unknown',
                'other_participant' => $otherParticipant ? $otherParticipant->username : 'Unknown',
                'other_participant_id' => $otherParticipant ? $otherParticipant->id : null,
                'has_been_read' => $message->has_been_read,
                'has_unread_messages' => $message->hasUnreadMessages($currentUserId),
                'reply_count' => $message->replies()->count(),
                'last_activity' => $message->getLastActivity(),
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $this->timeAgo($message->created_at->format('Y-m-d H:i:s')),
                'is_author' => $message->author_user_id === $currentUserId
            ];
        }

        return $formatted;
    }

    /**
     * Format message thread for viewing
     */
    private function formatMessageThreadForView(Message $message, int $currentUserId): array
    {
        $otherParticipant = $message->getOtherParticipant($currentUserId);
        
        $thread = [
            'message' => [
                'id' => $message->id,
                'short_id' => $message->short_id,
                'subject' => $message->subject,
                'body' => $message->body,
                'body_html' => Markdown::defaultTransform($message->body),
                'author_id' => $message->author_user_id,
                'recipient_id' => $message->recipient_user_id,
                'author_username' => $message->author->username ?? 'Unknown',
                'recipient_username' => $message->recipient->username ?? 'Unknown',
                'other_participant' => $otherParticipant ? $otherParticipant->username : 'Unknown',
                'other_participant_id' => $otherParticipant ? $otherParticipant->id : null,
                'has_been_read' => $message->has_been_read,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $this->timeAgo($message->created_at->format('Y-m-d H:i:s')),
                'is_author' => $message->author_user_id === $currentUserId
            ],
            'replies' => []
        ];

        // Add replies
        $visibleReplies = $message->visibleReplies($currentUserId)->get();
        foreach ($visibleReplies as $reply) {
            $thread['replies'][] = [
                'id' => $reply->id,
                'short_id' => $reply->short_id,
                'body' => $reply->body,
                'body_html' => Markdown::defaultTransform($reply->body),
                'author_id' => $reply->author_user_id,
                'recipient_id' => $reply->recipient_user_id,
                'author_username' => $reply->author->username ?? 'Unknown',
                'recipient_username' => $reply->recipient->username ?? 'Unknown',
                'has_been_read' => $reply->has_been_read,
                'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $this->timeAgo($reply->created_at->format('Y-m-d H:i:s')),
                'is_author' => $reply->author_user_id === $currentUserId
            ];
        }

        return $thread;
    }

    /**
     * Validate message data
     */
    private function validateMessageData(string $subject, string $body): void
    {
        if (empty(trim($subject))) {
            throw new \Exception("Subject cannot be empty.");
        }

        if (strlen($subject) > 100) {
            throw new \Exception("Subject is too long (maximum 100 characters).");
        }

        if (empty(trim($body))) {
            throw new \Exception("Message body cannot be empty.");
        }

        if (strlen($body) > 65535) {
            throw new \Exception("Message body is too long (maximum 65,535 characters).");
        }
    }

    /**
     * Send email notification for new message
     */
    private function sendEmailNotification(Message $message): void
    {
        // TODO: Implement email notification
        // This would integrate with your email service
    }

    /**
     * Send email notification for message reply
     */
    private function sendReplyEmailNotification(Message $message, MessageReply $reply): void
    {
        // TODO: Implement email notification for replies
        // This would integrate with your email service
    }

    /**
     * Check if a user can receive messages from another user
     */
    private function canUserReceiveMessage(User $author, User $recipient): bool
    {
        $profile = $recipient->getProfile();
        $setting = $profile->allow_messages_from ?? 'members';
        
        return match ($setting) {
            'anyone' => true,
            'members' => $author->id !== null, // Registered user
            'followed_users' => $recipient->isFollowing($author->id),
            'none' => false,
            default => true
        };
    }

    /**
     * Calculate time ago string
     */
    private function timeAgo(string $datetime): string
    {
        $time = time() - strtotime($datetime);

        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . 'm ago';
        if ($time < 86400) return floor($time/3600) . 'h ago';
        if ($time < 2592000) return floor($time/86400) . 'd ago';
        if ($time < 31536000) return floor($time/2592000) . 'mo ago';
        
        return floor($time/31536000) . 'y ago';
    }
}