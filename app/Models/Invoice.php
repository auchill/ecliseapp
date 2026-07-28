<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoiceable_type',
        'invoiceable_id',
        'customer_id',
        'type',
        'status',
        'currency',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'fee_amount',
        'total',
        'amount_paid',
        'refunded_amount',
        'balance_due',
        'issued_at',
        'due_at',
        'paid_at',
        'cancelled_at',
        'billing_snapshot',
        'shipping_snapshot',
        'terms_snapshot',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice): void {
            if (filled($invoice->invoice_number)) {
                return;
            }

            $year = $invoice->created_at?->format('Y') ?: now()->format('Y');

            $invoice->forceFill([
                'invoice_number' => sprintf('INV-%s-%07d', $year, $invoice->id),
            ])->saveQuietly();
        });
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'billing_snapshot' => 'array',
            'shipping_snapshot' => 'array',
            'terms_snapshot' => 'array',
        ];
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLabel(): string
    {
        return InvoiceStatus::tryFrom((string) $this->status)?->label()
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
