<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentFinalizer;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, PaymentFinalizer $finalizer)
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        if (! $this->validStripeSignature($payload, (string) $request->header('Stripe-Signature'))) {
            abort(400, 'Invalid Stripe signature.');
        }

        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];
        $sessionId = $object['id'] ?? null;
        $payment = $sessionId ? Payment::query()->where('stripe_checkout_session_id', $sessionId)->first() : null;

        if (! $payment && isset($object['metadata']['payment_id'])) {
            $payment = Payment::query()->find($object['metadata']['payment_id']);
        }

        if (! $payment) {
            Log::warning('Stripe webhook payment not found', ['event' => $event['id'] ?? null, 'session' => $sessionId]);

            return response()->json(['received' => true]);
        }

        if ($type === 'checkout.session.completed') {
            $finalizer->markPaid($payment, [
                'stripe_payment_intent_id' => $object['payment_intent'] ?? null,
                'gateway_reference_id' => $object['payment_intent'] ?? $sessionId,
                'amount' => isset($object['amount_total']) ? round($object['amount_total'] / 100, 2) : $payment->amount,
                'currency' => strtolower($object['currency'] ?? $payment->currency),
                'raw_response' => $event,
                'paid_at' => now(),
            ]);
        }

        if (in_array($type, ['checkout.session.expired', 'payment_intent.payment_failed'], true)) {
            $finalizer->markFailed($payment, $type === 'checkout.session.expired' ? 'cancelled' : 'failed', $event);
        }

        return response()->json(['received' => true]);
    }

    public function paypal(Request $request, PaymentGatewayService $gateways, PaymentFinalizer $finalizer)
    {
        $payload = $request->json()->all();
        $headers = collect($request->headers->all())
            ->map(fn (array $value) => $value[0] ?? null)
            ->all();

        if (! $gateways->verifyPayPalWebhook($payload, $headers)) {
            abort(400, 'Invalid PayPal webhook signature.');
        }

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

            return response()->json(['received' => true]);
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $finalizer->markPaid($payment, [
                'paypal_capture_id' => $resource['id'] ?? null,
                'gateway_reference_id' => $resource['id'] ?? $payment->paypal_order_id,
                'amount' => isset($resource['amount']['value']) ? (float) $resource['amount']['value'] : $payment->amount,
                'currency' => strtolower($resource['amount']['currency_code'] ?? $payment->currency),
                'raw_response' => $payload,
                'paid_at' => now(),
            ]);
        }

        if (in_array($eventType, ['PAYMENT.CAPTURE.DENIED', 'CHECKOUT.ORDER.VOIDED'], true)) {
            $finalizer->markFailed($payment, $eventType === 'CHECKOUT.ORDER.VOIDED' ? 'cancelled' : 'failed', $payload);
        }

        return response()->json(['received' => true]);
    }

    private function validStripeSignature(string $payload, string $signatureHeader): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret || ! $signatureHeader) {
            return false;
        }

        parse_str(str_replace(',', '&', $signatureHeader), $parts);
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (! $timestamp || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }
}
