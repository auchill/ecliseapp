<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case PendingVerification = 'pending_verification';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
    case Expired = 'expired';

    // Existing production rows and flows currently use "paid"; keep readable during migration.
    case LegacyPaid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Payment',
            self::PendingVerification => 'Awaiting Verification',
            self::Processing => 'Processing',
            self::Authorized => 'Authorized',
            self::Succeeded, self::LegacyPaid => 'Paid',
            self::Failed => 'Payment Failed',
            self::Cancelled => 'Cancelled',
            self::PartiallyRefunded => 'Partially Refunded',
            self::Refunded => 'Refunded',
            self::Disputed => 'Disputed',
            self::Expired => 'Expired',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Succeeded, self::LegacyPaid], true);
    }

    public static function successfulValues(): array
    {
        return [self::Succeeded->value, self::LegacyPaid->value];
    }
}
