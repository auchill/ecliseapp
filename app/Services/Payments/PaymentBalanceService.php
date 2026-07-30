<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentBalanceService
{
    public function invoicePaidAmount(Invoice $invoice): float
    {
        return round((float) $invoice->payments()
            ->whereIn('status', $this->settledPaymentStatuses())
            ->sum('amount'), 2);
    }

    public function paymentRefundedAmount(Payment $payment): float
    {
        $ledgerRefunds = (float) $payment->refunds()
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount');

        return round(max($ledgerRefunds, (float) $payment->refunded_amount), 2);
    }

    public function invoiceRefundedAmount(Invoice $invoice): float
    {
        return round((float) $invoice->payments()
            ->with('refunds')
            ->get()
            ->sum(fn (Payment $payment): float => $this->paymentRefundedAmount($payment)), 2);
    }

    public function invoiceNetPaidAmount(Invoice $invoice): float
    {
        return round(max(0, $this->invoicePaidAmount($invoice) - $this->invoiceRefundedAmount($invoice)), 2);
    }

    public function invoiceBalanceDue(Invoice $invoice): float
    {
        return round(max(0, (float) $invoice->total - $this->invoiceNetPaidAmount($invoice)), 2);
    }

    public function refundableAmount(Payment $payment): float
    {
        return round(max(0, (float) $payment->amount - $this->paymentRefundedAmount($payment)), 2);
    }

    public function synchronizeInvoice(Invoice $invoice): Invoice
    {
        $paid = $this->invoiceNetPaidAmount($invoice);
        $refunded = $this->invoiceRefundedAmount($invoice);
        $total = (float) $invoice->total;
        $balance = $refunded >= $total && $total > 0
            ? 0.0
            : round(max(0, $total - $paid), 2);

        $invoice->forceFill([
            'amount_paid' => $paid,
            'refunded_amount' => $refunded,
            'balance_due' => $balance,
            'status' => $this->deriveInvoiceStatus($invoice, $paid, $refunded, $balance),
            'paid_at' => $balance <= 0.01 && (float) $invoice->total > 0
                ? ($invoice->paid_at ?: now())
                : null,
        ])->save();

        return $invoice->fresh();
    }

    private function deriveInvoiceStatus(Invoice $invoice, float $paid, float $refunded, float $balance): string
    {
        if (in_array($invoice->status, [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value], true)) {
            return $invoice->status;
        }

        $total = (float) $invoice->total;

        if ($total <= 0) {
            return InvoiceStatus::Draft->value;
        }

        if ($refunded >= $total && $paid <= 0.01) {
            return InvoiceStatus::Refunded->value;
        }

        if ($refunded > 0) {
            return InvoiceStatus::PartiallyRefunded->value;
        }

        if ($balance <= 0.01) {
            return InvoiceStatus::Paid->value;
        }

        if ($paid > 0) {
            return InvoiceStatus::PartiallyPaid->value;
        }

        return $invoice->issued_at ? InvoiceStatus::Issued->value : InvoiceStatus::Draft->value;
    }

    private function settledPaymentStatuses(): array
    {
        return array_merge(PaymentStatus::successfulValues(), [
            PaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value,
        ]);
    }
}
