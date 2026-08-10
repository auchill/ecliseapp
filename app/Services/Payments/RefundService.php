<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundService
{
    public function __construct(
        private readonly PaymentBalanceService $balances,
        private readonly PaymentSettingsService $settings,
        private readonly PaymentAuditLogger $audit,
        private readonly PaymentPayloadSanitizer $payloadSanitizer,
        private readonly StripeApiClient $stripe,
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

    public function process(PaymentRefund $refund, User $actor, array $data, ?string $sourceIp = null): PaymentRefund
    {
        $refund->loadMissing('payment');

        if ($refund->payment?->provider === 'stripe') {
            return $this->processStripe($refund, $actor, $data, $sourceIp);
        }

        return $this->processManual($refund, $actor, $data, $sourceIp);
    }

    public function processStripe(PaymentRefund $refund, User $actor, array $data, ?string $sourceIp = null): PaymentRefund
    {
        if (! $this->settings->stripeReadyForCustomerCheckout()) {
            throw new InvalidArgumentException('Stripe is not fully configured for refunds.');
        }

        $refund = DB::transaction(function () use ($refund, $actor, $data, $sourceIp): PaymentRefund {
            $refund = PaymentRefund::query()->with('payment.invoice')->lockForUpdate()->findOrFail($refund->id);

            if (! in_array($refund->status, [RefundStatus::Pending->value, RefundStatus::Approved->value, RefundStatus::Processing->value], true)) {
                throw new InvalidArgumentException('This refund cannot be processed.');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $this->validateRefund($payment, (float) $refund->amount, $refund->id);

            $providerPaymentId = $payment->stripe_payment_intent_id ?: $payment->gateway_payment_id;

            if (blank($providerPaymentId)) {
                throw new InvalidArgumentException('Stripe payment intent is missing for this refund.');
            }

            $refund->update([
                'status' => RefundStatus::Processing->value,
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'processed_method' => 'stripe',
                'internal_note' => $data['internal_note'] ?? $refund->internal_note,
                'metadata' => array_merge($refund->metadata ?? [], [
                    'provider_payment_id' => $providerPaymentId,
                ]),
            ]);

            $this->audit->log('refund.processing', $refund, $actor, [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'provider' => 'stripe',
            ], $sourceIp);

            return $refund->fresh('payment.invoice');
        });

        $payment = $refund->payment;
        $providerPaymentId = $payment->stripe_payment_intent_id ?: $payment->gateway_payment_id;
        $idempotencyKey = 'stripe-refund-'.$refund->refund_number;

        $response = $this->stripe
            ->form(['Idempotency-Key' => $idempotencyKey])
            ->post($this->stripe->url('refunds'), [
                'payment_intent' => $providerPaymentId,
                'amount' => Money::toMinorUnits($refund->amount),
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[payment_number]' => (string) $payment->payment_number,
                'metadata[refund_id]' => (string) $refund->id,
                'metadata[refund_number]' => (string) $refund->refund_number,
            ]);

        $payload = $response->json() ?: [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ];

        if ($response->failed()) {
            $this->markStripeRefundFailed($refund, $actor, $payload, $sourceIp);

            throw new InvalidArgumentException(data_get($payload, 'error.message') ?: 'Stripe refund failed.');
        }

        return $this->applyStripeRefundResult($refund, $actor, $payload, $sourceIp);
    }

    private function applyStripeRefundResult(PaymentRefund $refund, User $actor, array $payload, ?string $sourceIp = null): PaymentRefund
    {
        $failed = false;

        $processedRefund = DB::transaction(function () use ($refund, $actor, $payload, $sourceIp, &$failed): PaymentRefund {
            $refund = PaymentRefund::query()->with('payment.invoice')->lockForUpdate()->findOrFail($refund->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $status = $this->mapStripeRefundStatus((string) ($payload['status'] ?? ''));
            $sanitizedPayload = $this->payloadSanitizer->sanitize($payload);

            if ($status === RefundStatus::Failed->value) {
                $failed = true;

                $refund->update([
                    'status' => RefundStatus::Failed->value,
                    'provider_refund_id' => $payload['id'] ?? $refund->provider_refund_id,
                    'provider_reference' => $payload['id'] ?? $refund->provider_reference,
                    'failure_message' => data_get($payload, 'failure_reason') ?: data_get($payload, 'error.message'),
                    'metadata' => $sanitizedPayload,
                ]);

                $this->recordRefundTransaction($payment, $refund, RefundStatus::Failed->value, $sanitizedPayload);
                $this->audit->log('refund.failed', $refund, $actor, [
                    'payment_id' => $payment->id,
                    'refund_id' => $refund->id,
                    'provider' => 'stripe',
                ], $sourceIp);

                return $refund->fresh('payment.invoice');
            }

            $refund->update([
                'status' => $status,
                'provider_refund_id' => $payload['id'] ?? $refund->provider_refund_id,
                'provider_reference' => $payload['id'] ?? $refund->provider_reference,
                'refunded_at' => $status === RefundStatus::Succeeded->value ? now() : $refund->refunded_at,
                'failure_message' => null,
                'metadata' => $sanitizedPayload,
            ]);

            $this->recordRefundTransaction($payment, $refund->fresh(), $status, $sanitizedPayload);

            if ($status === RefundStatus::Succeeded->value) {
                $refundedAmount = round((float) $payment->refunded_amount + (float) $refund->amount, 2);
                $payment->update([
                    'refunded_amount' => $refundedAmount,
                    'status' => $refundedAmount + 0.01 >= (float) $payment->amount
                        ? PaymentStatus::Refunded->value
                        : PaymentStatus::PartiallyRefunded->value,
                    'refunded_at' => now(),
                ]);

                if ($payment->invoice) {
                    $this->balances->synchronizeInvoice($payment->invoice);
                }
            }

            $this->audit->log($status === RefundStatus::Succeeded->value ? 'refund.processed' : 'refund.processing', $refund->fresh(), $actor, [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
                'provider' => 'stripe',
            ], $sourceIp);

            return $refund->fresh('payment.invoice');
        });

        if ($failed) {
            throw new InvalidArgumentException('Stripe refund failed.');
        }

        return $processedRefund;
    }

    private function markStripeRefundFailed(PaymentRefund $refund, User $actor, array $payload, ?string $sourceIp = null): void
    {
        DB::transaction(function () use ($refund, $actor, $payload, $sourceIp): void {
            $refund = PaymentRefund::query()->with('payment')->lockForUpdate()->findOrFail($refund->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $sanitizedPayload = $this->payloadSanitizer->sanitize($payload);

            $refund->update([
                'status' => RefundStatus::Failed->value,
                'provider_refund_id' => $payload['id'] ?? $refund->provider_refund_id,
                'provider_reference' => $payload['id'] ?? $refund->provider_reference,
                'failure_message' => data_get($payload, 'error.message') ?: data_get($payload, 'failure_reason') ?: 'Stripe refund failed.',
                'metadata' => $sanitizedPayload,
            ]);

            $this->recordRefundTransaction($payment, $refund, RefundStatus::Failed->value, $sanitizedPayload);
            $this->audit->log('refund.failed', $refund, $actor, [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'provider' => 'stripe',
            ], $sourceIp);
        });
    }

    private function recordRefundTransaction(Payment $payment, PaymentRefund $refund, string $status, array $payload): void
    {
        $payment->transactions()->create([
            'transaction_type' => PaymentTransactionType::Refund->value,
            'status' => $status,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'provider_transaction_id' => $refund->provider_refund_id,
            'provider_reference' => $refund->provider_reference,
            'response_payload' => $payload,
            'failure_message' => $status === RefundStatus::Failed->value ? $refund->failure_message : null,
            'processed_at' => now(),
        ]);
    }

    private function mapStripeRefundStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => RefundStatus::Succeeded->value,
            'pending', 'requires_action' => RefundStatus::Processing->value,
            default => RefundStatus::Failed->value,
        };
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
