<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    public const CONDITIONS = ['New', 'Used', 'Refurbished'];

    public const STATUSES = ['Active', 'Inactive', 'Out of Stock'];

    protected $fillable = [
        'category_id',
        'product_brand_id',
        'product_category_id',
        'product_model_id',
        'name',
        'slug',
        'sku',
        'brand',
        'model',
        'condition',
        'description',
        'price',
        'sale_price',
        'quantity',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productBrand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active')->where('quantity', '>', 0);
    }

    public function currentPrice(): float
    {
        return (float) ($this->sale_price ?: $this->price);
    }

    public function brandName(): ?string
    {
        return $this->productBrand?->name ?? $this->brand;
    }

    public function categoryName(): ?string
    {
        return $this->productCategory?->name ?? $this->category?->name;
    }

    public function modelName(): ?string
    {
        return $this->productModel?->name ?? $this->model;
    }

    public function imageUrl(): string
    {
        if ($this->image_path) {
            return asset('storage/'.$this->image_path);
        }

        return asset('images/brand/logo_main.png');
    }
}
