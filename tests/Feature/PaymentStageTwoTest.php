<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Models\PaymentRefund;
use App\Models\Product;
use App\Models\Repair;
use App\Models\User;
use App\Services\Payments\InvoiceService;
use App\Services\Payments\ManualPaymentService;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Mail::fake();
    config([
        'services.stripe.secret' => null,
        'services.paypal.client_id' => null,
        'services.paypal.secret' => null,
    ]);
});

function stage2User(string $email, string $role = 'customer'): User
{
    return User::query()->create([
        'name' => ucfirst($role).' Stage Two',
        'email' => $email,
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

function stage2Product(string $sku = 'STAGE2-1', int $quantity = 5): Product
{
    return Product::query()->create([
        'name' => 'Stage Two Product',
        'slug' => strtolower($sku),
        'sku' => $sku,
        'regular_price' => 100,
        'quantity' => $quantity,
        'is_active' => true,
    ]);
}

function stage2Cart(User $user, Product $product): Cart
{
    $cart = Customer::forUser($user)->carts()->create(['status' => 'active']);
    $cart->items()->create([
        'source_id' => $product->id,
        'source_sku' => $product->sku,
        'source' => CartItem::SOURCE_ECLISE,
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    return $cart;
}

function stage2Repair(User $user, float $total = 113): Repair
{
    $customer = Customer::forUser($user);

    return Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'STAGE2-REP-'.random_int(1000, 9999),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 15',
        'issue_category' => 'Screen',
        'issue_description' => 'Broken display',
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
}

function stage2PaidRepairPayment(): Payment
{
    $admin = stage2User('stage2-paid-admin@example.com', 'admin');
    $customerUser = stage2User('stage2-paid-customer@example.com');
    $repair = stage2Repair($customerUser);
    $invoice = app(InvoiceService::class)->createRepairFinalInvoice($repair);

    return app(ManualPaymentService::class)->record($invoice, $admin, [
        'method' => 'cash',
        'amount' => 113,
        'currency' => 'cad',
        'manual_reference' => 'CASH-STAGE2-PAID',
    ]);
}

test('stage 2 payment schema adds operational fields settings and audit logs', function () {
    expect(Schema::hasColumns('payments', [
        'receipt_number',
        'manual_reference',
        'proof_path',
        'submitted_at',
        'rejected_by',
        'rejection_reason',
        'created_by',
        'source_ip',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('payment_refunds', [
            'requested_method',
            'processed_method',
            'manual_reference',
            'internal_note',
            'source_ip',
        ]))->toBeTrue()
        ->and(Schema::hasTable('payment_settings'))->toBeTrue()
        ->and(Schema::hasTable('payment_audit_logs'))->toBeTrue();
});

test('interac shop checkout creates an invoice and verifies into an order with a receipt', function () {
    $customerUser = stage2User('stage2-interac-shop@example.com');
    $product = stage2Product('STAGE2-INTERAC');
    $cart = stage2Cart($customerUser, $product);

    $this->actingAs($customerUser)
        ->post(route('checkout.store'), [
            'full_name' => 'Stage Two Customer',
            'email' => 'stage2-interac-shop@example.com',
            'phone' => '416-555-2000',
            'payment_gateway' => 'interac',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    $payment = Payment::query()->with('invoice')->firstOrFail();

    expect(Order::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(1)
        ->and($payment->source)->toBe('shop')
        ->and($payment->method)->toBe('interac')
        ->and($payment->status)->toBe('pending_verification')
        ->and($payment->invoice_id)->not->toBeNull()
        ->and((float) $payment->invoice->total)->toBe(113.0)
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeTrue();

    $admin = stage2User('stage2-interac-admin@example.com', 'admin');
    $verified = app(ManualPaymentService::class)->verifyInterac($payment, $admin, [
        'manual_reference' => 'INT-STAGE2-1',
    ]);

    $order = Order::query()->with('items')->firstOrFail();
    $invoice = $payment->invoice->fresh();

    expect($verified->status)->toBe('paid')
        ->and($verified->receipt_number)->toMatch('/^RCT-\d{4}-\d{7}$/')
        ->and($verified->verified_by)->toBe($admin->id)
        ->and($verified->payable->is($order))->toBeTrue()
        ->and($order->customer_id)->toBe($payment->customer_id)
        ->and($order->items)->toHaveCount(1)
        ->and($invoice->invoiceable->is($order))->toBeTrue()
        ->and($invoice->status)->toBe('paid')
        ->and((float) $invoice->balance_due)->toBe(0.0)
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeFalse();
});

test('admin manual cash payment pays a repair invoice and writes receipt audit and transaction records', function () {
    $admin = stage2User('stage2-cash-admin@example.com', 'admin');
    $customerUser = stage2User('stage2-cash-customer@example.com');
    $repair = stage2Repair($customerUser);
    $invoice = app(InvoiceService::class)->createRepairFinalInvoice($repair);

    $payment = app(ManualPaymentService::class)->record($invoice, $admin, [
        'method' => 'cash',
        'amount' => 113,
        'currency' => 'cad',
        'manual_reference' => 'CASH-STAGE2-1',
    ]);

    expect($payment->status)->toBe('paid')
        ->and($payment->source)->toBe('repair')
        ->and($payment->method)->toBe('cash')
        ->and($payment->provider)->toBe('manual')
        ->and($payment->received_by)->toBe($admin->id)
        ->and($payment->receipt_number)->toMatch('/^RCT-\d{4}-\d{7}$/')
        ->and($payment->transactions()->where('transaction_type', 'manual_confirmation')->exists())->toBeTrue()
        ->and(PaymentAuditLog::query()->where('event', 'payment.manual.recorded')->exists())->toBeTrue()
        ->and((float) $repair->fresh()->amount_paid)->toBe(113.0)
        ->and($repair->fresh()->payment_status)->toBe('paid')
        ->and($invoice->fresh()->status)->toBe('paid');
});

test('refund approval and processing updates payment and invoice balances', function () {
    $payment = stage2PaidRepairPayment();
    $requester = stage2User('stage2-refund-requester@example.com', 'admin');
    $approver = stage2User('stage2-refund-approver@example.com', 'admin');

    $refund = app(RefundService::class)->request($payment, $requester, [
        'amount' => 20,
        'reason' => 'Customer adjustment',
        'requested_method' => 'cash',
    ]);

    expect($refund->status)->toBe('pending')
        ->and(PaymentRefund::query()->count())->toBe(1);

    app(RefundService::class)->approve($refund, $approver);
    $processed = app(RefundService::class)->processManual($refund->fresh(), $approver, [
        'processed_method' => 'cash',
        'manual_reference' => 'REF-STAGE2-1',
    ]);

    $payment->refresh();
    $invoice = $payment->invoice->fresh();

    expect($processed->status)->toBe('succeeded')
        ->and($payment->status)->toBe('partially_refunded')
        ->and((float) $payment->refunded_amount)->toBe(20.0)
        ->and($payment->transactions()->where('transaction_type', 'refund')->exists())->toBeTrue()
        ->and($invoice->status)->toBe('partially_refunded')
        ->and((float) $invoice->amount_paid)->toBe(93.0)
        ->and((float) $invoice->refunded_amount)->toBe(20.0)
        ->and((float) $invoice->balance_due)->toBe(20.0);

    $this->actingAs($payment->customer->user)
        ->get(route('payments.receipt', $payment))
        ->assertOk()
        ->assertSee($payment->receipt_number);
});

test('customers cannot view invoices that belong to another customer', function () {
    $owner = stage2User('stage2-invoice-owner@example.com');
    $other = stage2User('stage2-invoice-other@example.com');
    $invoice = app(InvoiceService::class)->createRepairFinalInvoice(stage2Repair($owner));

    $this->actingAs($other)
        ->get(route('invoices.show', $invoice))
        ->assertForbidden();
});

test('admin cannot ship an unpaid shop order by submitting paid status in the form', function () {
    $admin = stage2User('stage2-gate-admin@example.com', 'admin');
    $customerUser = stage2User('stage2-gate-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $order = Order::query()->create([
        'customer_id' => $customer->id,
        'order_number' => 'STAGE2-GATE-ORDER',
        'subtotal' => 100,
        'tax' => 13,
        'shipping_cost' => 20,
        'total' => 133,
        'status' => 'Pending',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'shipping',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update', $order), [
            'status' => 'Shipped',
            'payment_status' => 'paid',
            'fulfillment_method' => 'shipping',
            'shipping_cost' => 20,
            'recipient_name' => 'Gate Customer',
            'recipient_phone' => '416-555-3000',
            'recipient_email' => 'stage2-gate-customer@example.com',
            'address_line1' => '1 Gate Street',
            'city' => 'Toronto',
            'province' => 'ON',
            'postal_code' => 'M5J 1A1',
            'country' => 'Canada',
        ])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe('Pending')
        ->and($order->statusUpdates()->count())->toBe(0);
});

test('reconciliation dry run returns provider availability and does not write audit logs', function () {
    config([
        'services.stripe.secret' => null,
        'services.paypal.client_id' => null,
        'services.paypal.secret' => null,
    ]);

    $before = PaymentAuditLog::query()->count();

    Artisan::call('eclise:reconcile-payments', [
        '--dry-run' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray()
        ->and($payload['dry_run'])->toBeTrue()
        ->and($payload['records_checked'])->toBeInt()
        ->and($payload['provider_checks']['stripe']['status'])->toBe('unavailable')
        ->and($payload['provider_checks']['paypal']['status'])->toBe('unavailable')
        ->and(PaymentAuditLog::query()->count())->toBe($before);
});
