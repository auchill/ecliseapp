<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Manual = 'manual';
    case Terminal = 'terminal';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Manual => 'Manual',
            self::Terminal => 'Terminal',
        };
    }
}
