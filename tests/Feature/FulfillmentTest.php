<?php

use App\Mail\OrderStatusUpdatedMail;
use App\Mail\QuoteBookingCreatedMail;
use App\Mail\QuoteSubmittedCustomerMail;
use App\Mail\RepairStatusUpdatedMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductModel;
use App\Models\Quote;
use App\Models\RepairBooking;
use App\Models\ShippingDiscountRule;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\PaymentFinalizer;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    $this->seed(ShippingSeeder::class);
    $this->seed(ReferenceDataSeeder::class);
});

function addProductToCart(Cart $cart, Product $product, int $quantity = 1): void
{
    $cart->items()->create([
        'source_id' => $product->id,
        'source_sku' => $product->sku,
        'source' => CartItem::SOURCE_ECLISE,
        'quantity' => $quantity,
        'unit_price' => $product->currentPrice(),
    ]);
}

test('checkout pickup creates a pickup order with zero shipping', function () {
    $user = User::query()->create([
        'name' => 'Pickup Customer',
        'email' => 'pickup@example.com',
        'password' => 'password',
    ]);

    $product = Product::query()->create([
        'name' => 'Pickup Phone',
        'slug' => 'pickup-phone',
        'sku' => 'PICKUP-1',
        'condition' => 'Used',
        'price' => 100,
        'quantity' => 2,
        'status' => 'Active',
    ]);

    $cart = Customer::forUser($user)->carts()->create(['status' => 'active']);
    addProductToCart($cart, $product);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Pickup Customer',
            'email' => 'pickup@example.com',
            'phone' => '416-555-0001',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    expect(Order::query()->count())->toBe(0);
    app(PaymentFinalizer::class)->markPaid(Payment::query()->firstOrFail());
    $order = Order::query()->firstOrFail();

    expect($order->fulfillment_method)->toBe('pickup')
        ->and($order->order_number)->toMatch('/^ECL-ORD-\d{4}-\d{7}$/')
        ->and($order->shipping_method_id)->toBeNull()
        ->and((float) $order->shipping_base_cost)->toBe(0.0)
        ->and((float) $order->shipping_discount_amount)->toBe(0.0)
        ->and((float) $order->shipping_cost)->toBe(0.0)
        ->and($order->payment_status)->toBe('paid')
        ->and((float) $order->total)->toBe(113.0);

    expect($order->payments()->count())->toBe(1)
        ->and($order->payments()->first()->source)->toBe('shop')
        ->and($order->customer)->toBeInstanceOf(Customer::class)
        ->and($product->fresh()->quantity)->toBe(1);
});

test('checkout normal shipping stores method snapshot and regular shipping cost', function () {
    $method = ShippingMethod::query()->where('code', 'normal')->firstOrFail();
    $user = User::query()->create([
        'name' => 'Shipping Customer',
        'email' => 'shipping@example.com',
        'password' => 'password',
    ]);

    $product = Product::query()->create([
        'name' => 'Shipping Phone',
        'slug' => 'shipping-phone',
        'sku' => 'SHIP-1',
        'condition' => 'Used',
        'price' => 100,
        'quantity' => 2,
        'status' => 'Active',
    ]);

    $cart = Customer::forUser($user)->carts()->create(['status' => 'active']);
    addProductToCart($cart, $product);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Shipping Customer',
            'email' => 'shipping@example.com',
            'phone' => '416-555-0002',
            'payment_gateway' => 'paypal',
            'fulfillment_method' => 'shipping',
            'shipping_method_id' => $method->id,
            'shipping_full_name' => 'Shipping Customer',
            'shipping_phone' => '416-555-0002',
            'shipping_email' => 'shipping@example.com',
            'shipping_address_line1' => '10 King Street',
            'shipping_city' => 'Toronto',
            'shipping_province' => 'ON',
            'shipping_postal_code' => 'M5H 1A1',
            'shipping_country' => 'Canada',
        ])
        ->assertRedirect();

    app(PaymentFinalizer::class)->markPaid(Payment::query()->firstOrFail());
    $order = Order::query()->firstOrFail();

    expect($order->fulfillment_method)->toBe('shipping')
        ->and($order->shipping_method_id)->toBe($method->id)
        ->and($order->shipping_method_name)->toBe('Normal Shipping')
        ->and($order->shipping_delivery_days)->toBe('3-7 days')
        ->and((float) $order->shipping_base_cost)->toBe(20.0)
        ->and((float) $order->shipping_discount_amount)->toBe(0.0)
        ->and((float) $order->shipping_cost)->toBe(20.0)
        ->and($order->latestPayment->gateway)->toBe('paypal')
        ->and((float) $order->total)->toBe(133.0);

    $this->post(route('orders.track.result'), [
        'order_number' => $order->order_number,
        'contact' => 'shipping@example.com',
    ])->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Normal Shipping')
        ->assertSee('Final shipping');
});

test('checkout applies the best matching shipping discount', function () {
    $normal = ShippingMethod::query()->where('code', 'normal')->firstOrFail();
    $overnight = ShippingMethod::query()->where('code', 'overnight')->firstOrFail();

    $normalCustomer = User::query()->create([
        'name' => 'Free Shipping Customer',
        'email' => 'free-shipping@example.com',
        'password' => 'password',
    ]);

    $normalProduct = Product::query()->create([
        'name' => 'Free Shipping Laptop',
        'slug' => 'free-shipping-laptop',
        'sku' => 'FREE-SHIP-1',
        'condition' => 'Used',
        'price' => 350,
        'quantity' => 2,
        'status' => 'Active',
    ]);

    $normalCart = Customer::forUser($normalCustomer)->carts()->create(['status' => 'active']);
    addProductToCart($normalCart, $normalProduct);

    $this->actingAs($normalCustomer)
        ->post(route('checkout.store'), [
            'customer_name' => 'Free Shipping Customer',
            'email' => 'free-shipping@example.com',
            'phone' => '416-555-0101',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'shipping',
            'shipping_method_id' => $normal->id,
            'shipping_full_name' => 'Free Shipping Customer',
            'shipping_phone' => '416-555-0101',
            'shipping_email' => 'free-shipping@example.com',
            'shipping_address_line1' => '50 Queen Street',
            'shipping_city' => 'Toronto',
            'shipping_province' => 'ON',
            'shipping_postal_code' => 'M5V 2A1',
            'shipping_country' => 'Canada',
        ])
        ->assertRedirect();

    app(PaymentFinalizer::class)->markPaid(Payment::query()->latest('id')->firstOrFail());
    $normalOrder = Order::query()->where('email', 'free-shipping@example.com')->firstOrFail();

    expect((float) $normalOrder->shipping_base_cost)->toBe(20.0)
        ->and((float) $normalOrder->shipping_discount_amount)->toBe(20.0)
        ->and((float) $normalOrder->shipping_cost)->toBe(0.0)
        ->and((float) $normalOrder->total)->toBe(395.5);

    $overnightCustomer = User::query()->create([
        'name' => 'Overnight Customer',
        'email' => 'overnight@example.com',
        'password' => 'password',
    ]);

    $overnightProduct = Product::query()->create([
        'name' => 'Overnight Laptop',
        'slug' => 'overnight-laptop',
        'sku' => 'OVERNIGHT-1',
        'condition' => 'Used',
        'price' => 600,
        'quantity' => 2,
        'status' => 'Active',
    ]);

    $overnightCart = Customer::forUser($overnightCustomer)->carts()->create(['status' => 'active']);
    addProductToCart($overnightCart, $overnightProduct);

    $this->actingAs($overnightCustomer)
        ->post(route('checkout.store'), [
            'customer_name' => 'Overnight Customer',
            'email' => 'overnight@example.com',
            'phone' => '416-555-0102',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'shipping',
            'shipping_method_id' => $overnight->id,
            'shipping_full_name' => 'Overnight Customer',
            'shipping_phone' => '416-555-0102',
            'shipping_email' => 'overnight@example.com',
            'shipping_address_line1' => '60 Bay Street',
            'shipping_city' => 'Toronto',
            'shipping_province' => 'ON',
            'shipping_postal_code' => 'M5J 2N8',
            'shipping_country' => 'Canada',
        ])
        ->assertRedirect();

    app(PaymentFinalizer::class)->markPaid(Payment::query()->latest('id')->firstOrFail());
    $overnightOrder = Order::query()->where('email', 'overnight@example.com')->firstOrFail();

    expect($overnightOrder->shipping_method_name)->toBe('Overnight Shipping')
        ->and($overnightOrder->shipping_delivery_days)->toBe('1 day')
        ->and((float) $overnightOrder->shipping_base_cost)->toBe(45.0)
        ->and((float) $overnightOrder->shipping_discount_amount)->toBe(22.5)
        ->and((float) $overnightOrder->shipping_cost)->toBe(22.5)
        ->and((float) $overnightOrder->total)->toBe(700.5);
});

test('repair shipping calculates selected return shipping method and appears in tracking', function () {
    $method = ShippingMethod::query()->where('code', 'overnight')->firstOrFail();
    $user = User::query()->create([
        'name' => 'Repair Customer',
        'email' => 'repair@example.com',
        'password' => 'password',
    ]);

    $repair = RepairBooking::query()->create([
        'tracking_number' => 'ECL-REP-TEST-SHIP',
        'customer_name' => 'Repair Customer',
        'email' => 'repair@example.com',
        'phone' => '416-555-0003',
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 13',
        'issue_category' => 'Screen Replacement',
        'issue_description' => 'The display is cracked and needs replacement.',
        'repair_items' => [
            ['type' => 'workmanship', 'name' => 'Screen replacement labour', 'quantity' => 1, 'unit_price' => 80, 'total' => 80],
            ['type' => 'part', 'name' => 'iPhone 13 screen', 'quantity' => 1, 'unit_price' => 120, 'total' => 120],
        ],
        'subtotal' => 200,
        'tax_amount' => 26,
        'shipping_amount' => 0,
        'total_amount' => 226,
        'repair_total' => 226,
        'amount_paid' => 0,
        'balance_due' => 226,
        'payment_status' => 'unpaid',
        'repair_status' => 'awaiting_customer_payment',
        'status' => 'awaiting_customer_payment',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
    ]);

    $this->actingAs($user)->post(route('repairs.complete.store', $repair->tracking_number), [
        'payment_gateway' => 'paypal',
        'payment_amount_option' => 'minimum',
        'fulfillment_method' => 'shipping',
        'shipping_method_id' => $method->id,
        'shipping_full_name' => 'Repair Customer',
        'shipping_phone' => '416-555-0003',
        'shipping_email' => 'repair@example.com',
        'shipping_address_line1' => '20 Queen Street',
        'shipping_city' => 'Toronto',
        'shipping_province' => 'ON',
        'shipping_postal_code' => 'M5V 2A1',
        'shipping_country' => 'Canada',
        'terms_accepted' => '1',
    ])->assertRedirect();

    $repair->refresh();

    expect($repair->fulfillment_method)->toBe('shipping')
        ->and($repair->user_id)->toBe($user->id)
        ->and($repair->shipping_method_name)->toBe('Overnight Shipping')
        ->and($repair->shipping_delivery_days)->toBe('1 day')
        ->and((float) $repair->shipping_base_cost)->toBe(45.0)
        ->and((float) $repair->shipping_discount_amount)->toBe(0.0)
        ->and((float) $repair->shipping_cost)->toBe(45.0)
        ->and($repair->payment_status)->toBe('unpaid')
        ->and($repair->latestPayment->gateway)->toBe('paypal')
        ->and($repair->latestPayment->source)->toBe('repair')
        ->and((float) $repair->repair_total)->toBe(271.0)
        ->and((float) $repair->latestPayment->amount)->toBe(195.5);

    $this->post(route('repairs.track.submit'), [
        'tracking_number' => $repair->tracking_number,
        'contact' => 'repair@example.com',
    ])->assertOk()
        ->assertSee($repair->tracking_number)
        ->assertSee('Overnight Shipping')
        ->assertSee('Final shipping');
});

test('verified payment finalization marks order paid and commits inventory once', function () {
    Mail::fake();

    $user = User::query()->create([
        'name' => 'Paid Customer',
        'email' => 'paid@example.com',
        'password' => 'password',
    ]);

    $product = Product::query()->create([
        'name' => 'Paid Phone',
        'slug' => 'paid-phone',
        'sku' => 'PAID-1',
        'condition' => 'Used',
        'price' => 100,
        'quantity' => 2,
        'status' => 'Active',
    ]);

    $cart = Customer::forUser($user)->carts()->create(['status' => 'active']);
    addProductToCart($cart, $product);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Paid Customer',
            'email' => 'paid@example.com',
            'phone' => '416-555-9999',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    $payment = Payment::query()->firstOrFail();

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(2)
        ->and($cart->fresh()->status)->toBe('active');

    app(PaymentFinalizer::class)->markPaid($payment, [
        'gateway_reference_id' => 'pi_test_123',
        'stripe_payment_intent_id' => 'pi_test_123',
    ]);

    $order = Order::query()->firstOrFail();

    expect($order->payment_status)->toBe('paid')
        ->and($order->inventory_committed_at)->not->toBeNull()
        ->and($product->fresh()->quantity)->toBe(1)
        ->and($cart->fresh())->toBeNull();

    app(PaymentFinalizer::class)->markPaid($payment->fresh());

    expect($product->fresh()->quantity)->toBe(1);
});

test('guest cart items merge into customer cart on login', function () {
    $user = User::query()->create([
        'name' => 'Cart Merge',
        'email' => 'cart-merge@example.com',
        'password' => 'password',
    ]);

    $product = Product::query()->create([
        'name' => 'Merge Phone',
        'slug' => 'merge-phone',
        'sku' => 'MERGE-1',
        'condition' => 'Used',
        'price' => 120,
        'quantity' => 5,
        'status' => 'Active',
    ]);

    $this->withSession(['cart.items' => [$product->id => 2]])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard'));

    $cart = Customer::forUser($user)->activeCart()->firstOrFail();

    expect($cart->items()->where('source_id', $product->id)->where('source_sku', $product->sku)->where('source', 'Eclise')->value('quantity'))->toBe(2)
        ->and(session('cart.items'))->toBeNull();
});

test('customer quote submission can be converted to a priced repair booking', function () {
    Mail::fake();

    $admin = User::query()->create([
        'name' => 'Quote Admin',
        'email' => 'quote-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $deviceType = DeviceType::query()->where('slug', 'phone')->firstOrFail();
    $productBrand = ProductBrand::query()->create([
        'name' => 'Apple',
        'slug' => 'apple',
        'status' => 'active',
    ]);
    $productModel = ProductModel::query()->create([
        'name' => 'iPhone 13',
        'slug' => 'iphone-13',
        'status' => 'active',
    ]);
    $issueCategory = IssueCategory::query()->where('slug', 'screen-replacement')->firstOrFail();
    $quoteUser = User::query()->create([
        'name' => 'Quote Customer',
        'email' => 'quote@example.com',
        'password' => 'password',
    ]);
    $quoteCustomer = Customer::forUser($quoteUser);

    $this->actingAs($quoteUser)->post(route('quotes.store'), [
        'device_type_id' => $deviceType->id,
        'product_brand_id' => $productBrand->id,
        'product_model_id' => $productModel->id,
        'issue_category_id' => $issueCategory->id,
        'preferred_date' => now()->addDay()->toDateString(),
        'preferred_time' => '11:30',
        'issue_description' => 'Screen cracked after a drop.',
    ])->assertRedirect(route('quotes.create'));

    $quote = Quote::query()->firstOrFail();

    expect($quote->status)->toBe('pending')
        ->and($quote->customer_id)->toBe($quoteCustomer->id)
        ->and(str_starts_with($quote->quote_number, 'ECL-QTE-'))->toBeTrue();

    Mail::assertSent(QuoteSubmittedCustomerMail::class);

    $this->actingAs($admin)
        ->post(route('admin.quotes.convert.store', $quote), [
            'device_type_id' => $deviceType->id,
            'product_brand_id' => $productBrand->id,
            'product_model_id' => $productModel->id,
            'issue_category_id' => $issueCategory->id,
            'preferred_appointment_date' => now()->addDay()->toDateString(),
            'preferred_appointment_time' => '11:30',
            'repair_item_type' => ['workmanship', 'part'],
            'repair_item_name' => ['Screen replacement labour', 'iPhone 13 screen'],
            'repair_item_quantity' => [1, 1],
            'repair_item_unit_price' => [80, 120],
            'tax_amount' => 26,
            'internal_notes' => 'Approved quote.',
        ])
        ->assertRedirect();

    $booking = RepairBooking::query()->where('quote_id', $quote->id)->firstOrFail();

    expect($quote->fresh()->status)->toBe('converted_to_repair')
        ->and($quote->fresh()->converted_to_repair)->toBeTrue()
        ->and($booking->customer_id)->toBe($quoteCustomer->id)
        ->and($booking->repair_number)->toMatch('/^ECL-REP-\d{4}-\d{7}$/')
        ->and($booking->payment_status)->toBe('unpaid')
        ->and($booking->repair_status)->toBe('awaiting_customer_payment')
        ->and((float) $booking->subtotal)->toBe(200.0)
        ->and((float) $booking->tax_amount)->toBe(26.0)
        ->and((float) $booking->total_amount)->toBe(226.0)
        ->and($booking->repair_items)->toHaveCount(2);

    Mail::assertSent(QuoteBookingCreatedMail::class);
});

test('admin can manage shipping methods and discounts', function () {
    $admin = User::query()->create([
        'name' => 'Shipping Admin',
        'email' => 'shipping-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipping-methods.index'))
        ->assertOk()
        ->assertSee('Normal Shipping')
        ->assertSee('Overnight Shipping');

    $this->actingAs($admin)
        ->post(route('admin.shipping-methods.store'), [
            'name' => 'Same Day Shipping',
            'code' => 'same day',
            'description' => 'Local same-day delivery.',
            'base_cost' => 65,
            'delivery_days_min' => 1,
            'delivery_days_max' => 1,
            'sort_order' => 5,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.shipping-methods.index'));

    $method = ShippingMethod::query()->where('code', 'same-day')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.shipping-methods.update', $method), [
            'name' => 'Same Day Shipping',
            'code' => 'same day',
            'description' => 'Local same-day delivery.',
            'base_cost' => 60,
            'delivery_days_min' => 1,
            'delivery_days_max' => 1,
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.shipping-methods.edit', $method));

    expect($method->fresh()->is_active)->toBeFalse()
        ->and((float) $method->fresh()->base_cost)->toBe(60.0);

    $this->actingAs($admin)
        ->post(route('admin.shipping-discounts.store'), [
            'name' => 'Same Day $10 off over $200',
            'minimum_order_amount' => 200,
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'shipping_method_id' => $method->id,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.shipping-discounts.index'));

    expect(ShippingDiscountRule::query()->where('name', 'Same Day $10 off over $200')->exists())->toBeTrue();

    $discount = ShippingDiscountRule::query()->where('name', 'Same Day $10 off over $200')->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.shipping-discounts.destroy', $discount))
        ->assertRedirect(route('admin.shipping-discounts.index'));

    $this->actingAs($admin)
        ->delete(route('admin.shipping-methods.destroy', $method))
        ->assertRedirect(route('admin.shipping-methods.index'));

    expect(ShippingDiscountRule::query()->whereKey($discount->id)->exists())->toBeFalse()
        ->and(ShippingMethod::query()->whereKey($method->id)->exists())->toBeFalse();
});

test('admin status updates create timelines and queue mail rendering', function () {
    Mail::fake();

    $admin = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $order = Order::query()->create([
        'order_number' => 'ECL-ORD-TEST-0001',
        'customer_name' => 'Mail Customer',
        'email' => 'mail@example.com',
        'phone' => '416-555-0004',
        'subtotal' => 100,
        'tax' => 13,
        'shipping_cost' => 20,
        'total' => 133,
        'status' => 'Pending',
        'payment_status' => 'Pending',
        'payment_provider' => 'stripe',
        'fulfillment_method' => 'shipping',
        'shipping_full_name' => 'Mail Customer',
        'shipping_phone' => '416-555-0004',
        'shipping_email' => 'mail@example.com',
        'shipping_address_line1' => '30 Bay Street',
        'shipping_city' => 'Toronto',
        'shipping_province' => 'ON',
        'shipping_postal_code' => 'M5J 2N8',
        'shipping_country' => 'Canada',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update', $order), [
            'status' => 'Shipped',
            'payment_status' => 'Paid',
            'fulfillment_method' => 'shipping',
            'shipping_cost' => 20,
            'shipping_full_name' => 'Mail Customer',
            'shipping_phone' => '416-555-0004',
            'shipping_email' => 'mail@example.com',
            'shipping_address_line1' => '30 Bay Street',
            'shipping_city' => 'Toronto',
            'shipping_province' => 'ON',
            'shipping_postal_code' => 'M5J 2N8',
            'shipping_country' => 'Canada',
            'delivery_carrier' => 'Canada Post',
            'tracking_number' => 'CP123',
            'status_note' => 'Order shipped.',
            'is_customer_visible' => '1',
        ])
        ->assertRedirect();

    expect($order->statusUpdates()->count())->toBe(1);
    Mail::assertSent(OrderStatusUpdatedMail::class);

    $repair = RepairBooking::query()->create([
        'tracking_number' => 'ECL-REP-TEST-0001',
        'customer_name' => 'Repair Mail',
        'email' => 'repairmail@example.com',
        'phone' => '416-555-0005',
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 14',
        'issue_category' => 'Battery',
        'issue_description' => 'Battery drains quickly.',
        'terms_accepted' => true,
        'status' => 'booking_created',
        'repair_status' => 'booking_created',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'shipping',
        'shipping_cost' => 20,
        'shipping_amount' => 20,
        'repair_total' => 20,
        'total_amount' => 20,
        'balance_due' => 20,
        'shipping_full_name' => 'Repair Mail',
        'shipping_phone' => '416-555-0005',
        'shipping_email' => 'repairmail@example.com',
        'shipping_address_line1' => '40 Front Street',
        'shipping_city' => 'Toronto',
        'shipping_province' => 'ON',
        'shipping_postal_code' => 'M5J 1E3',
        'shipping_country' => 'Canada',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.repairs.update', $repair), [
            'status' => 'shipped',
            'payment_status' => 'unpaid',
            'fulfillment_method' => 'shipping',
            'shipping_cost' => 20,
            'shipping_full_name' => 'Repair Mail',
            'shipping_phone' => '416-555-0005',
            'shipping_email' => 'repairmail@example.com',
            'shipping_address_line1' => '40 Front Street',
            'shipping_city' => 'Toronto',
            'shipping_province' => 'ON',
            'shipping_postal_code' => 'M5J 1E3',
            'shipping_country' => 'Canada',
            'delivery_carrier' => 'FedEx',
            'delivery_tracking_number' => 'FX123',
            'status_note' => 'Repair shipped.',
            'is_customer_visible' => '1',
        ])
        ->assertRedirect();

    expect($repair->statusUpdates()->count())->toBe(1);
    Mail::assertSent(RepairStatusUpdatedMail::class);
});
