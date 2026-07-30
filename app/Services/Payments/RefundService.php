<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundService
{
    public function __construct(
        private readonly PaymentBalanceService $balances,
        private readonly PaymentSettingsService $settings,
        private readonly PaymentAuditLogger $audit,
    ) {}

    public function request(Payment $payment, User $actor, array $data, ?string $sourceIp = null): PaymentRefund
    {
        return DB::transaction(function () use ($payment, $actor, $data, $sourceIp): PaymentRefund {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $amount = round((float) ($data['amount'] ?? 0), 2);

            $this->validateRefund($payment, $amount);

            $refund = $payment->refunds()->create([
                'amount' => $amount,
                'currency' => $payment->currency,
                'status' => RefundStatus::Pending->value,
                'reason_code' => $data['reason_code'] ?? null,
                'reason' => $data['reason'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
                'requested_method' => $data['requested_method'] ?? $payment->method,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'source_ip' => $sourceIp,
            ]);

            $this->audit->log('refund.requested', $refund, $actor, [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount' => $amount,
            ], $sourceIp);

            return $refund->fresh('payment');
        });
    }

    public function approve(PaymentRefund $refund, User $actor, ?string $sourceIp = null): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $actor, $sourceIp): PaymentRefund {
            $refund = PaymentRefund::query()->with('payment')->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== RefundStatus::Pending->value) {
                throw new InvalidArgumentException('Only pending refunds can be approved.');
            }

            if ((bool) $this->settings->get('refund_approval_required') && $refund->requested_by === $actor->id) {
                throw new InvalidArgumentException('Requester cannot approve their own refund.');
            }

            $refund->update([
                'status' => RefundStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->audit->log('refund.approved', $refund, $actor, [
                'payment_id' => $refund->payment_id,
                'refund_id' => $refund->id,
            ], $sourceIp);

            return $refund->fresh('payment');
        });
    }

    public function processManual(PaymentRefund $refund, User $actor, array $data, ?string $sourceIp = null): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $actor, $data, $sourceIp): PaymentRefund {
            $refund = PaymentRefund::query()->with('payment.invoice')->lockForUpdate()->findOrFail($refund->id);

            if (! in_array($refund->status, [RefundStatus::Pending->value, RefundStatus::Approved->value, RefundStatus::Processing->value], true)) {
                throw new InvalidArgumentException('This refund cannot be processed.');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $this->validateRefund($payment, (float) $refund->amount, $refund->id);

            $refund->update([
                'status' => RefundStatus::Succeeded->value,
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'refunded_at' => now(),
                'processed_method' => $data['processed_method'] ?? $refund->requested_method ?? $payment->method,
                'manual_reference' => $data['manual_reference'] ?? $refund->manual_reference,
                'internal_note' => $data['internal_note'] ?? $refund->internal_note,
            ]);

            $refundedAmount = round((float) $payment->refunded_amount + (float) $refund->amount, 2);
            $payment->update([
                'refunded_amount' => $refundedAmount,
                'status' => $refundedAmount + 0.01 >= (float) $payment->amount
                    ? PaymentStatus::Refunded->value
                    : PaymentStatus::PartiallyRefunded->value,
                'refunded_at' => now(),
            ]);

            $payment->transactions()->create([
                'transaction_type' => PaymentTransactionType::Refund->value,
                'status' => RefundStatus::Succeeded->value,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'provider_reference' => $refund->manual_reference,
                'processed_at' => now(),
            ]);

            if ($payment->invoice) {
                $this->balances->synchronizeInvoice($payment->invoice);
            }

            $this->audit->log('refund.processed', $refund, $actor, [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
            ], $sourceIp);

            return $refund->fresh('payment.invoice');
        });
    }

    private function validateRefund(Payment $payment, float $amount, ?int $excludingRefundId = null): void
    {
        if (! $payment->isPaid() && ! in_array($payment->status, [PaymentStatus::PartiallyRefunded->value], true)) {
            throw new InvalidArgumentException('Only successful payments can be refunded.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $pendingAmount = (float) $payment->refunds()
            ->when($excludingRefundId, fn ($query) => $query->whereKeyNot($excludingRefundId))
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Approved->value, RefundStatus::Processing->value])
            ->sum('amount');

        if ($amount + $pendingAmount > $this->balances->refundableAmount($payment) + 0.01) {
            throw new InvalidArgumentException('Refund amount exceeds the refundable balance.');
        }
    }
}
