<?php

namespace App\Services\Payments;

use App\Models\Payment;

class ReceiptService
{
    public function ensureReceiptNumber(Payment $payment): Payment
    {
        if (! $payment->isPaid() || filled($payment->receipt_number)) {
            return $payment;
        }

        $year = ($payment->paid_at ?: $payment->created_at ?: now())->format('Y');

        $payment->forceFill([
            'receipt_number' => sprintf('RCT-%s-%07d', $year, $payment->id),
        ])->saveQuietly();

        return $payment->fresh();
    }
}
