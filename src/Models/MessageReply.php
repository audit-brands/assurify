<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReply extends Model
{
    protected $table = 'message_replies';
    
    protected $fillable = [
        'message_id',
        'author_user_id',
        'recipient_user_id',
        'body',
        'short_id',
        'deleted_by_author',
        'deleted_by_recipient',
        'has_been_read'
    ];

    protected $casts = [
        'deleted_by_author' => 'boolean',
        'deleted_by_recipient' => 'boolean',
        'has_been_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the parent message
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * Get the author of the reply
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * Get the recipient of the reply
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Check if reply is visible to a specific user
     */
    public function isVisibleTo(int $userId): bool
    {
        if ($this->author_user_id === $userId && !$this->deleted_by_author) {
            return true;
        }
        
        if ($this->recipient_user_id === $userId && !$this->deleted_by_recipient) {
            return true;
        }
        
        return false;
    }

    /**
     * Mark reply as read
     */
    public function markAsRead(): void
    {
        if (!$this->has_been_read) {
            $this->has_been_read = true;
            $this->save();
        }
    }

    /**
     * Delete reply for a specific user
     */
    public function deleteForUser(int $userId): bool
    {
        if ($this->author_user_id === $userId) {
            $this->deleted_by_author = true;
        } elseif ($this->recipient_user_id === $userId) {
            $this->deleted_by_recipient = true;
        } else {
            return false;
        }
        
        return $this->save();
    }

    /**
     * Get the other participant in the conversation
     */
    public function getOtherParticipant(int $userId): ?User
    {
        if ($this->author_user_id === $userId) {
            return $this->recipient;
        } elseif ($this->recipient_user_id === $userId) {
            return $this->author;
        }
        
        return null;
    }

    /**
     * Generate unique short ID for reply
     */
    public static function generateShortId(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $shortId = '';
            for ($i = 0; $i < 6; $i++) {
                $shortId .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::where('short_id', $shortId)->exists());

        return $shortId;
    }
}