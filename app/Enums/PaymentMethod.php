<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Interac = 'interac';
    case Cash = 'cash';
    case DebitTerminal = 'debit_terminal';
    case CreditTerminal = 'credit_terminal';
    case PayInStore = 'pay_in_store';
    case StoreCredit = 'store_credit';
    case GiftCard = 'gift_card';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Interac => 'Interac e-Transfer',
            self::Cash => 'Cash',
            self::DebitTerminal => 'Debit Terminal',
            self::CreditTerminal => 'Credit Terminal',
            self::PayInStore => 'Pay in Store',
            self::StoreCredit => 'Store Credit',
            self::GiftCard => 'Gift Card',
        };
    }
}
