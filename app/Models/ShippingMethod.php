<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_cost',
        'delivery_days_min',
        'delivery_days_max',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_cost' => 'decimal:2',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function discountRules(): HasMany
    {
        return $this->hasMany(ShippingDiscountRule::class);
    }

    public function deliveryDaysLabel(): string
    {
        if ($this->delivery_days_min === $this->delivery_days_max) {
            return $this->delivery_days_min === 1
                ? '1 day'
                : $this->delivery_days_min.' days';
        }

        return $this->delivery_days_min.'-'.$this->delivery_days_max.' days';
    }
}
