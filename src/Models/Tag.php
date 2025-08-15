<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    protected $fillable = [
        'tag',
        'description',
        'privileged',
        'is_media',
        'inactive',
        'hotness_mod',
        'category_id',
        'token'
    ];

    protected $casts = [
        'privileged' => 'boolean',
        'is_media' => 'boolean',
        'inactive' => 'boolean',
        'hotness_mod' => 'float'
    ];

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'taggings');
    }

    public function taggings(): HasMany
    {
        return $this->hasMany(Tagging::class);
    }
    
    /**
     * Get the category this tag belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'category_id');
    }
}
