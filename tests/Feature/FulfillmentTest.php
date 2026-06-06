<?php

use App\Mail\OrderStatusUpdatedMail;
use App\Mail\RepairStatusUpdatedMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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

    $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Pickup Customer',
            'email' => 'pickup@example.com',
            'phone' => '416-555-0001',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->fulfillment_method)->toBe('pickup')
        ->and((float) $order->shipping_cost)->toBe(0.0)
        ->and((float) $order->total)->toBe(113.0);
});

test('checkout shipping creates a shipping order with canada shipping cost', function () {
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

    $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Shipping Customer',
            'email' => 'shipping@example.com',
            'phone' => '416-555-0002',
            'fulfillment_method' => 'shipping',
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

    $order = Order::query()->firstOrFail();

    expect($order->fulfillment_method)->toBe('shipping')
        ->and((float) $order->shipping_cost)->toBe(20.0)
        ->and((float) $order->total)->toBe(133.0);

    $this->post(route('orders.track.result'), [
        'order_number' => $order->order_number,
        'contact' => 'shipping@example.com',
    ])->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Shipping');
});

test('repair shipping calculates shipping cost and appears in tracking', function () {
    $this->post(route('repairs.store'), [
        'customer_name' => 'Repair Customer',
        'email' => 'repair@example.com',
        'phone' => '416-555-0003',
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 13',
        'issue_category' => 'Screen',
        'issue_description' => 'The display is cracked and needs replacement.',
        'fulfillment_method' => 'shipping',
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

    $repair = RepairBooking::query()->firstOrFail();

    expect($repair->fulfillment_method)->toBe('shipping')
        ->and((float) $repair->shipping_cost)->toBe(20.0);

    $this->post(route('repairs.track.submit'), [
        'tracking_number' => $repair->tracking_number,
        'contact' => 'repair@example.com',
    ])->assertOk()
        ->assertSee($repair->tracking_number)
        ->assertSee('Shipping');
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
        'payment_provider' => 'square',
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
        'status' => 'Submitted',
        'fulfillment_method' => 'shipping',
        'shipping_cost' => 20,
        'repair_total' => 20,
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
            'status' => 'Shipped',
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
