<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartBrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobilesentrix_manufacturer_id',
        'name',
        'slug',
        'description',
        'is_active',
        'status',
        'raw_payload',
        'synced_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function partModels(): HasMany
    {
        return $this->hasMany(PartModel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }
}
