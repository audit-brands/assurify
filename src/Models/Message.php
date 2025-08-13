<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'messages';
    
    protected $fillable = [
        'author_user_id',
        'recipient_user_id', 
        'subject',
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
     * Get the author of the message
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * Get the recipient of the message  
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Get all replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(MessageReply::class, 'message_id')
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Get visible replies (not deleted by the current user)
     */
    public function visibleReplies(int $userId): HasMany
    {
        return $this->hasMany(MessageReply::class, 'message_id')
                    ->where(function($query) use ($userId) {
                        $query->where('author_user_id', $userId)
                              ->where('deleted_by_author', false);
                    })
                    ->orWhere(function($query) use ($userId) {
                        $query->where('recipient_user_id', $userId)
                              ->where('deleted_by_recipient', false);
                    })
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Check if message is visible to a specific user
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
     * Mark message as read
     */
    public function markAsRead(): void
    {
        if (!$this->has_been_read) {
            $this->has_been_read = true;
            $this->save();
        }
    }

    /**
     * Delete message for a specific user
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
     * Check if message thread has unread messages for user
     */
    public function hasUnreadMessages(int $userId): bool
    {
        // Check main message
        if (($this->recipient_user_id === $userId || $this->author_user_id === $userId) && 
            !$this->has_been_read && $this->author_user_id !== $userId) {
            return true;
        }

        // Check replies
        $unreadReplies = $this->replies()
                             ->where('recipient_user_id', $userId)
                             ->where('has_been_read', false)
                             ->count();
                             
        return $unreadReplies > 0;
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
     * Get last activity timestamp for the thread
     */
    public function getLastActivity(): string
    {
        $lastReply = $this->replies()->orderBy('created_at', 'desc')->first();
        
        if ($lastReply) {
            return $lastReply->created_at->format('Y-m-d H:i:s');
        }
        
        return $this->created_at->format('Y-m-d H:i:s');
    }

    /**
     * Get total message count in thread (including replies)
     */
    public function getTotalMessages(): int
    {
        return 1 + $this->replies()->count();
    }

    /**
     * Generate unique short ID for message
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

    /**
     * Legacy method for backward compatibility
     */
    public function isDeletedFor(User $user): bool
    {
        return !$this->isVisibleTo($user->id);
    }
}