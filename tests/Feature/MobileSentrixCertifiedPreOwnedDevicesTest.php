<?php

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use App\Models\User;
use App\Services\PaymentFinalizer;
use App\Support\MobileSentrixDeviceFilters;
use Database\Seeders\ShippingSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Mail::fake();
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

test('mobile sentrix device sync stores direct fields without creating lookup data', function () {
    $lookupCounts = [
        'product_brands' => ProductBrand::query()->count(),
        'product_models' => ProductModel::query()->count(),
        'product_sizes' => ProductSize::query()->count(),
        'product_conditions' => ProductCondition::query()->count(),
        'product_colors' => ProductColor::query()->count(),
        'product_networks' => ProductNetwork::query()->count(),
    ];

    $rawDevice = [
        'entity_id' => 73001,
        'sku' => 'CPO-IPHONE-14-128',
        'name' => 'Apple iPhone 14 128GB Blue Certified Pre-Owned',
        'manufacturer_text' => 'Apple',
        'model_text' => 'iPhone 14',
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
        ->and($device->device_model_text)->toBe('iPhone 14')
        ->and($device->raw_payload == $rawDevice)->toBeTrue()
        ->and(Schema::hasTable('device_manufacturers'))->toBeFalse()
        ->and(Schema::hasTable('device_models'))->toBeFalse()
        ->and(Schema::hasColumn('mobilesentrix_devices', 'device_manufacturer_id'))->toBeFalse()
        ->and(Schema::hasColumn('mobilesentrix_devices', 'device_model_id'))->toBeFalse();

    expect(ProductBrand::query()->count())->toBe($lookupCounts['product_brands'])
        ->and(ProductModel::query()->count())->toBe($lookupCounts['product_models'])
        ->and(ProductSize::query()->count())->toBe($lookupCounts['product_sizes'])
        ->and(ProductCondition::query()->count())->toBe($lookupCounts['product_conditions'])
        ->and(ProductColor::query()->count())->toBe($lookupCounts['product_colors'])
        ->and(ProductNetwork::query()->count())->toBe($lookupCounts['product_networks']);

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
        ->assertSee('TOTAL: <span data-cpo-selected-total>CA$0.00</span>', false)
        ->assertSee('data-auth-required', false)
        ->assertDontSee('Galaxy S24');

    $this->actingAs(cpoTestUser('cpo-admin@example.com', 'admin'))
        ->get(route('admin.devices.index'))
        ->assertOk()
        ->assertSee('MobileSentrix Devices')
        ->assertSee('iPhone 15')
        ->assertSee('Galaxy S24')
        ->assertDontSee('Add To Cart');
});

test('certified pre owned devices default to database price order with null prices last', function () {
    foreach ([
        [88201, 'Final Price Device', null, 50, null],
        [88202, 'Direct Price Device', 100, null, null],
        [88203, 'Regular Price Device', null, null, 200],
        [88204, 'No Price Device', null, null, null],
    ] as [$entityId, $name, $price, $finalPrice, $regularPrice]) {
        MobileSentrixDevice::query()->create([
            'entity_id' => $entityId,
            'sku' => 'SORT-'.$entityId,
            'name' => $name,
            'manufacturer_text' => 'Test',
            'device_model_text' => $name,
            'available_qty' => 1,
            'price' => $price,
            'final_price' => $finalPrice,
            'regular_price' => $regularPrice,
            'status' => 'active',
        ]);
    }

    $filters = app(MobileSentrixDeviceFilters::class);

    expect($filters->query(Request::create('/', 'GET'), customer: true)->pluck('name')->all())
        ->toBe([
            'Final Price Device',
            'Direct Price Device',
            'Regular Price Device',
            'No Price Device',
        ])
        ->and($filters->query(Request::create('/', 'GET', ['price_sort' => 'price_desc']), customer: true)->pluck('name')->all())
        ->toBe([
            'Regular Price Device',
            'Direct Price Device',
            'Final Price Device',
            'No Price Device',
        ])
        ->and($filters->query(Request::create('/', 'GET'), customer: true)->paginate(2, ['*'], 'page', 1)->pluck('name')->all())
        ->toBe(['Final Price Device', 'Direct Price Device'])
        ->and($filters->query(Request::create('/', 'GET'), customer: true)->paginate(2, ['*'], 'page', 2)->pluck('name')->all())
        ->toBe(['Regular Price Device', 'No Price Device']);

    $this->get(route('shop.certified-pre-owned-devices.index'))
        ->assertOk()
        ->assertSee('<option value="price_asc" selected>Low to High</option>', false)
        ->assertSeeInOrder([
            'Final Price Device',
            'Direct Price Device',
            'Regular Price Device',
            'No Price Device',
        ]);
});

test('active device filter chips render accessible individual remove buttons', function () {
    MobileSentrixDevice::query()->create([
        'entity_id' => 88010,
        'sku' => 'FILTER-CHIP-CPO',
        'name' => 'Filter Chip Device',
        'manufacturer_text' => 'Apple',
        'device_model_text' => 'iPhone 6',
        'device_size_text' => '16GB',
        'device_color_text' => 'Black',
        'condition_text' => 'Good',
        'device_carrier_text' => 'Unlocked',
        'available_qty' => 2,
        'price' => 200,
        'status' => 'active',
    ]);

    $response = $this->get(route('shop.certified-pre-owned-devices.index', [
        'device_model_text' => ['iPhone 6'],
        'device_size_text' => ['16GB'],
        'device_color_text' => ['Gold', 'Black', 'Green'],
        'device_carrier_text' => ['GSM', 'USA Regional', 'WiFi Version'],
        'price_min' => 100,
        'price_max' => 300,
    ]));

    $response->assertOk()
        ->assertSee('data-cpo-remove-filter', false)
        ->assertSee('data-cpo-active-filter-group="device_color_text"', false)
        ->assertSee('data-cpo-active-filter-group="device_carrier_text"', false)
        ->assertSee('data-filter-type="column"', false)
        ->assertSee('data-filter-type="price_min"', false)
        ->assertSee('data-filter-type="price_max"', false)
        ->assertSee('aria-label="Remove Model iPhone 6 filter"', false)
        ->assertSee('aria-label="Remove Size 16GB filter"', false)
        ->assertSee('aria-label="Remove Color Gold filter"', false)
        ->assertSee('aria-label="Remove Color Black filter"', false)
        ->assertSee('aria-label="Remove Color Green filter"', false);

    expect(substr_count($response->getContent(), 'class="cpo-active-filter-title">Color :'))->toBe(1)
        ->and(substr_count($response->getContent(), 'class="cpo-active-filter-title">Carrier :'))->toBe(1);
});

test('individual device filter removal states preserve sibling and price filters', function () {
    foreach ([
        [88101, 'Black', '16GB', 150],
        [88102, 'Gray', '16GB', 250],
        [88103, 'Gray', '16GB', 350],
    ] as [$entityId, $color, $size, $price]) {
        MobileSentrixDevice::query()->create([
            'entity_id' => $entityId,
            'sku' => 'FILTER-'.$entityId,
            'name' => $color.' Filter Device',
            'manufacturer_text' => 'Apple',
            'device_model_text' => 'iPhone 6',
            'device_size_text' => $size,
            'device_color_text' => $color,
            'condition_text' => 'Good',
            'device_carrier_text' => 'Unlocked',
            'available_qty' => 2,
            'price' => $price,
            'status' => 'active',
        ]);
    }

    $headers = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $afterBlackRemoval = $this->get(route('shop.certified-pre-owned-devices.index', [
        'device_color_text' => ['Gray'],
        'device_size_text' => ['16GB'],
        'price_min' => 100,
        'price_max' => 300,
    ]), $headers);

    $afterBlackRemoval->assertOk()->assertJsonPath('total', 1);
    expect($afterBlackRemoval->json('chips_html'))
        ->toContain('Gray')
        ->toContain('Size :')
        ->toContain('Min price :')
        ->toContain('Max price :')
        ->not->toContain('data-filter-value="Black"');

    $afterMinRemoval = $this->get(route('shop.certified-pre-owned-devices.index', [
        'device_color_text' => ['Gray'],
        'price_max' => 300,
    ]), $headers);

    $afterMinRemoval->assertOk()->assertJsonPath('total', 1);
    expect($afterMinRemoval->json('chips_html'))
        ->toContain('Max price :')
        ->not->toContain('Min price :');

    $afterMaxRemoval = $this->get(route('shop.certified-pre-owned-devices.index', [
        'device_color_text' => ['Gray'],
        'price_min' => 100,
    ]), $headers);

    $afterMaxRemoval->assertOk()->assertJsonPath('total', 2);
    expect($afterMaxRemoval->json('chips_html'))
        ->toContain('Min price :')
        ->not->toContain('Max price :');

    $this->get(route('shop.certified-pre-owned-devices.index'))
        ->assertOk()
        ->assertDontSee('data-cpo-active-filter', false);
});

test('cart stores eclise and mobile sentrix items with distinct source identifiers', function () {
    $customer = cpoTestUser('cpo-cart@example.com');
    $product = Product::query()->create([
        'name' => 'Retail Phone',
        'slug' => 'retail-phone',
        'sku' => 'RETAIL-1',
        'regular_price' => 400,
        'quantity' => 5,
        'is_active' => true,
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
    $this->actingAs($customer)->post(route('cart.devices.bulk'), ['devices' => [$device->id => 2]])->assertRedirect();

    $cart = Customer::forUser($customer)->activeCart()->firstOrFail();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'source_id' => $product->id,
        'source_sku' => $product->sku,
        'source' => CartItem::SOURCE_ECLISE,
        'quantity' => 1,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'source_id' => 99001,
        'source_sku' => $device->sku,
        'source' => CartItem::SOURCE_MOBILESENTRIX,
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
        'regular_price' => 100,
        'quantity' => 5,
        'is_active' => true,
    ]);
    $device = MobileSentrixDevice::query()->create([
        'entity_id' => 99002,
        'sku' => 'CHECKOUT-CPO',
        'name' => 'Checkout CPO Device',
        'available_qty' => 2,
        'price' => 250,
        'status' => 'active',
    ]);
    $cart = Customer::forUser($customer)->carts()->create(['status' => 'active']);
    $cart->items()->create([
        'source_id' => $product->id,
        'source_sku' => $product->sku,
        'source' => CartItem::SOURCE_ECLISE,
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $cart->items()->create([
        'source_id' => $device->entity_id,
        'source_sku' => $device->sku,
        'source' => CartItem::SOURCE_MOBILESENTRIX,
        'quantity' => 1,
        'unit_price' => 250,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'full_name' => 'CPO Checkout',
            'email' => 'cpo-checkout@example.com',
            'phone' => '416-555-2000',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    expect(Order::query()->count())->toBe(0);
    app(PaymentFinalizer::class)->markPaid(Payment::query()->firstOrFail());
    $order = Order::query()->with('items')->firstOrFail();

    expect((float) $order->subtotal)->toBe(350.0)
        ->and((float) $order->total)->toBe(395.5)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('source', CartItem::SOURCE_ECLISE)->display_name)->toBe('Checkout Retail Phone')
        ->and($order->items->firstWhere('source', CartItem::SOURCE_MOBILESENTRIX)->display_name)->toBe('Checkout CPO Device');

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'source_id' => $product->id,
        'source' => CartItem::SOURCE_ECLISE,
        'source_sku' => 'CHECKOUT-RETAIL',
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'source_id' => 99002,
        'source' => CartItem::SOURCE_MOBILESENTRIX,
        'source_sku' => 'CHECKOUT-CPO',
    ]);
});
