<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\UnresolvedWebhookPaymentException;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\PaymentFinalizer;
use App\Services\PaymentGatewayService;
use App\Services\Payments\PaymentPayloadSanitizer;
use App\Services\Payments\StripeWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentWebhookController extends Controller
{
    /**
     * Stripe's documented replay tolerance for the Stripe-Signature timestamp.
     */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function stripe(Request $request, PaymentPayloadSanitizer $sanitizer, StripeWebhookProcessor $processor)
    {
        $payload = $request->getContent();

        if (! $this->validStripeSignature($payload, (string) $request->header('Stripe-Signature'))) {
            abort(400, 'Invalid Stripe signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            abort(400, 'Invalid Stripe webhook payload.');
        }

        $webhookEvent = $this->recordWebhookEvent(
            'stripe',
            (string) ($event['id'] ?? hash('sha256', $payload)),
            (string) ($event['type'] ?? 'unknown'),
            $event,
            $sanitizer,
        );

        if ($this->isTerminalWebhookEvent($webhookEvent)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $processor->process($webhookEvent, $event);
        } catch (UnresolvedWebhookPaymentException $exception) {
            Log::warning('Stripe webhook could not be matched to a local payment', [
                'event' => $event['id'] ?? null,
                'type' => $event['type'] ?? null,
            ]);

            // 422 keeps Stripe retrying, which is what resolves a webhook that outran the local commit.
            return response()->json(['received' => false, 'message' => 'Payment not found for this event.'], 422);
        } catch (Throwable $exception) {
            Log::warning('Stripe webhook processing failed', [
                'event' => $event['id'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['received' => false, 'message' => 'Webhook processing failed.'], 422);
        }

        return response()->json(['received' => true]);
    }

    public function paypal(
        Request $request,
        PaymentGatewayService $gateways,
        PaymentFinalizer $finalizer,
        PaymentPayloadSanitizer $sanitizer,
    ) {
        $payload = $request->json()->all();
        $headers = collect($request->headers->all())
            ->map(fn (array $value) => $value[0] ?? null)
            ->all();

        if (! $gateways->verifyPayPalWebhook($payload, $headers)) {
            abort(400, 'Invalid PayPal webhook signature.');
        }

        $webhookEvent = $this->recordWebhookEvent(
            'paypal',
            (string) ($payload['id'] ?? hash('sha256', json_encode($payload))),
            (string) ($payload['event_type'] ?? 'unknown'),
            $payload,
            $sanitizer,
        );

        if ($this->isTerminalWebhookEvent($webhookEvent)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $webhookEvent->markProcessing();

        $eventType = $payload['event_type'] ?? null;
        $resource = $payload['resource'] ?? [];
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['id']
            ?? null;

        $payment = $paypalOrderId
            ? Payment::query()->where('paypal_order_id', $paypalOrderId)->first()
            : null;

        if (! $payment) {
            Log::warning('PayPal webhook payment not found', ['event' => $payload['id'] ?? null, 'order' => $paypalOrderId]);
            $webhookEvent->markProcessed(PaymentWebhookEvent::STATUS_IGNORED);

            return response()->json(['received' => true]);
        }

        try {
            $handled = false;

            if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                $finalizer->markPaid($payment, [
                    'paypal_capture_id' => $resource['id'] ?? null,
                    'gateway_reference_id' => $resource['id'] ?? $payment->paypal_order_id,
                    'gateway_payment_id' => $resource['id'] ?? $payment->paypal_order_id,
                    'amount' => isset($resource['amount']['value']) ? (float) $resource['amount']['value'] : $payment->amount,
                    'currency' => strtolower($resource['amount']['currency_code'] ?? $payment->currency),
                    'raw_response' => $payload,
                    'paid_at' => now(),
                ]);
                $handled = true;
            }

            if (in_array($eventType, ['PAYMENT.CAPTURE.DENIED', 'CHECKOUT.ORDER.VOIDED'], true)) {
                $finalizer->markFailed($payment, $eventType === 'CHECKOUT.ORDER.VOIDED' ? 'cancelled' : 'failed', $payload);
                $handled = true;
            }

            $webhookEvent->markProcessed($handled ? PaymentWebhookEvent::STATUS_PROCESSED : PaymentWebhookEvent::STATUS_IGNORED);
        } catch (Throwable $exception) {
            $webhookEvent->markFailed($exception->getMessage());

            throw $exception;
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verifies a Stripe-Signature header the way Stripe documents it: a shared timestamp,
     * one or more v1 signatures (several are sent while a signing secret is being rotated),
     * and a replay tolerance on the timestamp.
     */
    private function validStripeSignature(string $payload, string $signatureHeader): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret || ! $signatureHeader) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && $timestamp === null) {
                $timestamp = $value;
            } elseif ($key === 'v1' && filled($value)) {
                $signatures[] = $value;
            }
        }

        if (! is_numeric($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            Log::warning('Stripe webhook rejected: signature timestamp outside tolerance.');

            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function recordWebhookEvent(
        string $provider,
        string $providerEventId,
        string $eventType,
        array $payload,
        PaymentPayloadSanitizer $sanitizer,
    ): PaymentWebhookEvent {
        return PaymentWebhookEvent::query()->firstOrCreate(
            [
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
            ],
            [
                'event_type' => $eventType,
                'status' => PaymentWebhookEvent::STATUS_RECEIVED,
                'payload' => $sanitizer->sanitize($payload),
                'received_at' => now(),
            ],
        );
    }

    private function isTerminalWebhookEvent(PaymentWebhookEvent $event): bool
    {
        return in_array($event->status, [
            PaymentWebhookEvent::STATUS_PROCESSED,
            PaymentWebhookEvent::STATUS_IGNORED,
        ], true);
    }
}
