<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSentrixDevice extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_devices';

    protected $fillable = [
        'device_manufacturer_id',
        'device_model_id',
        'device_color_id',
        'device_condition_id',
        'device_carrier_id',
        'device_size_id',
        'device_grade_id',
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

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(DeviceManufacturer::class, 'device_manufacturer_id');
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(DeviceColor::class, 'device_color_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(DeviceCondition::class, 'device_condition_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(DeviceCarrier::class, 'device_carrier_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(DeviceSize::class, 'device_size_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(DeviceGrade::class, 'device_grade_id');
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
        return $this->image_url ?: asset('images/brand/logo_main.png');
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
