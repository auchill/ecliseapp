<?php

namespace App\Enums;

enum PaymentTransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Sale = 'sale';
    case Payment = 'payment';
    case Failure = 'failure';
    case Void = 'void';
    case Refund = 'refund';
    case Chargeback = 'chargeback';
    case ManualConfirmation = 'manual_confirmation';
    case ManualRejection = 'manual_rejection';
    case Reconciliation = 'reconciliation';
}
