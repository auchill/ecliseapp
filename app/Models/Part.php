<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    private const LOCAL_ID_FLOOR = 900000000000000000;

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

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Part $part): void {
            if (filled($part->getKey())) {
                return;
            }

            $part->id = (int) max(
                self::LOCAL_ID_FLOOR,
                ((int) static::query()->where('id', '>=', self::LOCAL_ID_FLOOR)->max('id')) + 1,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'api_updated_at' => 'datetime',
            'product_hold_date' => 'datetime',
            'featured' => 'boolean',
            'premium' => 'boolean',
            'end_of_life' => 'boolean',
            'weight' => 'decimal:4',
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'length' => 'decimal:4',
            'is_in_stock' => 'boolean',
            'category_ids' => 'array',
            'regular_price_with_tax' => 'decimal:4',
            'regular_price_without_tax' => 'decimal:4',
            'final_price_with_tax' => 'decimal:4',
            'final_price_without_tax' => 'decimal:4',
            'customer_price' => 'decimal:4',
            'is_saleable' => 'boolean',
            'image_gallery' => 'array',
            'in_stock_qty' => 'integer',
            'is_preorder' => 'boolean',
            'related_product' => 'array',
            'model_text' => 'array',
            'total_reviews_count' => 'integer',
            'tier_price' => 'array',
            'has_custom_options' => 'boolean',
            'cost_price' => 'decimal:4',
            'compatibility' => 'array',
            'specifications' => 'array',
            'price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'markup_value' => 'decimal:4',
            'api_price' => 'decimal:4',
            'final_price' => 'decimal:4',
            'quantity' => 'integer',
            'api_quantity' => 'integer',
            'is_api_item' => 'boolean',
            'is_active' => 'boolean',
            'raw_payload' => 'array',
            'last_price_synced_at' => 'datetime',
            'last_stock_synced_at' => 'datetime',
            'synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(PartBrand::class, 'part_brand_id');
    }

    public function partBrand(): BelongsTo
    {
        return $this->brand();
    }

    public function partCategory(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function partModel(): BelongsTo
    {
        return $this->belongsTo(PartModel::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PartCategory::class, 'part_category_part', 'part_id', 'category_id')
            ->withTimestamps();
    }

    public function partCategories(): BelongsToMany
    {
        return $this->categories();
    }

    public function models(): BelongsToMany
    {
        return $this->belongsToMany(PartModel::class, 'part_model_part')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PartTag::class, 'part_part_tag')
            ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(PartImage::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(PartCompatibility::class);
    }

    public function relatedParts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'part_related_parts', 'part_id', 'related_part_id')
            ->withTimestamps();
    }

    public function brandName(): ?string
    {
        return $this->brand?->name ?? $this->manufacturer_text ?? $this->brand_text ?? $this->brand;
    }

    public function categoryName(): ?string
    {
        return $this->partCategory?->name ?? $this->categories->first()?->name ?? $this->part_category;
    }

    public function modelName(): ?string
    {
        if ($this->partModel?->name) {
            return $this->partModel->name;
        }

        $model = $this->models->first()?->name;

        if ($model) {
            return $model;
        }

        $modelText = $this->model_text;

        if (is_array($modelText)) {
            return implode(', ', array_filter($modelText));
        }

        return $this->model_compatibility;
    }

    public function displayPrice(): float
    {
        return (float) ($this->selling_price ?: $this->final_price ?: $this->customer_price ?: $this->price);
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
