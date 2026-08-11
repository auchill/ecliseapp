<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Payments\PaymentBalanceService;
use App\Services\Payments\PaymentPayloadSanitizer;
use App\Services\Payments\PaymentSettingsService;
use App\Services\Payments\StripeApiClient;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentGatewayService
{
    public function __construct(
        private readonly PaymentBalanceService $balances,
        private readonly PaymentPayloadSanitizer $payloadSanitizer,
        private readonly PaymentSettingsService $settings,
        private readonly StripeApiClient $stripe,
    ) {}

    public function createCheckout(Payment $payment): string
    {
        if ((float) $payment->amount <= 0) {
            return route('payments.show', $payment);
        }

        return match ($payment->gateway) {
            'stripe' => $this->createStripeCheckout($payment),
            'paypal' => $this->createPayPalOrder($payment),
            default => route('payments.show', $payment),
        };
    }

    public function capturePayPalOrder(Payment $payment): array
    {
        $accessToken = $this->paypalAccessToken();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->post($this->paypalBaseUrl().'/v2/checkout/orders/'.$payment->paypal_order_id.'/capture');

        if ($response->failed()) {
            Log::warning('PayPal capture failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Unable to capture PayPal payment.');
        }

        return $response->json();
    }

    public function verifyPayPalWebhook(array $payload, array $headers): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId || ! config('services.paypal.client_id') || ! config('services.paypal.secret')) {
            return false;
        }

        $accessToken = $this->paypalAccessToken();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'] ?? null,
                'cert_url' => $headers['paypal-cert-url'] ?? null,
                'transmission_id' => $headers['paypal-transmission-id'] ?? null,
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
                'transmission_time' => $headers['paypal-transmission-time'] ?? null,
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ]);

        return $response->ok() && $response->json('verification_status') === 'SUCCESS';
    }

    private function createStripeCheckout(Payment $payment): string
    {
        $secret = config('services.stripe.secret');

        if (! $secret || ! $this->settings->stripeReadyForCustomerCheckout()) {
            Log::warning('Stripe checkout requested while Stripe is not ready', [
                'payment_id' => $payment->id,
                'readiness_status' => $this->settings->stripeReadiness()['status'],
            ]);

            return route('payments.show', $payment);
        }

        try {
            $amountInCents = $this->validatedStripeAmountInCents($payment);
        } catch (RuntimeException $exception) {
            Log::warning('Stripe checkout validation failed', [
                'payment_id' => $payment->id,
                'message' => $exception->getMessage(),
            ]);

            $payment->update([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ]);

            return route('payments.show', $payment);
        }

        if ($reusableUrl = $this->reusableCheckoutUrl($payment)) {
            return $reusableUrl;
        }

        if (blank($payment->idempotency_key)) {
            $payment->forceFill([
                'idempotency_key' => 'stripe-checkout-payment-'.$payment->id,
            ])->save();
        }

        $payable = $payment->payable;
        $customerEmail = data_get($payment->checkout_data, 'customer.email')
            ?: $payable?->customer?->email;

        $response = $this->stripe
            ->form(['Idempotency-Key' => $payment->idempotency_key])
            ->post($this->stripe->url('checkout/sessions'), [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'customer_email' => $customerEmail,
                'success_url' => route('payments.stripe.success', $payment).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payments.cancel', $payment),
                'payment_method_types[0]' => 'card',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower((string) $payment->currency),
                'line_items[0][price_data][unit_amount]' => $amountInCents,
                'line_items[0][price_data][product_data][name]' => $this->paymentDescription($payment),
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[payment_number]' => (string) $payment->payment_number,
                'metadata[invoice_id]' => (string) $payment->invoice_id,
                'metadata[invoice_number]' => (string) $payment->invoice?->invoice_number,
                'metadata[purpose]' => (string) $payment->purpose,
                'metadata[payable_type]' => class_basename($payment->payable_type),
                'metadata[payable_id]' => (string) $payment->payable_id,
                // Stripe does not copy session metadata onto the PaymentIntent, and
                // payment_intent.succeeded can arrive before checkout.session.completed has
                // stored the intent id locally. Without this, that event has nothing to resolve
                // against and fails on every payment.
                'payment_intent_data[metadata][payment_id]' => (string) $payment->id,
                'payment_intent_data[metadata][payment_number]' => (string) $payment->payment_number,
                'payment_intent_data[metadata][invoice_id]' => (string) $payment->invoice_id,
            ]);

        $responsePayload = $response->json() ?: [
            'status' => $response->status(),
            'body' => Str::limit($response->body(), 1000),
        ];

        if ($response->failed()) {
            Log::warning('Stripe checkout session creation failed', [
                'payment_id' => $payment->id,
                'response' => $this->payloadSanitizer->sanitize($responsePayload),
            ]);

            $payment->update([
                'status' => 'failed',
                'failure_code' => data_get($responsePayload, 'error.code'),
                'failure_message' => data_get($responsePayload, 'error.message'),
                'raw_response' => $this->payloadSanitizer->sanitize($responsePayload),
            ]);

            return route('payments.show', $payment);
        }

        $payment->update([
            'gateway_reference_id' => $responsePayload['id'] ?? null,
            'stripe_checkout_session_id' => $responsePayload['id'] ?? null,
            'status' => 'pending',
            'raw_response' => $this->payloadSanitizer->sanitize($responsePayload),
        ]);

        return $responsePayload['url'] ?? route('payments.show', $payment);
    }

    private function createPayPalOrder(Payment $payment): string
    {
        if (! config('services.paypal.client_id') || ! config('services.paypal.secret')) {
            return route('payments.show', $payment);
        }

        $response = Http::withToken($this->paypalAccessToken())
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->post($this->paypalBaseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    'description' => Str::limit($this->paymentDescription($payment), 120, ''),
                    'amount' => [
                        'currency_code' => strtoupper($payment->currency),
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'landing_page' => 'NO_PREFERENCE',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('payments.paypal.return', $payment),
                    'cancel_url' => route('payments.cancel', $payment),
                ],
            ]);

        if ($response->failed()) {
            Log::warning('PayPal order creation failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);

            $payment->update([
                'status' => 'failed',
                'raw_response' => $response->json(),
            ]);

            return route('payments.show', $payment);
        }

        $payload = $response->json();
        $approvalLink = collect($payload['links'] ?? [])->firstWhere('rel', 'approve');
        $approvalUrl = $approvalLink['href'] ?? null;

        $payment->update([
            'gateway_reference_id' => $payload['id'] ?? null,
            'paypal_order_id' => $payload['id'] ?? null,
            'status' => 'pending',
            'raw_response' => $payload,
        ]);

        return $approvalUrl ?: route('payments.show', $payment);
    }

    private function paypalAccessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
            ->timeout(20)
            ->connectTimeout(10)
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to authenticate with PayPal.');
        }

        return $response->json('access_token');
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function paymentDescription(Payment $payment): string
    {
        if ($payment->source === 'shop' && $payment->checkout_data) {
            return 'Eclise shop checkout';
        }

        $payable = $payment->payable;

        if (method_exists($payable, 'fulfillmentLabel')) {
            return class_basename($payable).' '.$payable->getKey().' '.$payable->fulfillmentLabel();
        }

        return class_basename($payment->payable_type).' '.$payment->payable_id;
    }

    /**
     * Returns a Checkout Session URL that Stripe will still accept, or null to create a new one.
     *
     * A stored URL is never enough on its own: sessions expire after 24 hours and are consumed
     * once paid. Handing a customer a completed or expired session drops them on Stripe's
     * "You're all done here" dead end, so every candidate is confirmed open with Stripe first.
     */
    private function reusableCheckoutUrl(Payment $payment): ?string
    {
        $ownUrl = data_get($payment->raw_response, 'url');

        if ($payment->stripe_checkout_session_id && filled($ownUrl)
            && $this->checkoutSessionIsOpen($payment->stripe_checkout_session_id)) {
            return $ownUrl;
        }

        return $this->openSiblingCheckoutUrl($payment);
    }

    /**
     * Two tabs against one invoice must not become two live Checkout Sessions. When an equivalent
     * Stripe attempt for the same invoice is still open, send the customer back to it.
     */
    private function openSiblingCheckoutUrl(Payment $payment): ?string
    {
        if (! $payment->invoice_id) {
            return null;
        }

        $sibling = Payment::query()
            ->where('invoice_id', $payment->invoice_id)
            ->whereKeyNot($payment->id)
            ->where('gateway', 'stripe')
            ->where('status', 'pending')
            ->whereNotNull('stripe_checkout_session_id')
            ->whereRaw('CAST(amount AS DECIMAL(12,2)) = ?', [number_format((float) $payment->amount, 2, '.', '')])
            ->latest('id')
            ->first();

        $url = data_get($sibling?->raw_response, 'url');

        if (blank($url) || ! $this->checkoutSessionIsOpen($sibling->stripe_checkout_session_id)) {
            return null;
        }

        Log::info('Reusing an open Stripe checkout session for the same invoice', [
            'payment_id' => $payment->id,
            'reused_payment_id' => $sibling->id,
            'invoice_id' => $payment->invoice_id,
        ]);

        return $url;
    }

    /**
     * Only an "open" session can still be paid. Complete, expired, or unreadable sessions are
     * treated as unusable so the caller creates a fresh one.
     */
    private function checkoutSessionIsOpen(?string $sessionId): bool
    {
        if (blank($sessionId)) {
            return false;
        }

        $response = $this->stripe->request()
            ->acceptJson()
            ->get($this->stripe->url('checkout/sessions/'.$sessionId));

        if ($response->failed()) {
            Log::warning('Stripe checkout session lookup failed; creating a new session', [
                'session_id' => $sessionId,
            ]);

            return false;
        }

        return $response->json('status') === 'open';
    }

    private function validatedStripeAmountInCents(Payment $payment): int
    {
        $payment->loadMissing('invoice');

        if (strtolower((string) $payment->currency) !== 'cad') {
            throw new RuntimeException('Stripe checkout currently supports CAD payments only.');
        }

        $amountInCents = Money::toMinorUnits($payment->amount);

        if ($amountInCents <= 0) {
            throw new RuntimeException('Stripe checkout amount must be greater than zero.');
        }

        if ($payment->invoice) {
            $balanceDue = $this->balances->invoiceBalanceDue($payment->invoice->fresh());

            if ($balanceDue <= 0) {
                throw new RuntimeException('The linked invoice has no balance due.');
            }

            if ((float) $payment->amount > $balanceDue + 0.01) {
                throw new RuntimeException('Stripe checkout amount exceeds the linked invoice balance.');
            }
        }

        return $amountInCents;
    }
}
