<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';
    case FullPayment = 'full_payment';
    case DiagnosticFee = 'diagnostic_fee';
    case AdditionalCharge = 'additional_charge';
    case ShopOrder = 'shop_order';
    case Shipping = 'shipping';
    case Adjustment = 'adjustment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Balance => 'Balance',
            self::FullPayment => 'Full Payment',
            self::DiagnosticFee => 'Diagnostic Fee',
            self::AdditionalCharge => 'Additional Charge',
            self::ShopOrder => 'Shop Order',
            self::Shipping => 'Shipping',
            self::Adjustment => 'Adjustment',
            self::Refund => 'Refund',
        };
    }
}
