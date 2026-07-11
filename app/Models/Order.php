<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Pending',
        'Paid',
        'Processing',
        'Ready for Pickup',
        'Shipped',
        'Delivered',
        'Completed',
        'Cancelled',
        'Refunded',
    ];

    public const FULFILLMENT_METHODS = [
        'pickup' => 'Store Pickup',
        'shipping' => 'Shipping',
    ];

    protected $fillable = [
        'customer_id',
        'order_number',
        'subtotal',
        'tax',
        'total',
        'status',
        'payment_provider',
        'payment_gateway',
        'payment_reference',
        'fulfillment_method',
        'payment_status',
        'payment_amount',
        'currency',
        'paid_at',
        'inventory_committed_at',
        'shipping_method_id',
        'shipping_method_name',
        'shipping_delivery_days',
        'shipping_base_cost',
        'shipping_discount_amount',
        'shipping_cost',
        'delivery_carrier',
        'tracking_number',
        'tracking_notes',
        'admin_notes',
        'customer_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_base_cost' => 'decimal:2',
            'shipping_discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'inventory_committed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(OrderStatusUpdate::class)->latest();
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment()
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function publicStatusUpdates(): HasMany
    {
        return $this->hasMany(OrderStatusUpdate::class)
            ->where('is_customer_visible', true)
            ->oldest();
    }

    public function isShipping(): bool
    {
        return $this->fulfillment_method === 'shipping';
    }

    public function fulfillmentLabel(): string
    {
        return self::FULFILLMENT_METHODS[$this->fulfillment_method] ?? 'Store Pickup';
    }

    public function shippingAddressLines(): array
    {
        if (! $this->isShipping()) {
            return [];
        }

        if ($this->shipping?->shipping_address) {
            return preg_split('/\R/', $this->shipping->shipping_address) ?: [];
        }

        return [];
    }
}
