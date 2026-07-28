<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'status',
        'payload',
        'error_message',
        'attempt_count',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'attempt_count' => (int) $this->attempt_count + 1,
            'error_message' => null,
        ])->save();
    }

    public function markProcessed(string $status = self::STATUS_PROCESSED): void
    {
        $this->forceFill([
            'status' => $status,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ])->save();
    }
}
