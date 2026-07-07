<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PartCategory extends Model
{
    use HasFactory;

    private const LOCAL_ID_FLOOR = 900000000000000000;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (PartCategory $category): void {
            if (filled($category->getKey())) {
                return;
            }

            $category->id = (int) max(
                self::LOCAL_ID_FLOOR,
                ((int) static::query()->where('id', '>=', self::LOCAL_ID_FLOOR)->max('id')) + 1,
            );
        });

        static::saving(function (PartCategory $category): void {
            if (blank($category->slug) && filled($category->name)) {
                $category->slug = Str::slug($category->name.' '.$category->id);
            }
        });
    }

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

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_category_part', 'category_id', 'part_id')
            ->withTimestamps();
    }

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->parentCategory();
    }

    public function childCategories(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->childCategories();
    }

    public function syncedParts(): BelongsToMany
    {
        return $this->parts();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }
}
