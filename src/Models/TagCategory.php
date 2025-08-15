<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagCategory extends Model
{
    protected $table = 'tag_categories';
    
    protected $fillable = [
        'name',
        'description', 
        'sort_order',
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];
    
    /**
     * Get tags in this category
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'category_id');
    }
    
    /**
     * Get active tags in this category
     */
    public function activeTags(): HasMany
    {
        return $this->tags()->where('inactive', false);
    }
    
    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for ordered categories
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}