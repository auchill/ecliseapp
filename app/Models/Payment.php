<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    public const GATEWAYS = [
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially refunded',
    ];

    protected $fillable = [
        'payable_type',
        'payable_id',
        'order_id',
        'repair_order_id',
        'gateway',
        'gateway_reference_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paypal_order_id',
        'paypal_capture_id',
        'amount',
        'currency',
        'status',
        'raw_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairBooking::class, 'repair_order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function gatewayLabel(): string
    {
        return self::GATEWAYS[$this->gateway] ?? ucfirst($this->gateway);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
