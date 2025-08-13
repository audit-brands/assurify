<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hat extends Model
{
    protected $fillable = [
        'user_id',
        'granted_by_user_id',
        'hat',
        'link',
        'modlog_use',
        'doffed_at',
        'token'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'granted_by_user_id' => 'integer',
        'modlog_use' => 'boolean',
        'doffed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    // Check if hat is currently active (not doffed)
    public function isActive(): bool
    {
        return is_null($this->doffed_at);
    }

    // Doff (remove) the hat
    public function doff(): void
    {
        $this->doffed_at = now();
        $this->save();
    }
}