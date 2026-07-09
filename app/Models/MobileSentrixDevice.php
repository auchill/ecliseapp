<?php

namespace App\Models;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileSentrixDevice extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_devices';

    protected $fillable = [
        'entity_id',
        'sku',
        'name',
        'url',
        'url_key',
        'manufacturer_text',
        'device_model_text',
        'device_color_text',
        'condition_text',
        'device_carrier_text',
        'device_size_text',
        'device_grade_text',
        'available_qty',
        'qty',
        'price',
        'regular_price',
        'final_price',
        'cost',
        'status',
        'product_type',
        'image_url',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'available_qty' => 'integer',
            'qty' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('available_qty', '>', 0)
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('available_qty')->where('qty', '>', 0);
                });
        });
    }

    public function availableQuantity(): int
    {
        return max(0, (int) ($this->available_qty ?? $this->qty ?? 0));
    }

    public function displayPrice(): ?float
    {
        $price = $this->price ?? $this->final_price ?? $this->regular_price;

        return $price === null ? null : (float) $price;
    }

    public function cartProductId(): string
    {
        return (string) ($this->entity_id ?: $this->sku ?: $this->id);
    }

    public function imageUrl(): string
    {
        return CatalogImage::remoteUrl($this->image_url);
    }

    public function getDisplayImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    public function displayName(): string
    {
        return $this->name ?: trim(implode(' ', array_filter([
            $this->manufacturer_text,
            $this->device_model_text,
            $this->device_size_text,
            $this->device_color_text,
            $this->condition_text,
        ]))) ?: 'Certified Pre-Owned Device';
    }
}
