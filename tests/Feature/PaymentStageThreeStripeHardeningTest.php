<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\Repair;
use App\Models\User;
use App\Services\PaymentGatewayService;
use App\Services\Payments\PaymentSettingsService;
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
        'services.paypal.webhook_id' => null,
    ]);
});

function hardeningUser(string $email, string $role = 'customer'): User
{
    return User::query()->create([
        'name' => 'Hardening '.ucfirst($role),
        'email' => $email,
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

/**
 * @return array{0: Repair, 1: Payment, 2: Customer}
 */
function hardeningRepairPayment(array $overrides = [], float $total = 113.0): array
{
    $user = hardeningUser('hardening-customer-'.str()->random(10).'@example.com');
    $customer = Customer::forUser($user);
    $repair = Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'HARD-REP-'.str()->random(6),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 15',
        'issue_category' => 'Display',
        'issue_description' => 'Display replacement',
        'subtotal' => round($total / 1.13, 2),
        'tax_amount' => round($total - ($total / 1.13), 2),
        'total_amount' => $total,
        'amount_paid' => 0,
        'balance_due' => $total,
        'status' => 'awaiting_customer_payment',
        'repair_status' => 'awaiting_customer_payment',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
        'repair_total' => $total,
        'currency' => 'cad',
    ]);
    $invoice = Invoice::query()->create([
        'customer_id' => $customer->id,
        'invoiceable_type' => Repair::class,
        'invoiceable_id' => $repair->id,
        'type' => 'repair_final',
        'status' => 'issued',
        'currency' => 'cad',
        'subtotal' => round($total / 1.13, 2),
        'tax_amount' => round($total - ($total / 1.13), 2),
        'total' => $total,
        'balance_due' => $total,
        'issued_at' => now(),
    ]);

    $payment = $repair->payments()->create(array_merge([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'repair_id' => $repair->id,
        'source' => 'repair',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'purpose' => 'balance',
        'amount' => $total,
        'currency' => 'cad',
        'status' => 'pending',
    ], $overrides));

    return [$repair->fresh(), $payment, $customer];
}

function hardeningSignedRequest(array $event, string $secret = 'whsec_hardening', ?int $timestamp = null, int $signatureCount = 1): array
{
    $payload = json_encode($event);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    // Stripe sends several v1 signatures while a signing secret is being rotated.
    $header = "t={$timestamp}";

    for ($i = 1; $i < $signatureCount; $i++) {
        $header .= ',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_rotated_'.$i);
    }

    $header .= ",v1={$signature}";

    return [$payload, [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $header,
    ]];
}

function hardeningCheckoutEvent(Payment $payment, array $objectOverrides = [], string $eventId = 'evt_hardening', string $type = 'checkout.session.completed'): array
{
    return [
        'id' => $eventId,
        'type' => $type,
        'data' => [
            'object' => array_merge([
                'id' => $payment->stripe_checkout_session_id ?: 'cs_hardening',
                'payment_intent' => 'pi_hardening',
                'payment_status' => 'paid',
                'amount_total' => Money::toMinorUnits($payment->amount),
                'currency' => 'cad',
                'metadata' => ['payment_id' => $payment->id],
            ], $objectOverrides),
        ],
    ];
}

// ---------------------------------------------------------------------------
// Money conversion
// ---------------------------------------------------------------------------

test('money conversion is exact for every documented cad stage 3 amount', function () {
    $cases = [
        '1.00' => 100,
        '0.01' => 1,
        '10.10' => 1010,
        '99.99' => 9999,
        '100.00' => 10000,
        '999.95' => 99995,
        '1,250.00' => 125000,
    ];

    foreach ($cases as $amount => $minor) {
        expect(Money::toMinorUnits($amount))->toBe($minor)
            ->and(Money::fromMinorUnits($minor))->toBe(round((float) str_replace(',', '', $amount), 2));
    }
});

// ---------------------------------------------------------------------------
// Readiness
// ---------------------------------------------------------------------------

test('stripe readiness rejects a malformed publishable key', function () {
    config([
        'services.stripe.key' => 'ppk_test_typo',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);

    $readiness = app(PaymentSettingsService::class)->stripeReadiness();

    expect($readiness['status'])->toBe('incomplete')
        ->and($readiness['missing'])->toContain('STRIPE_KEY')
        ->and(app(PaymentSettingsService::class)->paymentMethodOptions(customerFacing: true))->not->toHaveKey('stripe');
});

test('stripe readiness rejects mixed test and live credentials', function () {
    config([
        'services.stripe.key' => 'pk_live_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);

    $readiness = app(PaymentSettingsService::class)->stripeReadiness();

    expect($readiness['status'])->toBe('incomplete')
        ->and($readiness['missing'])->toContain('consistent key mode');
});

test('missing paypal configuration never blocks stripe readiness', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
        'services.paypal.webhook_id' => null,
    ]);

    $settings = app(PaymentSettingsService::class);

    expect($settings->stripeReadiness()['status'])->toBe('ready')
        ->and($settings->stripeReadiness()['mode'])->toBe('test')
        ->and($settings->paypalReadiness()['status'])->toBe('deferred')
        ->and($settings->paymentMethodOptions(customerFacing: true))->toHaveKey('stripe')
        ->and($settings->paymentMethodOptions(customerFacing: true))->not->toHaveKey('paypal');
});

test('stripe api calls pin the configured stripe api version', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
        'services.stripe.api_version' => '2024-06-20',
    ]);
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_version', 'url' => 'https://checkout.stripe.test/version']),
    ]);

    [, $payment] = hardeningRepairPayment();
    app(PaymentGatewayService::class)->createCheckout($payment);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Stripe-Version', '2024-06-20'));
});

// ---------------------------------------------------------------------------
// Webhook signature verification
// ---------------------------------------------------------------------------

test('stripe webhook rejects a signature outside the replay tolerance', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_stale']);

    [$payload, $headers] = hardeningSignedRequest(
        hardeningCheckoutEvent($payment, ['id' => 'cs_stale'], 'evt_stale'),
        timestamp: time() - 3600,
    );

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending')
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

test('stripe webhook accepts a payload signed during secret rotation', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_rotation']);

    [$payload, $headers] = hardeningSignedRequest(
        hardeningCheckoutEvent($payment, ['id' => 'cs_rotation'], 'evt_rotation'),
        signatureCount: 3,
    );

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($payment->fresh()->status)->toBe('paid');
});

// ---------------------------------------------------------------------------
// Event processing
// ---------------------------------------------------------------------------

test('a completed checkout session with unpaid funds is never marked paid', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [$repair, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_async']);

    [$payload, $headers] = hardeningSignedRequest(hardeningCheckoutEvent($payment, [
        'id' => 'cs_async',
        'payment_status' => 'unpaid',
    ], 'evt_async'));

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($payment->fresh()->status)->toBe('processing')
        ->and($payment->fresh()->paid_at)->toBeNull()
        ->and($repair->fresh()->payment_status)->toBe('unpaid')
        ->and($payment->fresh()->invoice->balance_due)->toEqual(113.00)
        ->and(PaymentTransaction::query()->count())->toBe(0);
});

test('a later payment intent succeeded event does not finalize the payment twice', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_double']);

    [$payload, $headers] = hardeningSignedRequest(hardeningCheckoutEvent($payment, ['id' => 'cs_double'], 'evt_double_session'));
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_double_intent',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_hardening',
            'amount_received' => Money::toMinorUnits($payment->amount),
            'currency' => 'cad',
            'metadata' => ['payment_id' => $payment->id],
        ]],
    ]);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($payment->fresh()->status)->toBe('paid')
        ->and(PaymentTransaction::query()->where('transaction_type', 'payment')->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->count())->toBe(2);
});

test('duplicate delivery of one stripe event produces one financial effect', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_dupe']);

    $event = hardeningCheckoutEvent($payment, ['id' => 'cs_dupe'], 'evt_dupe');

    [$payload, $headers] = hardeningSignedRequest($event);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    [$payload, $headers] = hardeningSignedRequest($event);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    expect(PaymentTransaction::query()->where('transaction_type', 'payment')->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($payment->fresh()->invoice->balance_due)->toEqual(0.00);
});

test('a currency mismatch is recorded for investigation and never finalized', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_currency']);

    [$payload, $headers] = hardeningSignedRequest(hardeningCheckoutEvent($payment, [
        'id' => 'cs_currency',
        'currency' => 'usd',
    ], 'evt_currency'));

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertStatus(422);

    $webhook = PaymentWebhookEvent::query()->firstOrFail();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($webhook->status)->toBe(PaymentWebhookEvent::STATUS_FAILED)
        ->and($webhook->error_message)->toContain('currency')
        ->and($payment->fresh()->invoice->balance_due)->toEqual(113.00);
});

test('an unmatched stripe event is kept failed so it can be retried', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_orphan',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_orphan', 'payment_status' => 'paid', 'amount_total' => 500, 'currency' => 'cad']],
    ]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertStatus(422);

    $webhook = PaymentWebhookEvent::query()->firstOrFail();

    expect($webhook->status)->toBe(PaymentWebhookEvent::STATUS_FAILED)
        ->and($webhook->error_message)->toContain('No local payment');
});

test('an unsupported stripe event is acknowledged and ignored', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_unsupported',
        'type' => 'customer.created',
        'data' => ['object' => ['id' => 'cus_hardening']],
    ]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect(PaymentWebhookEvent::query()->firstOrFail()->status)->toBe(PaymentWebhookEvent::STATUS_IGNORED);
});

test('a late failure event never reverses funds stripe already confirmed', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [$repair, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_late']);

    [$payload, $headers] = hardeningSignedRequest(hardeningCheckoutEvent($payment, ['id' => 'cs_late'], 'evt_late_paid'));
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_late_failed',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => 'pi_hardening', 'metadata' => ['payment_id' => $payment->id]]],
    ]);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($repair->fresh()->payment_status)->toBe('paid')
        ->and($payment->fresh()->invoice->balance_due)->toEqual(0.00);
});

// ---------------------------------------------------------------------------
// Disputes
// ---------------------------------------------------------------------------

test('a dispute is recorded for investigation without reversing the settled payment', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [$repair, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_dispute']);

    [$payload, $headers] = hardeningSignedRequest(hardeningCheckoutEvent($payment, ['id' => 'cs_dispute'], 'evt_dispute_paid'));
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_dispute_created',
        'type' => 'charge.dispute.created',
        'data' => ['object' => [
            'id' => 'dp_hardening',
            'charge' => 'ch_hardening',
            'payment_intent' => 'pi_hardening',
            'status' => 'needs_response',
            'reason' => 'fraudulent',
            'amount' => 11300,
            'currency' => 'cad',
        ]],
    ]);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    // The payment stays settled: reversing it would reopen the invoice and permit a second charge.
    expect($payment->fresh()->status)->toBe('paid')
        ->and($repair->fresh()->payment_status)->toBe('paid')
        ->and($payment->fresh()->invoice->balance_due)->toEqual(0.00)
        ->and(PaymentTransaction::query()->where('transaction_type', 'chargeback')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Refunds
// ---------------------------------------------------------------------------

test('a refund updated webhook settles a processing refund exactly once', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment([
        'status' => 'paid',
        'paid_at' => now(),
        'stripe_payment_intent_id' => 'pi_refund_sync',
        'gateway_payment_id' => 'pi_refund_sync',
    ]);
    $requester = hardeningUser('hardening-refund-requester@example.com', 'admin');

    $refund = PaymentRefund::query()->create([
        'payment_id' => $payment->id,
        'amount' => 20,
        'currency' => 'cad',
        'status' => 'processing',
        'provider_refund_id' => 're_refund_sync',
        'requested_by' => $requester->id,
        'requested_at' => now(),
    ]);

    $event = [
        'id' => 'evt_refund_sync',
        'type' => 'refund.updated',
        'data' => ['object' => [
            'id' => 're_refund_sync',
            'status' => 'succeeded',
            'payment_intent' => 'pi_refund_sync',
            'amount' => 2000,
            'currency' => 'cad',
        ]],
    ];

    [$payload, $headers] = hardeningSignedRequest($event);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect($refund->fresh()->status)->toBe('succeeded')
        ->and($payment->fresh()->status)->toBe('partially_refunded')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(20.0);

    // Replaying the same refund state must not double-count the refunded amount.
    $event['id'] = 'evt_refund_sync_replay';
    [$payload, $headers] = hardeningSignedRequest($event);
    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect((float) $payment->fresh()->refunded_amount)->toBe(20.0);
});

test('a charge refunded webhook syncs every embedded refund', function () {
    config(['services.stripe.webhook_secret' => 'whsec_hardening']);
    [, $payment] = hardeningRepairPayment([
        'status' => 'paid',
        'paid_at' => now(),
        'stripe_payment_intent_id' => 'pi_charge_refund',
        'gateway_payment_id' => 'pi_charge_refund',
    ]);
    $requester = hardeningUser('hardening-charge-refund@example.com', 'admin');

    foreach ([['re_first', 20], ['re_second', 30]] as [$providerId, $amount]) {
        PaymentRefund::query()->create([
            'payment_id' => $payment->id,
            'amount' => $amount,
            'currency' => 'cad',
            'status' => 'processing',
            'provider_refund_id' => $providerId,
            'requested_by' => $requester->id,
            'requested_at' => now(),
        ]);
    }

    [$payload, $headers] = hardeningSignedRequest([
        'id' => 'evt_charge_refunded',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_charge_refund',
            'payment_intent' => 'pi_charge_refund',
            'refunds' => ['data' => [
                ['id' => 're_first', 'status' => 'succeeded'],
                ['id' => 're_second', 'status' => 'succeeded'],
            ]],
        ]],
    ]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)->assertOk();

    expect((float) $payment->fresh()->refunded_amount)->toBe(50.0)
        ->and($payment->fresh()->status)->toBe('partially_refunded');
});

// ---------------------------------------------------------------------------
// Customer ownership
// ---------------------------------------------------------------------------

test('payment pages require authentication and reject other customers', function () {
    [, $payment] = hardeningRepairPayment();
    $stranger = hardeningUser('hardening-stranger@example.com');

    $this->get(route('payments.show', $payment))->assertRedirect(route('login'));
    $this->actingAs($stranger)->get(route('payments.show', $payment))->assertForbidden();
    $this->actingAs($stranger)->get(route('payments.stripe.success', $payment))->assertForbidden();
    $this->actingAs($payment->customer->user)->get(route('payments.show', $payment))->assertOk();
});

test('the stripe success route never finalizes payment from browser parameters', function () {
    [, $payment] = hardeningRepairPayment();

    $this->actingAs($payment->customer->user)
        ->get(route('payments.stripe.success', $payment).'?session_id=cs_spoof&payment_status=paid&paid=true')
        ->assertOk();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->paid_at)->toBeNull()
        ->and($payment->fresh()->invoice->balance_due)->toEqual(113.00);
});

test('cancelling stripe checkout preserves the invoice and allows another attempt', function () {
    [$repair, $payment] = hardeningRepairPayment();

    $this->actingAs($payment->customer->user)
        ->get(route('payments.cancel', $payment))
        ->assertOk();

    expect($payment->fresh()->status)->toBe('cancelled')
        ->and($payment->fresh()->invoice->balance_due)->toEqual(113.00)
        ->and($repair->fresh()->payment_status)->toBe('unpaid')
        ->and($repair->fresh()->status)->toBe('awaiting_customer_payment');
});

// ---------------------------------------------------------------------------
// Cross-method behaviour
// ---------------------------------------------------------------------------

test('stripe checkout is refused once the invoice is already fully paid by another method', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);
    Http::fake();

    [, $payment] = hardeningRepairPayment();
    $invoice = $payment->invoice;

    // A manual payment settles the invoice first.
    $payment->payable->payments()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $payment->customer_id,
        'source' => 'repair',
        'gateway' => 'cash',
        'method' => 'cash',
        'provider' => 'manual',
        'purpose' => 'balance',
        'amount' => 113,
        'currency' => 'cad',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $url = app(PaymentGatewayService::class)->createCheckout($payment);

    expect($url)->toBe(route('payments.show', $payment))
        ->and($payment->fresh()->status)->toBe('failed')
        ->and($payment->fresh()->failure_message)->toContain('no balance due');

    Http::assertNothingSent();
});

test('a second stripe attempt on one invoice reuses the open checkout session', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_reuse', 'status' => 'open']),
        'https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_reuse', 'url' => 'https://checkout.stripe.test/reuse']),
    ]);

    [$repair, $first] = hardeningRepairPayment();
    app(PaymentGatewayService::class)->createCheckout($first);

    $second = $repair->payments()->create([
        'invoice_id' => $first->invoice_id,
        'customer_id' => $first->customer_id,
        'repair_id' => $repair->id,
        'source' => 'repair',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'purpose' => 'balance',
        'amount' => 113,
        'currency' => 'cad',
        'status' => 'pending',
    ]);

    expect(app(PaymentGatewayService::class)->createCheckout($second))->toBe('https://checkout.stripe.test/reuse')
        ->and($second->fresh()->stripe_checkout_session_id)->toBeNull();

    // One session creation, plus the liveness check on the session being reused.
    Http::assertSentCount(2);
});

test('a completed checkout session is never handed back to a customer', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);
    Http::fake([
        // Stripe reports the stored session as already paid.
        'https://api.stripe.com/v1/checkout/sessions/cs_spent' => Http::response(['id' => 'cs_spent', 'status' => 'complete', 'payment_status' => 'paid']),
        'https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_fresh', 'url' => 'https://checkout.stripe.test/fresh']),
    ]);

    [, $payment] = hardeningRepairPayment([
        'stripe_checkout_session_id' => 'cs_spent',
        'raw_response' => ['id' => 'cs_spent', 'url' => 'https://checkout.stripe.test/spent'],
    ]);

    // A spent session must not be replayed; a new one is created instead.
    expect(app(PaymentGatewayService::class)->createCheckout($payment))->toBe('https://checkout.stripe.test/fresh')
        ->and($payment->fresh()->stripe_checkout_session_id)->toBe('cs_fresh');
});

test('an expired sibling checkout session is not reused', function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
    ]);
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions/cs_stale' => Http::response(['id' => 'cs_stale', 'status' => 'expired']),
        'https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_new', 'url' => 'https://checkout.stripe.test/new']),
    ]);

    [$repair, $first] = hardeningRepairPayment();
    $first->update([
        'stripe_checkout_session_id' => 'cs_stale',
        'raw_response' => ['id' => 'cs_stale', 'url' => 'https://checkout.stripe.test/stale'],
    ]);

    $second = $repair->payments()->create([
        'invoice_id' => $first->invoice_id,
        'customer_id' => $first->customer_id,
        'repair_id' => $repair->id,
        'source' => 'repair',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'purpose' => 'balance',
        'amount' => 113,
        'currency' => 'cad',
        'status' => 'pending',
    ]);

    expect(app(PaymentGatewayService::class)->createCheckout($second))->toBe('https://checkout.stripe.test/new')
        ->and($second->fresh()->stripe_checkout_session_id)->toBe('cs_new');
});

// ---------------------------------------------------------------------------
// Admin webhook retry
// ---------------------------------------------------------------------------

test('an admin can retry an unmatched stripe event once the payment exists', function () {
    $admin = hardeningUser('hardening-retry-admin@example.com', 'admin');
    [, $payment] = hardeningRepairPayment(['stripe_checkout_session_id' => 'cs_retry_ignored']);

    $webhook = PaymentWebhookEvent::query()->create([
        'provider' => 'stripe',
        'provider_event_id' => 'evt_retry_ignored',
        'event_type' => 'checkout.session.completed',
        'status' => PaymentWebhookEvent::STATUS_IGNORED,
        'payload' => hardeningCheckoutEvent($payment, ['id' => 'cs_retry_ignored'], 'evt_retry_ignored'),
        'attempt_count' => 1,
        'received_at' => now(),
    ]);

    $this->actingAs($admin)->patch(route('admin.payment-webhooks.retry', $webhook))->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($webhook->fresh()->status)->toBe(PaymentWebhookEvent::STATUS_PROCESSED);
});

// ---------------------------------------------------------------------------
// Reconciliation
// ---------------------------------------------------------------------------

test('stripe reconciliation reports provider paid while local is unpaid', function () {
    config(['services.stripe.secret' => 'sk_test_hardening']);
    Http::fake([
        'https://api.stripe.com/v1/payment_intents/pi_drift' => Http::response([
            'id' => 'pi_drift',
            'status' => 'succeeded',
            'amount_received' => 11300,
            'currency' => 'cad',
            'amount_refunded' => 0,
        ]),
    ]);

    hardeningRepairPayment([
        'status' => 'pending',
        'stripe_payment_intent_id' => 'pi_drift',
        'gateway_payment_id' => 'pi_drift',
    ]);

    Artisan::call('eclise:reconcile-payments', ['--provider' => 'stripe', '--dry-run' => true, '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    $codes = array_column($report['provider_checks']['stripe']['discrepancies'], 'code');

    expect($codes)->toContain('provider_paid_local_unpaid')
        ->and($report['provider_checks']['stripe']['status'])->toBe('completed');
});

test('stripe reconciliation reports a refund total that drifted from the provider', function () {
    config(['services.stripe.secret' => 'sk_test_hardening']);
    Http::fake([
        'https://api.stripe.com/v1/payment_intents/pi_refund_drift' => Http::response([
            'id' => 'pi_refund_drift',
            'status' => 'succeeded',
            'amount_received' => 11300,
            'currency' => 'cad',
            'amount_refunded' => 2500,
        ]),
    ]);

    hardeningRepairPayment([
        'status' => 'paid',
        'paid_at' => now(),
        'stripe_payment_intent_id' => 'pi_refund_drift',
        'gateway_payment_id' => 'pi_refund_drift',
    ]);

    Artisan::call('eclise:reconcile-payments', ['--provider' => 'stripe', '--dry-run' => true, '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect(array_column($report['provider_checks']['stripe']['discrepancies'], 'code'))->toContain('refund_mismatch');
});

test('local reconciliation still runs with no paypal configuration', function () {
    Artisan::call('eclise:reconcile-payments', ['--dry-run' => true, '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['status'])->toBe('local_checks_complete')
        ->and($report['provider_checks']['paypal']['available'])->toBeFalse()
        ->and($report['dry_run'])->toBeTrue();
});

test('the repair confirmation page is not reachable by repair id alone', function () {
    [$repair, $payment] = hardeningRepairPayment();
    $stranger = hardeningUser('hardening-repair-stranger@example.com');
    $admin = hardeningUser('hardening-repair-admin@example.com', 'admin');

    // It renders customer name, shipping address and payment state.
    $this->get(route('repairs.confirmation', $repair))->assertRedirect(route('login'));
    $this->actingAs($stranger)->get(route('repairs.confirmation', $repair))->assertForbidden();
    $this->actingAs($payment->customer->user)->get(route('repairs.confirmation', $repair))->assertOk();
    $this->actingAs($admin)->get(route('repairs.confirmation', $repair))->assertOk();
});
