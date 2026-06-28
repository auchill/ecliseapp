<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartModel extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'external_model_id',
        'part_brand_id',
        'name',
        'slug',
        'source_field',
        'raw_value',
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

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_model_part')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
