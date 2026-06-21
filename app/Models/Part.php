<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Part extends Model
{
    use HasFactory;

    public const MARKUP_TYPES = [
        'none' => 'No markup',
        'fixed' => 'Fixed amount',
        'percentage' => 'Percentage',
    ];

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
        'mobilesentrix_product_id',
        'part_brand_id',
        'part_category_id',
        'part_model_id',
        'name',
        'slug',
        'sku',
        'new_sku',
        'barcode',
        'internal_sku',
        'external_api_id',
        'external_api_source',
        'description',
        'short_description',
        'product_extra_info',
        'mobilesentrix_url_key',
        'mobilesentrix_url',
        'default_image',
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
        'markup_type',
        'markup_value',
        'api_price',
        'final_price',
        'stock_id',
        'is_in_stock',
        'in_stock_qty',
        'quantity',
        'api_quantity',
        'weight',
        'height',
        'width',
        'length',
        'hst_code',
        'hst_description',
        'manufacturer_id',
        'manufacturer_text',
        'model_id',
        'model_text',
        'front_position',
        'front_position_text',
        'warranty_period',
        'warranty_period_text',
        'product_badges',
        'product_badges_text',
        'featured',
        'premium',
        'end_of_life',
        'api_status',
        'status',
        'availability_status',
        'condition',
        'stock_status',
        'supplier',
        'is_api_item',
        'is_active',
        'raw_payload',
        'api_updated_at',
        'last_price_synced_at',
        'last_stock_synced_at',
        'synced_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'markup_value' => 'decimal:2',
            'api_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'is_in_stock' => 'boolean',
            'in_stock_qty' => 'integer',
            'quantity' => 'integer',
            'api_quantity' => 'integer',
            'weight' => 'decimal:4',
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'length' => 'decimal:4',
            'compatibility' => 'array',
            'specifications' => 'array',
            'model_text' => 'array',
            'featured' => 'boolean',
            'premium' => 'boolean',
            'end_of_life' => 'boolean',
            'is_api_item' => 'boolean',
            'is_active' => 'boolean',
            'raw_payload' => 'array',
            'api_updated_at' => 'datetime',
            'last_price_synced_at' => 'datetime',
            'last_stock_synced_at' => 'datetime',
            'synced_at' => 'datetime',
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

    public function partModel(): BelongsTo
    {
        return $this->belongsTo(PartModel::class);
    }

    public function partCategories(): BelongsToMany
    {
        return $this->belongsToMany(PartCategory::class, 'part_category_part')
            ->withPivot('mobilesentrix_category_id')
            ->withTimestamps();
    }

    public function brandName(): ?string
    {
        return $this->partBrand?->name ?? $this->manufacturer_text ?? $this->brand;
    }

    public function categoryName(): ?string
    {
        return $this->partCategory?->name ?? $this->part_category;
    }

    public function modelName(): ?string
    {
        if ($this->partModel?->name) {
            return $this->partModel->name;
        }

        $modelText = $this->model_text;

        if (is_array($modelText)) {
            return implode(', ', array_filter($modelText));
        }

        return $this->model_compatibility;
    }

    public function displayPrice(): float
    {
        return (float) ($this->selling_price ?: $this->final_price ?: $this->price);
    }

    public function imageUrl(): string
    {
        if ($this->local_image_path ?: $this->image_path) {
            return asset('storage/'.($this->local_image_path ?: $this->image_path));
        }

        if ($this->default_image ?: $this->image_url) {
            return $this->default_image ?: $this->image_url;
        }

        return asset('images/brand/logo.png');
    }

    public function stockLabel(): string
    {
        if ($this->is_in_stock || $this->quantity > 0 || $this->in_stock_qty > 0) {
            return 'In stock';
        }

        return $this->availability_status ?: $this->stock_status ?: 'Check availability';
    }
}
