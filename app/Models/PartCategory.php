<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobilesentrix_category_id',
        'parent_id',
        'name',
        'slug',
        'level',
        'children_count',
        'description',
        'meta_keywords',
        'meta_title',
        'is_active',
        'is_anchor',
        'is_part',
        'has_children',
        'image_url',
        'status',
        'raw_payload',
        'synced_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_anchor' => 'boolean',
            'is_part' => 'boolean',
            'has_children' => 'boolean',
            'level' => 'integer',
            'children_count' => 'integer',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childCategories(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function syncedParts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_category_part')
            ->withPivot('mobilesentrix_category_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }
}
