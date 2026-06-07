<?php

use App\Mail\OrderStatusUpdatedMail;
use App\Mail\RepairStatusUpdatedMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\ShippingDiscountRule;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(ShippingSeeder::class);
});

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
        ->and($order->shipping_method_id)->toBeNull()
        ->and((float) $order->shipping_base_cost)->toBe(0.0)
        ->and((float) $order->shipping_discount_amount)->toBe(0.0)
        ->and((float) $order->shipping_cost)->toBe(0.0)
        ->and((float) $order->total)->toBe(113.0);
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

    $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Shipping Customer',
            'email' => 'shipping@example.com',
            'phone' => '416-555-0002',
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

    $order = Order::query()->firstOrFail();

    expect($order->fulfillment_method)->toBe('shipping')
        ->and($order->shipping_method_id)->toBe($method->id)
        ->and($order->shipping_method_name)->toBe('Normal Shipping')
        ->and($order->shipping_delivery_days)->toBe('3-7 days')
        ->and((float) $order->shipping_base_cost)->toBe(20.0)
        ->and((float) $order->shipping_discount_amount)->toBe(0.0)
        ->and((float) $order->shipping_cost)->toBe(20.0)
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

    $normalCart = Cart::query()->create(['user_id' => $normalCustomer->id, 'status' => 'active']);
    $normalCart->items()->create(['product_id' => $normalProduct->id, 'quantity' => 1, 'unit_price' => 350]);

    $this->actingAs($normalCustomer)
        ->post(route('checkout.store'), [
            'customer_name' => 'Free Shipping Customer',
            'email' => 'free-shipping@example.com',
            'phone' => '416-555-0101',
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

    $overnightCart = Cart::query()->create(['user_id' => $overnightCustomer->id, 'status' => 'active']);
    $overnightCart->items()->create(['product_id' => $overnightProduct->id, 'quantity' => 1, 'unit_price' => 600]);

    $this->actingAs($overnightCustomer)
        ->post(route('checkout.store'), [
            'customer_name' => 'Overnight Customer',
            'email' => 'overnight@example.com',
            'phone' => '416-555-0102',
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

    $repair = RepairBooking::query()->firstOrFail();

    expect($repair->fulfillment_method)->toBe('shipping')
        ->and($repair->shipping_method_name)->toBe('Overnight Shipping')
        ->and($repair->shipping_delivery_days)->toBe('1 day')
        ->and((float) $repair->shipping_base_cost)->toBe(45.0)
        ->and((float) $repair->shipping_discount_amount)->toBe(0.0)
        ->and((float) $repair->shipping_cost)->toBe(45.0)
        ->and((float) $repair->repair_total)->toBe(45.0);

    $this->post(route('repairs.track.submit'), [
        'tracking_number' => $repair->tracking_number,
        'contact' => 'repair@example.com',
    ])->assertOk()
        ->assertSee($repair->tracking_number)
        ->assertSee('Overnight Shipping')
        ->assertSee('Final shipping');
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
