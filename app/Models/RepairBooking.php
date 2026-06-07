<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairBooking extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Submitted',
        'Appointment Confirmed',
        'Device Received',
        'Diagnosis in Progress',
        'Waiting for Parts',
        'Repair in Progress',
        'Ready for Pickup',
        'Shipped',
        'Delivered',
        'Completed',
        'Cancelled',
    ];

    public const FULFILLMENT_METHODS = [
        'pickup' => 'Store Pickup',
        'shipping' => 'Shipping',
    ];

    protected $fillable = [
        'user_id',
        'tracking_number',
        'customer_name',
        'email',
        'phone',
        'device_type',
        'device_brand',
        'device_model',
        'issue_category',
        'issue_description',
        'preferred_appointment_date',
        'preferred_appointment_time',
        'device_image_path',
        'terms_accepted',
        'status',
        'estimated_completion_date',
        'internal_notes',
        'customer_notes',
        'fulfillment_method',
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
        'repair_total',
        'delivery_carrier',
        'delivery_tracking_number',
        'tracking_notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_appointment_date' => 'date',
            'estimated_completion_date' => 'date',
            'terms_accepted' => 'boolean',
            'shipping_base_cost' => 'decimal:2',
            'shipping_discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'repair_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(RepairStatusUpdate::class)->latest();
    }

    public function publicStatusUpdates(): HasMany
    {
        return $this->hasMany(RepairStatusUpdate::class)
            ->where('is_customer_visible', true)
            ->oldest();
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function deviceLabel(): string
    {
        return trim("{$this->device_brand} {$this->device_model}") ?: $this->device_type;
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
}
