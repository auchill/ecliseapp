<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'refund_number',
        'amount',
        'currency',
        'status',
        'provider_refund_id',
        'provider_reference',
        'manual_reference',
        'reason_code',
        'reason',
        'internal_note',
        'requested_method',
        'processed_method',
        'requested_by',
        'approved_by',
        'processed_by',
        'source_ip',
        'requested_at',
        'approved_at',
        'processed_at',
        'refunded_at',
        'failure_message',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::created(function (PaymentRefund $refund): void {
            if (filled($refund->refund_number)) {
                return;
            }

            $year = $refund->created_at?->format('Y') ?: now()->format('Y');

            $refund->forceFill([
                'refund_number' => sprintf('REF-%s-%07d', $year, $refund->id),
            ])->saveQuietly();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
