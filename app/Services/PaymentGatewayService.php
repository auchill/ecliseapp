<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentGatewayService
{
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

        if (! $secret) {
            return route('payments.show', $payment);
        }

        $payable = $payment->payable;

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'customer_email' => $payable->email ?? null,
                'success_url' => route('payments.stripe.success', $payment).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payments.cancel', $payment),
                'payment_method_types[0]' => 'card',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $payment->currency,
                'line_items[0][price_data][unit_amount]' => (int) round((float) $payment->amount * 100),
                'line_items[0][price_data][product_data][name]' => $this->paymentDescription($payment),
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[payable_type]' => class_basename($payment->payable_type),
                'metadata[payable_id]' => (string) $payment->payable_id,
            ]);

        if ($response->failed()) {
            Log::warning('Stripe checkout session creation failed', [
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
        $payment->update([
            'gateway_reference_id' => $payload['id'] ?? null,
            'stripe_checkout_session_id' => $payload['id'] ?? null,
            'status' => 'pending',
            'raw_response' => $payload,
        ]);

        return $payload['url'] ?? route('payments.show', $payment);
    }

    private function createPayPalOrder(Payment $payment): string
    {
        if (! config('services.paypal.client_id') || ! config('services.paypal.secret')) {
            return route('payments.show', $payment);
        }

        $response = Http::withToken($this->paypalAccessToken())
            ->acceptJson()
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
        $payable = $payment->payable;

        if (method_exists($payable, 'fulfillmentLabel')) {
            return class_basename($payable).' '.$payable->getKey().' '.$payable->fulfillmentLabel();
        }

        return class_basename($payment->payable_type).' '.$payment->payable_id;
    }
}
