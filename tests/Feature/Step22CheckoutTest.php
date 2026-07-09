<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentFinalizer;
use App\Support\CatalogImage;
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

function step22Customer(string $email = 'step22@example.com'): User
{
    return User::query()->create([
        'name' => 'Step 22 Customer',
        'email' => $email,
        'password' => 'password',
        'role' => 'customer',
        'status' => 'active',
    ]);
}

function step22Product(string $sku = 'STEP22-1', int $quantity = 3): Product
{
    return Product::query()->create([
        'name' => 'Step 22 Product',
        'slug' => strtolower($sku),
        'sku' => $sku,
        'condition' => 'New',
        'price' => 100,
        'quantity' => $quantity,
        'status' => 'Active',
    ]);
}

function step22Cart(User $user, Product $product): Cart
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

test('catalog models resolve missing invalid and MobileSentrix placeholder images to the grey logo', function () {
    $part = new Part;
    $product = new Product;

    expect($part->imageUrl())->toBe(CatalogImage::fallbackUrl())
        ->and($product->imageUrl())->toBe(CatalogImage::fallbackUrl());

    $part->image_url = CatalogImage::MOBILESENTRIX_PLACEHOLDER;
    expect($part->imageUrl())->toBe(CatalogImage::fallbackUrl());

    $part->image_url = 'not-a-valid-url';
    expect($part->imageUrl())->toBe(CatalogImage::fallbackUrl());
});

test('step 22 schema uses explicit source identity customer ownership and checkout snapshots', function () {
    expect(Schema::hasColumns('cart_items', ['source_id', 'source_sku', 'source', 'quantity', 'unit_price']))->toBeTrue()
        ->and(Schema::hasColumn('cart_items', 'product_id'))->toBeFalse()
        ->and(Schema::hasColumn('cart_items', 'item_source'))->toBeFalse()
        ->and(Schema::hasColumn('carts', 'customer_id'))->toBeTrue()
        ->and(Schema::hasColumn('carts', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumns('orders', ['customer_id', 'order_number']))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'source'))->toBeFalse()
        ->and(Schema::hasColumn('payments', 'checkout_data'))->toBeTrue()
        ->and(Schema::hasColumn('order_items', 'item_name'))->toBeFalse()
        ->and(Schema::hasColumn('order_items', 'image_url'))->toBeFalse()
        ->and(Schema::hasTable('customers'))->toBeTrue();
});

test('duplicate Eclise cart additions update one explicit source identity without a sku suffix', function () {
    $user = step22Customer('duplicate-cart@example.com');
    $product = step22Product('NO-ECL-SUFFIX');

    $this->actingAs($user)
        ->post(route('cart.store', $product), ['quantity' => 1])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('cart.store', $product), ['quantity' => 2])
        ->assertRedirect();

    $items = CartItem::query()->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()->source)->toBe(CartItem::SOURCE_ECLISE)
        ->and($items->first()->source_id)->toBe($product->id)
        ->and($items->first()->source_sku)->toBe('NO-ECL-SUFFIX')
        ->and($items->first()->quantity)->toBe(3);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('cart-action-badge badge rounded-pill bg-primary">3<', false);
});

test('verified shop payment creates customer order items and status then removes the cart', function () {
    $user = step22Customer('successful-checkout@example.com');
    $product = step22Product('SUCCESS-1');
    $cart = step22Cart($user, $product);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Updated Customer Name',
            'email' => 'successful-checkout@example.com',
            'phone' => '416-555-2200',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    expect(Order::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(1)
        ->and($cart->customer_id)->toBe($user->customer->id)
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeTrue();

    $payment = app(PaymentFinalizer::class)->markPaid(Payment::query()->firstOrFail(), [
        'gateway_reference_id' => 'step22-paid',
    ]);
    $order = Order::query()->with('items', 'statusUpdates', 'customer')->firstOrFail();

    expect($payment->payable->is($order))->toBeTrue()
        ->and($order->customer->user_id)->toBe($user->id)
        ->and($order->customer_id)->toBe($cart->customer_id)
        ->and($order->customer->full_name)->toBe('Updated Customer Name')
        ->and($order->order_number)->toMatch('/^ECL-ORD-\d{4}-\d{7}$/')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->source_sku)->toBe('SUCCESS-1')
        ->and($order->items->first()->display_name)->toBe('Step 22 Product')
        ->and($order->items->first()->display_image_url)->toBe(CatalogImage::fallbackUrl())
        ->and($order->statusUpdates->first()->status)->toBe('Paid')
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeFalse()
        ->and(CartItem::query()->where('cart_id', $cart->id)->exists())->toBeFalse();

    $product->delete();
    $unavailableItem = $order->items->first()->fresh();

    expect($unavailableItem->display_name)->toBe('Item unavailable')
        ->and($unavailableItem->display_image_url)->toBe(CatalogImage::fallbackUrl())
        ->and($unavailableItem->source_sku)->toBe('SUCCESS-1');
});

test('checkout prefills and updates one existing customer profile', function () {
    $user = step22Customer('existing-profile@example.com');
    $profile = Customer::query()->create([
        'user_id' => $user->id,
        'full_name' => 'Existing Profile',
        'email' => 'existing-profile@example.com',
        'phone' => '416-555-1100',
        'street_address' => '10 Existing Street',
        'city' => 'Toronto',
        'province' => 'ON',
        'postal_code' => 'M5V 1A1',
        'country' => 'Canada',
        'customer_since' => now()->subYear(),
        'status' => 'active',
    ]);
    step22Cart($user, step22Product('PROFILE-1'));

    $this->actingAs($user)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('Existing Profile')
        ->assertSee('10 Existing Street')
        ->assertSee('M5V 1A1');

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Updated Existing Profile',
            'email' => 'existing-profile@example.com',
            'phone' => '416-555-1199',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    app(PaymentFinalizer::class)->markPaid(Payment::query()->firstOrFail());

    expect(Customer::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($profile->fresh()->full_name)->toBe('Updated Existing Profile')
        ->and($profile->fresh()->phone)->toBe('416-555-1199')
        ->and($profile->fresh()->street_address)->toBe('10 Existing Street');
});

test('failed order creation rolls back customer and order records without deleting the cart', function () {
    $user = step22Customer('rollback-checkout@example.com');
    $product = step22Product('ROLLBACK-1', 1);
    $cart = step22Cart($user, $product);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'customer_name' => 'Rollback Customer',
            'email' => 'rollback-checkout@example.com',
            'phone' => '416-555-2299',
            'payment_gateway' => 'stripe',
            'fulfillment_method' => 'pickup',
        ])
        ->assertRedirect();

    $product->update(['quantity' => 0]);
    $payment = Payment::query()->firstOrFail();

    expect(fn () => app(PaymentFinalizer::class)->markPaid($payment))
        ->toThrow(RuntimeException::class, 'Insufficient inventory');

    expect(Order::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe('pending')
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeTrue()
        ->and(CartItem::query()->where('cart_id', $cart->id)->exists())->toBeTrue();
});
