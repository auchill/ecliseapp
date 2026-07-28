<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\Repair;
use App\Models\User;
use App\Services\PaymentFinalizer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Mail::fake();
});

function paymentDomainRepairPayment(array $paymentOverrides = []): Payment
{
    $user = User::query()->create([
        'name' => 'Payment Domain Customer',
        'email' => 'payment-domain-'.str()->random(8).'@example.com',
        'password' => 'password',
        'role' => 'customer',
        'status' => 'active',
    ]);
    $customer = Customer::forUser($user);
    $repair = Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'ECL-REP-'.now()->format('Y').'-'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 14',
        'issue_category' => 'Screen',
        'issue_description' => 'Cracked display',
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

    return $repair->payments()->create(array_merge([
        'customer_id' => $customer->id,
        'repair_id' => $repair->id,
        'source' => 'repair',
        'purpose' => 'balance',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'amount' => 113,
        'currency' => 'cad',
        'status' => 'pending',
    ], $paymentOverrides));
}

test('payment domain schema adds invoices transactions refunds webhooks and payment classification columns', function () {
    expect(Schema::hasTable('invoices'))->toBeTrue()
        ->and(Schema::hasTable('invoice_items'))->toBeTrue()
        ->and(Schema::hasTable('payment_transactions'))->toBeTrue()
        ->and(Schema::hasTable('payment_refunds'))->toBeTrue()
        ->and(Schema::hasTable('payment_webhook_events'))->toBeTrue()
        ->and(Schema::hasColumns('payments', [
            'payment_number',
            'invoice_id',
            'customer_id',
            'purpose',
            'method',
            'provider',
            'refunded_amount',
            'gateway_payment_id',
            'gateway_reference',
            'received_by',
            'verified_by',
            'metadata',
        ]))->toBeTrue();
});

test('finalized repair payments write one sanitized payment transaction ledger row', function () {
    $payment = paymentDomainRepairPayment([
        'gateway_reference_id' => 'pi_original',
    ]);

    expect($payment->payment_number)->toMatch('/^PAY-\d{4}-\d{7}$/')
        ->and($payment->transactions()->count())->toBe(0);

    $finalized = app(PaymentFinalizer::class)->markPaid($payment, [
        'gateway_reference_id' => 'pi_paid',
        'raw_response' => [
            'id' => 'evt_payment_domain_paid',
            'client_secret' => 'should-not-be-stored',
            'data' => [
                'object' => [
                    'card' => [
                        'last4' => '4242',
                    ],
                ],
            ],
        ],
    ]);

    $transaction = PaymentTransaction::query()->firstOrFail();

    expect($finalized->status)->toBe('paid')
        ->and($finalized->raw_response['client_secret'])->toBe('[redacted]')
        ->and($transaction->payment_id)->toBe($payment->id)
        ->and($transaction->transaction_type)->toBe('payment')
        ->and($transaction->status)->toBe('succeeded')
        ->and($transaction->provider_transaction_id)->toBe('pi_paid')
        ->and($transaction->response_payload['client_secret'])->toBe('[redacted]')
        ->and($transaction->response_payload['data']['object']['card'])->toBe('[redacted]');

    app(PaymentFinalizer::class)->markPaid($payment->fresh());

    expect(PaymentTransaction::query()->count())->toBe(1);
});

test('stripe webhooks persist sanitized event payloads and ignore duplicate provider events', function () {
    config(['services.stripe.webhook_secret' => 'whsec_payment_domain_test']);

    $payment = paymentDomainRepairPayment([
        'stripe_checkout_session_id' => 'cs_payment_domain_123',
    ]);
    $event = [
        'id' => 'evt_payment_domain_123',
        'type' => 'checkout.session.completed',
        'client_secret' => 'should-not-be-stored',
        'data' => [
            'object' => [
                'id' => 'cs_payment_domain_123',
                'payment_intent' => 'pi_payment_domain_123',
                'amount_total' => 11300,
                'currency' => 'cad',
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ],
        ],
    ];
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_payment_domain_test');
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ];

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)
        ->assertOk()
        ->assertJson(['received' => true]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload)
        ->assertOk()
        ->assertJson(['received' => true, 'duplicate' => true]);

    $webhookEvent = PaymentWebhookEvent::query()->firstOrFail();

    expect($payment->fresh()->status)->toBe('paid')
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->count())->toBe(1)
        ->and($webhookEvent->status)->toBe(PaymentWebhookEvent::STATUS_PROCESSED)
        ->and($webhookEvent->payload['client_secret'])->toBe('[redacted]');
});

test('payment migration verifier reports a clean empty migrated database', function () {
    $this->artisan('eclise:verify-payment-migration --json')
        ->assertSuccessful();
});
