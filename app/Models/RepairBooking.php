<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RepairBooking extends Model
{
    use HasFactory;

    public const STATUSES = [
        'booking_created',
        'awaiting_customer_payment',
        'awaiting_device',
        'device_received',
        'diagnosis_in_progress',
        'waiting_for_parts',
        'repair_in_progress',
        'ready_for_pickup',
        'shipped',
        'completed',
        'cancelled',
    ];

    public const FULFILLMENT_METHODS = [
        'pickup' => 'Store Pickup',
        'shipping' => 'Shipping',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
    ];

    public const STATUS_LABELS = [
        'booking_created' => 'Booking created',
        'awaiting_customer_payment' => 'Awaiting customer payment',
        'awaiting_device' => 'Awaiting device',
        'device_received' => 'Device received',
        'diagnosis_in_progress' => 'Diagnosis in progress',
        'waiting_for_parts' => 'Waiting for parts',
        'repair_in_progress' => 'Repair in progress',
        'ready_for_pickup' => 'Ready for pickup',
        'shipped' => 'Shipped',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'user_id',
        'quote_id',
        'tracking_number',
        'customer_name',
        'email',
        'phone',
        'device_type',
        'device_type_id',
        'device_brand',
        'product_brand_id',
        'device_model',
        'product_model_id',
        'issue_category',
        'issue_category_id',
        'issue_description',
        'preferred_appointment_date',
        'preferred_appointment_time',
        'device_image_path',
        'repair_items',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'terms_accepted',
        'status',
        'payment_status',
        'repair_status',
        'payment_gateway',
        'payment_amount',
        'currency',
        'paid_at',
        'estimated_completion_date',
        'internal_notes',
        'customer_notes',
        'customer_remark',
        'fulfillment_method',
        'pickup_or_shipping_option',
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
            'repair_items' => 'array',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'paid_at' => 'datetime',
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function deviceBrand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_model_id');
    }

    public function issueCategory(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class);
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

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment()
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function deviceLabel(): string
    {
        return trim(implode(' ', array_filter([
            $this->deviceBrand?->name ?? $this->device_brand,
            $this->deviceModelName(),
        ]))) ?: ($this->deviceType?->name ?? $this->device_type);
    }

    public function isShipping(): bool
    {
        return $this->fulfillment_method === 'shipping';
    }

    public function fulfillmentLabel(): string
    {
        return self::FULFILLMENT_METHODS[$this->pickup_or_shipping_option ?: $this->fulfillment_method] ?? self::FULFILLMENT_METHODS[$this->fulfillment_method] ?? 'Store Pickup';
    }

    public function deviceTypeName(): ?string
    {
        return $this->deviceType?->name ?? $this->device_type;
    }

    public function deviceBrandName(): ?string
    {
        return $this->deviceBrand?->name ?? $this->device_brand;
    }

    public function deviceModelName(): ?string
    {
        return $this->deviceModel?->name ?? $this->device_model;
    }

    public function issueCategoryName(): ?string
    {
        return $this->issueCategory?->name ?? $this->issue_category;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->repair_status ?: $this->status] ?? self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst(str_replace('_', ' ', (string) $this->payment_status));
    }

    public function partsTotal(): float
    {
        return collect($this->repair_items ?? [])
            ->where('type', 'part')
            ->sum(fn (array $item): float => (float) ($item['total'] ?? 0));
    }

    public function minimumPaymentAmount(): float
    {
        $total = (float) ($this->total_amount ?: $this->repair_total);
        $partsTotal = $this->partsTotal();

        if ($total <= 0) {
            return 0.00;
        }

        if ($partsTotal <= 0) {
            return round($total * 0.5, 2);
        }

        return round($partsTotal + (0.5 * max(0, $total - $partsTotal)), 2);
    }

    public function minimumPaymentDue(): float
    {
        return round(max(0, $this->minimumPaymentAmount() - (float) $this->amount_paid), 2);
    }

    public function currentBalanceDue(): float
    {
        return round(max(0, (float) ($this->total_amount ?: $this->repair_total) - (float) $this->amount_paid), 2);
    }

    public function canCustomerPay(): bool
    {
        return ! in_array($this->repair_status ?: $this->status, ['cancelled', 'completed'], true)
            && $this->payment_status !== 'paid'
            && $this->currentBalanceDue() > 0;
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
