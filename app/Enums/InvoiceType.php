<?php

namespace App\Enums;

enum InvoiceType: string
{
    case RepairEstimate = 'repair_estimate';
    case RepairDeposit = 'repair_deposit';
    case RepairFinal = 'repair_final';
    case RepairAdditionalCharge = 'repair_additional_charge';
    case ShopOrder = 'shop_order';
    case CreditNote = 'credit_note';
}
