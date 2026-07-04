<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeviceManufacturer;
use App\Models\MobileSentrixDevice;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'mobilesentrix.env' => 'staging',
        'mobilesentrix.base_url' => 'https://preprod.mobilesentrix.ca',
        'mobilesentrix.consumer_name' => 'Eclise Test',
        'mobilesentrix.consumer_key' => 'consumer-key',
        'mobilesentrix.consumer_secret' => 'consumer-secret',
        'mobilesentrix.access_token' => 'access-token',
        'mobilesentrix.access_token_secret' => 'access-secret',
        'mobilesentrix.auth_transport' => 'oauth_header',
        'mobilesentrix.timeout' => 120,
        'mobilesentrix.connect_timeout' => 20,
        'services.stripe.secret' => null,
        'services.paypal.client_id' => null,
        'services.paypal.secret' => null,
    ]);
});

function cpoTestUser(string $email, string $permissionName = 'customer'): User
{
    return User::query()->create([
        'name' => ucfirst($permissionName).' User',
        'email' => $email,
        'password' => 'password',
        'role' => $permissionName === 'admin' ? 'admin' : 'customer',
        'permission_id' => Permission::query()->where('name', $permissionName)->where('status', 'active')->value('id'),
        'status' => 'active',
    ]);
}

test('mobile sentrix device sync stores raw payload and lookup data without duplicates', function () {
    $rawDevice = [
        'entity_id' => 73001,
        'sku' => 'CPO-IPHONE-14-128',
        'name' => 'Apple iPhone 14 128GB Blue Certified Pre-Owned',
        'manufacturer_text' => 'Apple',
        'device_model_text' => 'iPhone 14',
        'device_color_text' => 'Blue',
        'condition_text' => 'Excellent',
        'device_carrier_text' => 'Unlocked',
        'device_size_text' => '128GB',
        'device_grade_text' => 'A',
        'available_qty' => 7,
        'price' => '629.99',
        'product_type' => 'devicesystem',
        'image_url' => 'https://cdn.example.test/iphone-14-blue.jpg',
        'nested_payload' => ['kept' => true],
    ];

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/products*' => Http::sequence()
            ->push(['data' => ['items' => [$rawDevice], 'page_info' => ['current_page' => 1, 'total_pages' => 1]]])
            ->push(['data' => ['items' => [$rawDevice], 'page_info' => ['current_page' => 1, 'total_pages' => 1]]]),
    ]);

    $this->artisan('mobilesentrix:sync-devices', ['--limit' => 30, '--page' => 1])->assertSuccessful();
    $this->artisan('mobilesentrix:sync-devices', ['--limit' => 30, '--page' => 1])->assertSuccessful();

    $device = MobileSentrixDevice::query()->firstOrFail();

    expect(MobileSentrixDevice::query()->count())->toBe(1)
        ->and($device->entity_id)->toBe(73001)
        ->and($device->sku)->toBe('CPO-IPHONE-14-128')
        ->and($device->raw_payload == $rawDevice)->toBeTrue()
        ->and(DeviceManufacturer::query()->where('slug', 'apple')->exists())->toBeTrue();

    Http::assertSent(function ($request): bool {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return str_contains($request->url(), '/api/rest/products')
            && ($query['product_type'] ?? null) === 'devicesystem'
            && ($query['pageinfo'] ?? null) === '1';
    });
});

test('customer device listing only shows available devices and admin listing has no cart controls', function () {
    $available = MobileSentrixDevice::query()->create([
        'entity_id' => 88001,
        'sku' => 'AVAILABLE-CPO',
        'name' => 'Available Certified Device',
        'manufacturer_text' => 'Apple',
        'device_model_text' => 'iPhone 15',
        'device_size_text' => '256GB',
        'device_color_text' => 'Black',
        'condition_text' => 'Good',
        'device_carrier_text' => 'Unlocked',
        'available_qty' => 3,
        'price' => 699,
        'status' => 'active',
    ]);

    MobileSentrixDevice::query()->create([
        'entity_id' => 88002,
        'sku' => 'UNAVAILABLE-CPO',
        'name' => 'Unavailable Certified Device',
        'manufacturer_text' => 'Samsung',
        'device_model_text' => 'Galaxy S24',
        'available_qty' => 0,
        'price' => 599,
        'status' => 'active',
    ]);

    $this->get(route('shop.certified-pre-owned-devices.index'))
        ->assertOk()
        ->assertSee($available->device_model_text)
        ->assertSee('Add To Cart')
        ->assertDontSee('Galaxy S24');

    $this->actingAs(cpoTestUser('cpo-admin@example.com', 'admin'))
        ->get(route('admin.devices.index'))
        ->assertOk()
        ->assertSee('MobileSentrix Devices')
        ->assertSee('iPhone 15')
        ->assertSee('Galaxy S24')
        ->assertDontSee('Add To Cart');
});

test('cart stores eclise and mobile sentrix items with distinct source identifiers', function () {
    $customer = cpoTestUser('cpo-cart@example.com');
    $product = Product::query()->create([
        'name' => 'Retail Phone',
        'slug' => 'retail-phone',
        'sku' => 'RETAIL-1',
        'condition' => 'New',
        'price' => 400,
        'quantity' => 5,
        'status' => 'Active',
    ]);
    $device = MobileSentrixDevice::query()->create([
        'entity_id' => 99001,
        'sku' => 'CPO-CART-1',
        'name' => 'CPO Cart Device',
        'available_qty' => 2,
        'price' => 250,
        'status' => 'active',
    ]);

    $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1])->assertRedirect();
    $this->actingAs($customer)->post(route('cart.devices.store', $device), ['quantity' => 2])->assertRedirect();

    $cart = Cart::query()->where('user_id', $customer->id)->where('status', 'active')->firstOrFail();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => 'ecl'.$product->id,
        'item_source' => CartItem::SOURCE_ECLISE,
        'quantity' => 1,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => '99001',
        'item_source' => CartItem::SOURCE_MOBILESENTRIX,
        'quantity' => 2,
    ]);
});

test('checkout creates mixed order items for retail and certified pre owned devices', function () {
    $this->seed(ShippingSeeder::class);

    $customer = cpoTestUser('cpo-checkout@example.com');
    $product = Product::query()->create([
        'name' => 'Checkout Retail Phone',
        'slug' => 'checkout-retail-phone',
        'sku' => 'CHECKOUT-RETAIL',
        'condition' => 'New',
        'price' => 100,
        'quantity' => 5,
        'status' => 'Active',
    ]);
    $device = MobileSentrixDevice::query()->create([
        'entity_id' => 99002,
        'sku' => 'CHECKOUT-CPO',
        'name' => 'Checkout CPO Device',
        'available_qty' => 2,
        'price' => 250,
        'status' => 'active',
    ]);
    $cart = Cart::query()->create(['user_id' => $customer->id, 'status' => 'active']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
    $cart->items()->create([
        'product_id' => $device->cartProductId(),
        'item_source' => CartItem::SOURCE_MOBILESENTRIX,
        'quantity' => 1,
        'unit_price' => 250,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'customer_name' => 'CPO Checkout',
            'email' => 'cpo-checkout@example.com',
            'phone' => '416-555-2000',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    $order = Order::query()->with('items')->firstOrFail();

    expect((float) $order->subtotal)->toBe(350.0)
        ->and((float) $order->total)->toBe(395.5)
        ->and($order->items)->toHaveCount(2);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => 'ecl'.$product->id,
        'item_source' => CartItem::SOURCE_ECLISE,
        'sku' => 'CHECKOUT-RETAIL',
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => '99002',
        'item_source' => CartItem::SOURCE_MOBILESENTRIX,
        'sku' => 'CHECKOUT-CPO',
    ]);
});
