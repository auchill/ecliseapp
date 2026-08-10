<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Exceptions\Payments\UnresolvedWebhookPaymentException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Services\PaymentFinalizer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StripeWebhookProcessor
{
    /**
     * The exact Stripe events Eclise processes. Anything else is acknowledged and ignored.
     */
    public const HANDLED_EVENT_TYPES = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'charge.refunded',
        'refund.updated',
        'charge.dispute.created',
        'charge.dispute.closed',
    ];

    /**
     * Stripe Checkout session payment states that represent settled funds.
     */
    private const SETTLED_SESSION_PAYMENT_STATUSES = ['paid', 'no_payment_required'];

    public function __construct(
        private readonly PaymentFinalizer $finalizer,
        private readonly PaymentPayloadSanitizer $payloadSanitizer,
        private readonly PaymentBalanceService $balances,
        private readonly PaymentAuditLogger $audit,
    ) {}

    public function process(PaymentWebhookEvent $webhookEvent, array $event): bool
    {
        $webhookEvent->markProcessing();

        try {
            $handled = $this->processEvent($event);
            $webhookEvent->markProcessed($handled ? PaymentWebhookEvent::STATUS_PROCESSED : PaymentWebhookEvent::STATUS_IGNORED);

            return $handled;
        } catch (Throwable $exception) {
            $webhookEvent->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    private function processEvent(array $event): bool
    {
        $type = (string) ($event['type'] ?? 'unknown');

        if (! in_array($type, self::HANDLED_EVENT_TYPES, true)) {
            return false;
        }

        $object = (array) data_get($event, 'data.object', []);
        $payment = $this->resolvePayment($object);

        if (! $payment) {
            // A handled event we cannot map is left failed so Stripe retries (which resolves the
            // race where the webhook outruns the local commit) and an admin can retry it manually.
            throw new UnresolvedWebhookPaymentException(
                'No local payment matches this Stripe '.$type.' event.',
            );
        }

        return match (true) {
            in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) => $this->handleCheckoutSession($payment, $object, $event),
            $type === 'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($payment, $object, $event),
            $type === 'checkout.session.expired' => $this->handleFailure($payment, 'expired', $event),
            in_array($type, ['checkout.session.async_payment_failed', 'payment_intent.payment_failed'], true) => $this->handleFailure($payment, 'failed', $event),
            in_array($type, ['charge.dispute.created', 'charge.dispute.closed'], true) => $this->recordDispute($payment, $type, $object, $event),
            default => $this->syncRefunds($payment, $type, $object, $event),
        };
    }

    private function handleCheckoutSession(Payment $payment, array $object, array $event): bool
    {
        $this->assertCheckoutSessionMatchesPayment($payment, $object);

        $sessionPaymentStatus = (string) ($object['payment_status'] ?? 'paid');

        if (! in_array($sessionPaymentStatus, self::SETTLED_SESSION_PAYMENT_STATUSES, true)) {
            // Async methods can complete the session while funds are still in flight. Record the
            // provider references but never mark the payment paid until Stripe confirms funding.
            $payment->forceFill(array_filter([
                'stripe_checkout_session_id' => $object['id'] ?? $payment->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $object['payment_intent'] ?? $payment->stripe_payment_intent_id,
                'status' => PaymentStatus::Processing->value,
            ]))->save();

            return true;
        }

        $this->finalizer->markPaid($payment, [
            'stripe_checkout_session_id' => $object['id'] ?? $payment->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $object['payment_intent'] ?? $payment->stripe_payment_intent_id,
            'gateway_reference_id' => $object['payment_intent'] ?? $object['id'] ?? $payment->gateway_reference_id,
            'gateway_payment_id' => $object['payment_intent'] ?? $object['id'] ?? $payment->gateway_payment_id,
            'amount' => isset($object['amount_total']) ? Money::fromMinorUnits($object['amount_total']) : $payment->amount,
            'currency' => strtolower((string) ($object['currency'] ?? $payment->currency)),
            'raw_response' => $event,
            'paid_at' => now(),
        ]);

        return true;
    }

    /**
     * payment_intent.succeeded can arrive alongside checkout.session.completed for one payment.
     * PaymentFinalizer::markPaid is the single finalization boundary and is idempotent, so a
     * second event never produces a second financial effect.
     */
    private function handlePaymentIntentSucceeded(Payment $payment, array $object, array $event): bool
    {
        $this->assertPaymentIntentMatchesPayment($payment, $object);

        $this->finalizer->markPaid($payment, [
            'stripe_payment_intent_id' => $object['id'] ?? $payment->stripe_payment_intent_id,
            'gateway_reference_id' => $object['id'] ?? $payment->gateway_reference_id,
            'gateway_payment_id' => $object['id'] ?? $payment->gateway_payment_id,
            'amount' => isset($object['amount_received']) ? Money::fromMinorUnits($object['amount_received']) : $payment->amount,
            'currency' => strtolower((string) ($object['currency'] ?? $payment->currency)),
            'raw_response' => $event,
            'paid_at' => now(),
        ]);

        return true;
    }

    private function handleFailure(Payment $payment, string $status, array $event): bool
    {
        // A late failure event must never reverse funds that Stripe already confirmed.
        if ($payment->hasSettledFunds()) {
            return true;
        }

        $this->finalizer->markFailed($payment, $status, $event);

        return true;
    }

    private function resolvePayment(array $object): ?Payment
    {
        $objectId = (string) ($object['id'] ?? '');
        $sessionId = str_starts_with($objectId, 'cs_') ? $objectId : '';
        $paymentIntentId = (string) ($object['payment_intent'] ?? (str_starts_with($objectId, 'pi_') ? $objectId : ''));

        $payment = null;

        if ($sessionId !== '') {
            $payment = Payment::query()->where('stripe_checkout_session_id', $sessionId)->first();
        }

        if (! $payment && $paymentIntentId !== '') {
            $payment = Payment::query()
                ->where(fn ($query) => $query
                    ->where('stripe_payment_intent_id', $paymentIntentId)
                    ->orWhere('gateway_payment_id', $paymentIntentId))
                ->first();
        }

        if (! $payment && filled(data_get($object, 'metadata.payment_id'))) {
            $payment = Payment::query()
                ->where('provider', 'stripe')
                ->find(data_get($object, 'metadata.payment_id'));
        }

        return $payment;
    }

    private function assertCheckoutSessionMatchesPayment(Payment $payment, array $object): void
    {
        $sessionId = (string) ($object['id'] ?? '');
        $paymentIntentId = (string) ($object['payment_intent'] ?? '');

        if ($payment->stripe_checkout_session_id && $sessionId !== '' && $payment->stripe_checkout_session_id !== $sessionId) {
            throw new RuntimeException('Stripe checkout session does not match the local payment.');
        }

        if ($payment->stripe_payment_intent_id && $paymentIntentId !== '' && $payment->stripe_payment_intent_id !== $paymentIntentId) {
            throw new RuntimeException('Stripe payment intent does not match the local payment.');
        }

        $this->assertAmountAndCurrencyMatch($payment, $object['amount_total'] ?? null, $object['currency'] ?? null);
    }

    private function assertPaymentIntentMatchesPayment(Payment $payment, array $object): void
    {
        $paymentIntentId = (string) ($object['id'] ?? '');

        if ($payment->stripe_payment_intent_id && $paymentIntentId !== '' && $payment->stripe_payment_intent_id !== $paymentIntentId) {
            throw new RuntimeException('Stripe payment intent does not match the local payment.');
        }

        $this->assertAmountAndCurrencyMatch($payment, $object['amount_received'] ?? $object['amount'] ?? null, $object['currency'] ?? null);
    }

    private function assertAmountAndCurrencyMatch(Payment $payment, mixed $amountMinor, mixed $currency): void
    {
        if ($amountMinor !== null && Money::toMinorUnits($payment->amount) !== (int) $amountMinor) {
            throw new RuntimeException(sprintf(
                'Stripe webhook amount does not match the local payment. Expected %d, provider reported %d.',
                Money::toMinorUnits($payment->amount),
                (int) $amountMinor,
            ));
        }

        if ($currency !== null && strtolower((string) $currency) !== strtolower((string) $payment->currency)) {
            throw new RuntimeException(sprintf(
                'Stripe webhook currency does not match the local payment. Expected %s, provider reported %s.',
                strtolower((string) $payment->currency),
                strtolower((string) $currency),
            ));
        }
    }

    /**
     * A dispute is surfaced for admin investigation only. It never reverses a settled payment,
     * because doing so would reopen the invoice balance and unblock a second charge.
     */
    private function recordDispute(Payment $payment, string $type, array $object, array $event): bool
    {
        $sanitized = (array) $this->payloadSanitizer->sanitize($event);
        $disputeId = (string) ($object['id'] ?? '');

        $alreadyRecorded = $payment->transactions()
            ->where('transaction_type', PaymentTransactionType::Chargeback->value)
            ->where('provider_transaction_id', $disputeId)
            ->where('status', (string) ($object['status'] ?? $type))
            ->exists();

        if ($alreadyRecorded) {
            return true;
        }

        $payment->transactions()->create([
            'transaction_type' => PaymentTransactionType::Chargeback->value,
            'status' => (string) ($object['status'] ?? $type),
            'amount' => isset($object['amount']) ? Money::fromMinorUnits($object['amount']) : $payment->amount,
            'currency' => strtolower((string) ($object['currency'] ?? $payment->currency)),
            'provider_transaction_id' => $disputeId,
            'provider_reference' => (string) ($object['charge'] ?? $payment->gateway_reference),
            'response_payload' => $sanitized,
            'failure_message' => (string) ($object['reason'] ?? null) ?: null,
            'processed_at' => now(),
        ]);

        $this->audit->log('payment.dispute.'.($type === 'charge.dispute.created' ? 'opened' : 'closed'), $payment, null, [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'dispute_id' => $disputeId,
            'dispute_status' => $object['status'] ?? null,
        ]);

        return true;
    }

    /**
     * Stripe no longer embeds the refund list on the charge object by default, so charge.refunded
     * is best-effort and refund.updated is the authoritative refund signal.
     */
    private function syncRefunds(Payment $payment, string $type, array $object, array $event): bool
    {
        $refundObjects = $type === 'refund.updated'
            ? [$object]
            : (array) data_get($object, 'refunds.data', []);

        $handled = false;

        foreach ($refundObjects as $refundObject) {
            $handled = $this->syncRefund($payment, (array) $refundObject, $event) || $handled;
        }

        return $handled;
    }

    private function syncRefund(Payment $payment, array $refundObject, array $event): bool
    {
        $providerRefundId = (string) ($refundObject['id'] ?? '');

        if ($providerRefundId === '') {
            return false;
        }

        $status = $this->mapRefundStatus((string) ($refundObject['status'] ?? ''));

        if (! $status) {
            return false;
        }

        return DB::transaction(function () use ($payment, $refundObject, $providerRefundId, $status, $event): bool {
            $refund = PaymentRefund::query()
                ->where('payment_id', $payment->id)
                ->where(fn ($query) => $query
                    ->where('provider_refund_id', $providerRefundId)
                    ->orWhere('provider_reference', $providerRefundId))
                ->lockForUpdate()
                ->first();

            if (! $refund) {
                return false;
            }

            if ($refund->status === $status) {
                return true;
            }

            $refund->update([
                'status' => $status,
                'provider_refund_id' => $providerRefundId,
                'provider_reference' => $providerRefundId,
                'refunded_at' => $status === 'succeeded' ? now() : $refund->refunded_at,
                'failure_message' => $status === 'failed' ? data_get($refundObject, 'failure_reason') : null,
                'metadata' => $this->payloadSanitizer->sanitize($event),
            ]);

            if ($status !== 'succeeded') {
                return true;
            }

            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $refundedAmount = $this->balances->paymentRefundedAmount($lockedPayment->fresh('refunds'));

            $lockedPayment->update([
                'refunded_amount' => $refundedAmount,
                'status' => $refundedAmount + 0.01 >= (float) $lockedPayment->amount
                    ? PaymentStatus::Refunded->value
                    : PaymentStatus::PartiallyRefunded->value,
                'refunded_at' => now(),
            ]);

            if ($lockedPayment->invoice) {
                $this->balances->synchronizeInvoice($lockedPayment->invoice);
            }

            return true;
        });
    }

    private function mapRefundStatus(string $status): ?string
    {
        return match ($status) {
            'succeeded' => 'succeeded',
            'pending', 'requires_action' => 'processing',
            'failed', 'canceled' => 'failed',
            default => null,
        };
    }
}
