<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartModel extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'mobilesentrix_model_id',
        'part_brand_id',
        'name',
        'slug',
        'status',
        'description',
        'raw_payload',
        'synced_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function partBrand(): BelongsTo
    {
        return $this->belongsTo(PartBrand::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
