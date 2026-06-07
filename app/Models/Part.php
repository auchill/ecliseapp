<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Part extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'Screens',
        'Batteries',
        'Charging Ports',
        'Cameras',
        'Back Covers',
        'Speakers',
        'Keyboards',
        'Laptop Screens',
        'Laptop Batteries',
        'Motherboards',
        'Other',
    ];

    protected $fillable = [
        'part_brand_id',
        'part_category_id',
        'name',
        'sku',
        'internal_sku',
        'external_api_id',
        'external_api_source',
        'description',
        'cost_price',
        'device_type',
        'brand',
        'model_compatibility',
        'compatibility',
        'specifications',
        'part_category',
        'image_path',
        'image_url',
        'local_image_path',
        'price',
        'selling_price',
        'api_price',
        'final_price',
        'quantity',
        'api_quantity',
        'availability_status',
        'condition',
        'stock_status',
        'supplier',
        'is_api_item',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'api_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'quantity' => 'integer',
            'api_quantity' => 'integer',
            'compatibility' => 'array',
            'specifications' => 'array',
            'is_api_item' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function partBrand(): BelongsTo
    {
        return $this->belongsTo(PartBrand::class);
    }

    public function partCategory(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function brandName(): ?string
    {
        return $this->partBrand?->name ?? $this->brand;
    }

    public function categoryName(): ?string
    {
        return $this->partCategory?->name ?? $this->part_category;
    }

    public function displayPrice(): float
    {
        return (float) ($this->final_price ?: $this->selling_price ?: $this->price);
    }

    public function imageUrl(): string
    {
        if ($this->local_image_path ?: $this->image_path) {
            return asset('storage/'.($this->local_image_path ?: $this->image_path));
        }

        if ($this->image_url) {
            return $this->image_url;
        }

        return asset('images/brand/logo.png');
    }
}
