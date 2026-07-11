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
        'customer_name',
        'email',
        'phone',
        'address',
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
        'shipping_full_name',
        'shipping_phone',
        'shipping_email',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
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

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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

        return array_values(array_filter([
            $this->shipping_full_name,
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            trim(implode(', ', array_filter([$this->shipping_city, $this->shipping_province, $this->shipping_postal_code]))),
            $this->shipping_country,
            $this->shipping_phone ? 'Phone: '.$this->shipping_phone : null,
            $this->shipping_email ? 'Email: '.$this->shipping_email : null,
        ]));
    }

    public function getCustomerNameAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->full_name;
    }

    public function getEmailAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->email;
    }

    public function getPhoneAttribute(?string $value): ?string
    {
        return $value ?? $this->customer?->phone;
    }
}
