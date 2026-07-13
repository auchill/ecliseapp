<?php

namespace App\Models;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_brand_id',
        'product_category_id',
        'product_model_id',
        'product_grade_id',
        'product_condition_id',
        'product_color_id',
        'product_network_id',
        'name',
        'slug',
        'sku',
        'serial_number',
        'short_description',
        'description',
        'cost_price',
        'regular_price',
        'sale_price',
        'quantity',
        'low_stock_threshold',
        'source',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function productBrand(): BelongsTo
    {
        return $this->brand();
    }

    public function productCategory(): BelongsTo
    {
        return $this->category();
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(ProductSize::class, 'product_product_size')->withTimestamps();
    }

    public function productGrade(): BelongsTo
    {
        return $this->belongsTo(ProductGrade::class);
    }

    public function productCondition(): BelongsTo
    {
        return $this->belongsTo(ProductCondition::class);
    }

    public function productColor(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class);
    }

    public function condition(): BelongsTo
    {
        return $this->productCondition();
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(ProductNetwork::class, 'product_network_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true)->oldestOfMany('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('quantity', '>', 0);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->quantity > 0;
    }

    public function currentPrice(): float
    {
        return (float) $this->effective_price;
    }

    public function getEffectivePriceAttribute(): ?string
    {
        return $this->sale_price !== null
            ? (string) $this->sale_price
            : $this->regular_price;
    }

    public function regularDisplayPrice(): float
    {
        return (float) ($this->regular_price ?: 0);
    }

    public function brandName(): ?string
    {
        $brand = $this->relationLoaded('brand')
            ? $this->brand
            : ($this->relationLoaded('productBrand') ? $this->productBrand : $this->brand()->first());

        return $brand?->name;
    }

    public function categoryName(): ?string
    {
        $category = $this->relationLoaded('category')
            ? $this->category
            : ($this->relationLoaded('productCategory') ? $this->productCategory : $this->category()->first());

        return $category?->name;
    }

    public function modelName(): ?string
    {
        $model = $this->relationLoaded('productModel')
            ? $this->productModel
            : $this->productModel()->first();

        return $model?->name;
    }

    public function conditionName(): ?string
    {
        $condition = $this->relationLoaded('condition')
            ? $this->condition
            : ($this->relationLoaded('productCondition') ? $this->productCondition : $this->condition()->first());

        return $condition?->name;
    }

    public function imageUrl(): string
    {
        $image = $this->resolvedImage();

        if ($image) {
            return $image->displayUrl();
        }

        return CatalogImage::fallbackUrl();
    }

    public function sizeNames(): string
    {
        $sizes = $this->relationLoaded('sizes') ? $this->sizes : $this->sizes()->get();

        return $sizes->pluck('name')->filter()->implode(', ');
    }

    public function networkName(): ?string
    {
        $network = $this->relationLoaded('network')
            ? $this->network
            : $this->network()->first();

        return $network?->name;
    }

    public function getDisplayImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    private function resolvedImage(): ?ProductImage
    {
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            return $this->primaryImage;
        }

        if ($this->relationLoaded('images')) {
            return $this->images->firstWhere('is_primary', true)
                ?: $this->images->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])->first();
        }

        return $this->primaryImage()->first()
            ?: $this->images()->orderBy('sort_order')->orderBy('id')->first();
    }
}
