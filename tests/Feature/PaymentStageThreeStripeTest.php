<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\Repair;
use App\Models\User;
use App\Services\PaymentGatewayService;
use App\Services\Payments\PaymentSettingsService;
use App\Services\Payments\RefundService;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config([
        'services.stripe.key' => null,
        'services.stripe.secret' => null,
        'services.stripe.webhook_secret' => null,
        'services.paypal.client_id' => null,
        'services.paypal.secret' => null,
    ]);
});

function stage3User(string $email, string $role = 'customer'): User
{
    return User::query()->create([
        'name' => 'Stage Three '.ucfirst($role),
        'email' => $email,
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

function stage3RepairPayment(array $overrides = []): Payment
{
    $user = stage3User('stage3-customer-'.str()->random(8).'@example.com');
    $customer = Customer::forUser($user);
    $repair = Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'STAGE3-REP-'.random_int(1000, 9999),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 15',
        'issue_category' => 'Display',
        'issue_description' => 'Display replacement',
        'subtotal' => 100,
        'tax_amount' => 13,
        'total_amount' => 113,
        'amount_paid' => 0,
        'balance_due' => 113,
        'status' => 'awaiting_customer_payment',
        'repair_status' => 'awaiting_customer_payment',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
        'repair_total' => 113,
        'currency' => 'cad',
    ]);
    $invoice = Invoice::query()->create([
        'customer_id' => $customer->id,
        'invoiceable_type' => Repair::class,
        'invoiceable_id' => $repair->id,
        'type' => 'repair_final',
        'status' => 'issued',
        'currency' => 'cad',
        'subtotal' => 100,
        'tax_amount' => 13,
        'total' => 113,
        'balance_due' => 113,
        'issued_at' => now(),
    ]);

    return $repair->payments()->create(array_merge([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'repair_id' => $repair->id,
        'source' => 'repair',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'purpose' => 'balance',
        'amount' => 113,
        'currency' => 'cad',
        'status' => 'pending',
    ], $overrides));
}

function stage3StripeHeaders(array $event, string $secret): array
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return [
        $payload,
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ],
    ];
}

test('money helper converts cad amounts to stripe minor units safely', function () {
    expect(Money::toMinorUnits('0.01'))->toBe(1)
        ->and(Money::toMinorUnits('10.10'))->toBe(1010)
        ->and(Money::toMinorUnits('99.99'))->toBe(9999)
        ->and(Money::toMinorUnits('100'))->toBe(10000)
        ->and(Money::toMinorUnits('1,250.45'))->toBe(125045)
        ->and(Money::fromMinorUnits(11300))->toBe(113.0);
});

test('customer stripe option is hidden until stripe is fully ready', function () {
    $settings = app(PaymentSettingsService::class);

    expect($settings->paymentMethodOptions(customerFacing: true))->not->toHaveKey('stripe')
        ->and($settings->stripeReadiness()['status'])->toBe('incomplete');

    config([
        'services.stripe.key' => 'pk_test_stage3',
        'services.stripe.secret' => 'sk_test_stage3',
        'services.stripe.webhook_secret' => 'whsec_stage3',
    ]);

    expect($settings->paymentMethodOptions(customerFacing: true))->toHaveKey('stripe')
        ->and($settings->stripeReadiness()['status'])->toBe('ready');
});

test('stripe checkout uses cents idempotency and safe metadata', function () {
    config([
        'services.stripe.key' => 'pk_test_stage3',
        'services.stripe.secret' => 'sk_test_stage3',
        'services.stripe.webhook_secret' => 'whsec_stage3',
    ]);
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_stage3_checkout',
            'url' => 'https://checkout.stripe.test/stage3',
        ]),
    ]);

    $payment = stage3RepairPayment(['amount' => '10.10']);
    $url = app(PaymentGatewayService::class)->createCheckout($payment);

    expect($url)->toBe('https://checkout.stripe.test/stage3')
        ->and($payment->fresh()->stripe_checkout_session_id)->toBe('cs_stage3_checkout')
        // The key is derived from the payload and the session being replaced, so it varies
        // rather than being fixed to the payment id.
        ->and($payment->fresh()->idempotency_key)->toStartWith('stripe-checkout-payment-'.$payment->id.'-');

    Http::assertSent(function ($request) use ($payment): bool {
        return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->hasHeader('Idempotency-Key', $payment->fresh()->idempotency_key)
            && $request['line_items[0][price_data][unit_amount]'] === 1010
            && $request['line_items[0][price_data][currency]'] === 'cad'
            && $request['metadata[payment_id]'] === (string) $payment->id
            && $request['metadata[payment_number]'] === $payment->payment_number;
    });
});

test('stripe webhook rejects invalid signatures before storing events', function () {
    config(['services.stripe.webhook_secret' => 'whsec_stage3']);
    $payment = stage3RepairPayment(['stripe_checkout_session_id' => 'cs_stage3_invalid']);

    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad',
    ], json_encode([
        'id' => 'evt_stage3_invalid',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_stage3_invalid']],
    ]))->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending')
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

test('stripe webhook fails safely when amount does not match local payment', function () {
    config(['services.stripe.webhook_secret' => 'whsec_stage3']);
    $payment = stage3RepairPayment(['stripe_checkout_session_id' => 'cs_stage3_amount']);
    $event = [
        'id' => 'evt_stage3_amount',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_stage3_amount',
                'payment_intent' => 'pi_stage3_amount',
                'amount_total' => 11200,
                'currency' => 'cad',
                'metadata' => ['payment_id' => $payment->id],
            ],
        ],
    ];
    [$payload, $headers] = stage3StripeHeaders($event, 'whsec_stage3');

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)
        ->assertStatus(422);

    $webhook = PaymentWebhookEvent::query()->firstOrFail();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($webhook->status)->toBe(PaymentWebhookEvent::STATUS_FAILED)
        ->and($webhook->error_message)->toContain('amount');
});

test('admin can retry a failed stored stripe webhook event', function () {
    $admin = stage3User('stage3-webhook-admin@example.com', 'admin');
    $payment = stage3RepairPayment(['stripe_checkout_session_id' => 'cs_stage3_retry']);
    $event = [
        'id' => 'evt_stage3_retry',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_stage3_retry',
                'payment_intent' => 'pi_stage3_retry',
                'amount_total' => 11300,
                'currency' => 'cad',
                'metadata' => ['payment_id' => $payment->id],
            ],
        ],
    ];
    $webhook = PaymentWebhookEvent::query()->create([
        'provider' => 'stripe',
        'provider_event_id' => 'evt_stage3_retry',
        'event_type' => 'checkout.session.completed',
        'status' => PaymentWebhookEvent::STATUS_FAILED,
        'payload' => $event,
        'attempt_count' => 1,
        'received_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.payment-webhooks.retry', $webhook))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($webhook->fresh()->status)->toBe(PaymentWebhookEvent::STATUS_PROCESSED)
        ->and($webhook->fresh()->attempt_count)->toBe(2)
        ->and(PaymentTransaction::query()->where('transaction_type', 'payment')->count())->toBe(1);
});

test('stripe refund processing waits for stripe success before updating payment balance', function () {
    config([
        'services.stripe.key' => 'pk_test_stage3',
        'services.stripe.secret' => 'sk_test_stage3',
        'services.stripe.webhook_secret' => 'whsec_stage3',
    ]);
    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_stage3_refund',
            'status' => 'succeeded',
            'payment_intent' => 'pi_stage3_refund',
            'amount' => 2000,
            'currency' => 'cad',
        ]),
    ]);

    $requester = stage3User('stage3-refund-requester@example.com', 'admin');
    $approver = stage3User('stage3-refund-approver@example.com', 'admin');
    $payment = stage3RepairPayment([
        'status' => 'paid',
        'paid_at' => now(),
        'gateway_payment_id' => 'pi_stage3_refund',
        'stripe_payment_intent_id' => 'pi_stage3_refund',
    ]);

    $refund = app(RefundService::class)->request($payment, $requester, [
        'amount' => 20,
        'reason' => 'Customer adjustment',
        'requested_method' => 'stripe',
    ]);
    app(RefundService::class)->approve($refund, $approver);
    $processed = app(RefundService::class)->process($refund->fresh(), $approver, []);

    expect($processed->status)->toBe('succeeded')
        ->and($processed->provider_refund_id)->toBe('re_stage3_refund')
        ->and($payment->fresh()->status)->toBe('partially_refunded')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(20.0)
        ->and(PaymentTransaction::query()->where('transaction_type', 'refund')->where('status', 'succeeded')->exists())->toBeTrue();

    Http::assertSent(function ($request) use ($refund): bool {
        return $request->url() === 'https://api.stripe.com/v1/refunds'
            && $request->hasHeader('Idempotency-Key', 'stripe-refund-'.$refund->refund_number)
            && $request['payment_intent'] === 'pi_stage3_refund'
            && $request['amount'] === 2000;
    });
});

test('stripe reconciliation dry run compares configured stripe payment intents', function () {
    config(['services.stripe.secret' => 'sk_test_stage3']);
    Http::fake([
        'https://api.stripe.com/v1/payment_intents/pi_stage3_reconcile*' => Http::response([
            'id' => 'pi_stage3_reconcile',
            'status' => 'succeeded',
            'amount_received' => 11300,
            'currency' => 'cad',
        ]),
    ]);
    stage3RepairPayment([
        'status' => 'paid',
        'paid_at' => now(),
        'gateway_payment_id' => 'pi_stage3_reconcile',
        'stripe_payment_intent_id' => 'pi_stage3_reconcile',
    ]);

    $before = PaymentAuditLog::query()->count();

    Artisan::call('eclise:reconcile-payments', [
        '--provider' => 'stripe',
        '--dry-run' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['dry_run'])->toBeTrue()
        ->and($payload['provider_checks']['stripe']['status'])->toBe('completed')
        ->and($payload['provider_checks']['stripe']['records_checked'])->toBe(1)
        ->and($payload['provider_checks']['stripe']['discrepancy_count'])->toBe(0)
        ->and(PaymentAuditLog::query()->count())->toBe($before);
});
