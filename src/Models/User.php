<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'created_at',
        'is_admin',
        'is_moderator',
        'karma',
        'about',
        'email_notifications',
        'pushover_notifications',
        'pushover_user_key',
        'pushover_sound',
        'mailing_list_mode',
        'show_avatars',
        'show_story_previews',
        'show_submit_tagging_hints',
        'show_read_ribbons',
        'hide_dragons',
        'post_reply_notifications',
        'theme',
        'session_token',
        'password_reset_token'
    ];

    protected $hidden = [
        'password_hash',
        'session_token',
        'password_reset_token'
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_moderator' => 'boolean',
        'karma' => 'integer',
        'email_notifications' => 'boolean',
        'pushover_notifications' => 'boolean',
        'mailing_list_mode' => 'integer',
        'show_avatars' => 'boolean',
        'show_story_previews' => 'boolean',
        'show_submit_tagging_hints' => 'boolean',
        'show_read_ribbons' => 'boolean',
        'hide_dragons' => 'boolean',
        'post_reply_notifications' => 'boolean'
    ];

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
