<?php

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\EcliseMarkup;
use App\Models\MobileSentrixDevice;
use App\Models\Order;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\MobileSentrixMarkupService;

function markupCategory(int $id, string $name, ?int $parentId = null, int $level = 1): PartCategory
{
    return PartCategory::query()->create([
        'id' => $id,
        'name' => $name,
        'slug' => str($name.' '.$id)->slug(),
        'parent_id' => $parentId,
        'level' => $level,
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);
}

function markupPart(array $overrides = [], array $categoryIds = []): Part
{
    $id = $overrides['id'] ?? random_int(100000, 999999);

    $part = Part::query()->create(array_merge([
        'id' => $id,
        'name' => 'Markup Test Part',
        'slug' => 'markup-test-part-'.$id,
        'sku' => 'MARKUP-PART-'.random_int(1000, 9999),
        'api_price' => 100,
        'price' => 100,
        'cost_price' => 100,
        'quantity' => 3,
        'in_stock_qty' => 3,
        'is_active' => true,
        'status' => 'active',
        'is_api_item' => true,
        'supplier' => 'MobileSentrix',
        'category_ids' => array_map('strval', $categoryIds),
    ], $overrides));

    if ($categoryIds !== []) {
        $part->categories()->sync($categoryIds);
    }

    return $part->fresh('categories');
}

function markupDevice(array $overrides = []): MobileSentrixDevice
{
    return MobileSentrixDevice::query()->create(array_merge([
        'entity_id' => random_int(100000, 999999),
        'sku' => 'MARKUP-CPO-'.random_int(1000, 9999),
        'name' => 'Markup CPO Device',
        'manufacturer_text' => 'Apple',
        'device_model_text' => 'iPhone',
        'available_qty' => 2,
        'price' => 100,
        'status' => 'active',
        'raw_payload' => ['category_ids' => [123]],
    ], $overrides));
}

function markupRule(array $attributes): EcliseMarkup
{
    return EcliseMarkup::query()->create(array_merge([
        'item_type' => EcliseMarkup::ITEM_TYPE_PARTS,
        'scope_type' => EcliseMarkup::SCOPE_ALL,
        'category_id' => null,
        'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
        'markup_value' => 0,
        'priority' => 0,
        'is_active' => true,
    ], $attributes));
}

function markupUser(string $email, string $permission = 'customer'): User
{
    return User::query()->create([
        'name' => ucfirst($permission).' User',
        'email' => $email,
        'password' => 'password',
        'role' => $permission,
        'permission_id' => Permission::query()->where('name', $permission)->value('id'),
        'status' => 'active',
    ]);
}

test('global percentage and fixed markup calculate parts prices', function () {
    $service = app(MobileSentrixMarkupService::class);
    $part = markupPart();

    markupRule(['markup_type' => EcliseMarkup::MARKUP_PERCENTAGE, 'markup_value' => 20]);
    expect($service->calculatePartPrice($part)->selling_price)->toBe(120.0);

    EcliseMarkup::query()->get()->each->delete();
    markupRule(['markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 15]);

    expect($service->calculatePartPrice($part)->selling_price)->toBe(115.0);
});

test('category markup overrides global markup for parts', function () {
    $category = markupCategory(16490, 'Screens');
    $part = markupPart([], [$category->id]);

    markupRule(['markup_type' => EcliseMarkup::MARKUP_PERCENTAGE, 'markup_value' => 20]);
    markupRule([
        'scope_type' => EcliseMarkup::SCOPE_CATEGORY,
        'category_id' => $category->id,
        'markup_type' => EcliseMarkup::MARKUP_FIXED,
        'markup_value' => 15,
        'priority' => 5,
    ]);

    $result = app(MobileSentrixMarkupService::class)->calculatePartPrice($part);

    expect($result->selling_price)->toBe(115.0)
        ->and($result->applied_scope)->toBe(EcliseMarkup::SCOPE_CATEGORY);
});

test('multiple category rules use priority deterministically', function () {
    $low = markupCategory(2001, 'Apple', null, 1);
    $high = markupCategory(2002, 'iPhone 15', $low->id, 2);
    $part = markupPart([], [$low->id, $high->id]);

    markupRule(['scope_type' => EcliseMarkup::SCOPE_CATEGORY, 'category_id' => $low->id, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 5, 'priority' => 1]);
    $winning = markupRule(['scope_type' => EcliseMarkup::SCOPE_CATEGORY, 'category_id' => $high->id, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 25, 'priority' => 10]);

    $result = app(MobileSentrixMarkupService::class)->calculatePartPrice($part);

    expect($result->selling_price)->toBe(125.0)
        ->and($result->applied_rule_id)->toBe($winning->id);
});

test('pre owned devices use global and category markup rules', function () {
    $device = markupDevice(['raw_payload' => ['category_ids' => [123]]]);

    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 50]);
    expect(app(MobileSentrixMarkupService::class)->calculatePreOwnedDevicePrice($device)->selling_price)->toBe(150.0);

    EcliseMarkup::query()->get()->each->delete();
    markupRule([
        'item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES,
        'scope_type' => EcliseMarkup::SCOPE_CATEGORY,
        'category_id' => 123,
        'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
        'markup_value' => 12.5,
    ]);

    expect(app(MobileSentrixMarkupService::class)->calculatePreOwnedDevicePrice($device)->selling_price)->toBe(112.5);
});

test('inactive rules are ignored and invalid prices are handled', function () {
    $part = markupPart(['api_price' => null, 'price' => null, 'customer_price' => null, 'cost_price' => null]);
    markupRule(['markup_value' => 100, 'is_active' => false]);

    $result = app(MobileSentrixMarkupService::class)->calculatePartPrice($part);

    expect($result->base_price)->toBeNull()
        ->and($result->selling_price)->toBeNull()
        ->and(markupPart(['api_price' => 99.99, 'price' => 99.99])->displayPrice())->toBe(99.99);
});

test('markup calculation rounds to two decimals', function () {
    $part = markupPart(['api_price' => 99.99, 'price' => 99.99]);
    markupRule(['markup_value' => 12.5]);

    expect(app(MobileSentrixMarkupService::class)->calculatePartPrice($part)->selling_price)->toBe(112.49);
});

test('device cart unit price snapshots marked up customer price', function () {
    $user = markupUser('markup-customer@example.com');
    Customer::forUser($user);
    $device = markupDevice(['entity_id' => 900001, 'sku' => 'CART-MARKUP', 'price' => 100, 'available_qty' => 2]);
    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 25]);

    $this->actingAs($user)
        ->post(route('cart.devices.store', $device), ['quantity' => 1])
        ->assertRedirect();

    expect(CartItem::query()->firstOrFail()->unit_price)->toBe('125.00');
});

test('existing order item price remains unchanged after markup changes', function () {
    $user = markupUser('markup-order@example.com');
    $customer = Customer::forUser($user);
    $order = Order::query()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ECL-ORD-MARKUP-1',
        'subtotal' => 125,
        'tax' => 16.25,
        'total' => 141.25,
        'status' => 'Paid',
        'payment_status' => 'paid',
    ]);
    $order->items()->create([
        'source' => CartItem::SOURCE_MOBILESENTRIX,
        'source_id' => 900002,
        'source_sku' => 'ORDER-MARKUP',
        'quantity' => 1,
        'unit_price' => 125,
        'line_total' => 125,
    ]);

    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 75]);

    expect($order->items()->first()->unit_price)->toBe('125.00');
});

test('eclise owned products do not receive mobilesentrix markup', function () {
    $category = ProductCategory::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'name' => 'Eclise Product',
        'slug' => 'eclise-product',
        'sku' => 'ECLISE-PRODUCT',
        'regular_price' => 100,
        'sale_price' => 80,
        'quantity' => 1,
        'is_active' => true,
    ]);

    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES, 'markup_type' => EcliseMarkup::MARKUP_FIXED, 'markup_value' => 50]);

    expect($product->currentPrice())->toBe(80.0);
});

test('admin can manage mobilesentrix markup rules', function () {
    $admin = markupUser('markup-admin@example.com', 'admin');

    $this->actingAs($admin)
        ->get(route('admin.mobilesentrix-markups.index'))
        ->assertOk()
        ->assertSee('Price Markup')
        ->assertSee(route('admin.mobilesentrix-markups.create'), false);

    $this->actingAs($admin)
        ->post(route('admin.mobilesentrix-markups.store'), [
            'item_type' => EcliseMarkup::ITEM_TYPE_PARTS,
            'scope_type' => EcliseMarkup::SCOPE_ALL,
            'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
            'markup_value' => 20,
            'priority' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.mobilesentrix-markups.index'));

    expect(EcliseMarkup::query()->count())->toBe(1);
});

test('admin markup refresh reports rule counts without changing source prices', function () {
    $admin = markupUser('markup-refresh-admin@example.com', 'admin');
    $part = markupPart(['id' => 880001, 'api_price' => 100, 'price' => 100]);
    $device = markupDevice(['entity_id' => 880002, 'price' => 200]);
    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PARTS, 'markup_value' => 20]);
    markupRule(['item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES, 'markup_value' => 25]);

    $this->actingAs($admin)
        ->post(route('admin.mobilesentrix-markups.refresh'))
        ->assertRedirect(route('admin.mobilesentrix-markups.index'))
        ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Active Parts rules: 1')
            && str_contains($message, 'Active Pre-Owned Device rules: 1')
            && str_contains($message, 'Source prices modified: 0'));

    expect($part->fresh()->api_price)->toBe('100.0000')
        ->and($part->fresh()->price)->toBe('100.0000')
        ->and($device->fresh()->price)->toBe('200.00');
});

test('customers cannot access mobilesentrix markup management', function () {
    $customer = markupUser('markup-customer-denied@example.com');

    $this->actingAs($customer)
        ->get(route('admin.mobilesentrix-markups.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->post(route('admin.mobilesentrix-markups.refresh'))
        ->assertForbidden();
});
