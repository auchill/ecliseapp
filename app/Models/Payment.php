<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class Payment extends Model
{
    use HasFactory;

    public const GATEWAYS = [
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'pending_verification' => 'Awaiting verification',
        'processing' => 'Processing',
        'authorized' => 'Authorized',
        'succeeded' => 'Paid',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially refunded',
    ];

    public const SOURCES = [
        'repair' => 'Repair',
        'shop' => 'Shop',
    ];

    protected $fillable = [
        'payment_number',
        'receipt_number',
        'payable_type',
        'payable_id',
        'invoice_id',
        'customer_id',
        'order_id',
        'repair_id',
        'source',
        'purpose',
        'method',
        'provider',
        'checkout_data',
        'gateway',
        'gateway_reference_id',
        'gateway_payment_id',
        'gateway_reference',
        'manual_reference',
        'proof_path',
        'proof_original_name',
        'proof_mime_type',
        'proof_size',
        'gateway_customer_id',
        'gateway_payment_method_id',
        'idempotency_key',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paypal_order_id',
        'paypal_capture_id',
        'amount',
        'refunded_amount',
        'currency',
        'subtotal',
        'tax_amount',
        'fee_amount',
        'discount_amount',
        'status',
        'raw_response',
        'paid_at',
        'authorized_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
        'received_by',
        'verified_by',
        'verified_at',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'created_by',
        'source_ip',
        'failure_code',
        'failure_message',
        'admin_note',
        'customer_note',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->method ??= $payment->gateway;
            $payment->provider ??= in_array($payment->gateway, ['stripe', 'paypal'], true)
                ? $payment->gateway
                : 'manual';
            $payment->purpose ??= $payment->source === 'repair' ? 'balance' : 'shop_order';
            $payment->gateway_payment_id ??= $payment->stripe_payment_intent_id
                ?: $payment->paypal_capture_id
                ?: $payment->paypal_order_id
                ?: $payment->gateway_reference_id;
            $payment->gateway_reference ??= $payment->gateway_reference_id;
        });

        static::created(function (Payment $payment): void {
            if (filled($payment->payment_number)) {
                return;
            }

            $year = $payment->created_at?->format('Y') ?: now()->format('Y');

            $payment->forceFill([
                'payment_number' => sprintf('PAY-%s-%07d', $year, $payment->id),
            ])->saveQuietly();
        });

        static::saving(function (Payment $payment): void {
            if ($payment->source && ! array_key_exists($payment->source, self::SOURCES)) {
                throw new InvalidArgumentException('Invalid payment source.');
            }

            if ($payment->status && ! PaymentStatus::tryFrom((string) $payment->status)) {
                throw new InvalidArgumentException('Invalid payment status.');
            }

            if ($payment->method && ! PaymentMethod::tryFrom((string) $payment->method)) {
                throw new InvalidArgumentException('Invalid payment method.');
            }

            if ($payment->provider && ! PaymentProvider::tryFrom((string) $payment->provider)) {
                throw new InvalidArgumentException('Invalid payment provider.');
            }

            if ($payment->purpose && ! PaymentPurpose::tryFrom((string) $payment->purpose)) {
                throw new InvalidArgumentException('Invalid payment purpose.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'raw_response' => 'array',
            'checkout_data' => 'array',
            'metadata' => 'array',
            'proof_size' => 'integer',
            'paid_at' => 'datetime',
            'authorized_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PaymentAuditLog::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(Repair::class, 'repair_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, PaymentStatus::successfulValues(), true);
    }

    public function hasSettledFunds(): bool
    {
        return in_array($this->status, array_merge(PaymentStatus::successfulValues(), [
            PaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value,
        ]), true);
    }

    public function canRequestRefund(): bool
    {
        return in_array($this->status, array_merge(PaymentStatus::successfulValues(), [
            PaymentStatus::PartiallyRefunded->value,
        ]), true) && (float) $this->refunded_amount + 0.01 < (float) $this->amount;
    }

    public function gatewayLabel(): string
    {
        if (blank($this->gateway)) {
            return ucfirst(str_replace('_', ' ', (string) ($this->provider ?: $this->method ?: 'manual')));
        }

        return self::GATEWAYS[$this->gateway] ?? ucfirst((string) $this->gateway);
    }

    public function statusLabel(): string
    {
        return PaymentStatus::tryFrom((string) $this->status)?->label()
            ?? self::STATUSES[$this->status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }

    public function receiptNumber(): string
    {
        return $this->receipt_number ?: sprintf('RCT-%s-%07d', ($this->paid_at ?: $this->created_at ?: now())->format('Y'), $this->id);
    }
}
