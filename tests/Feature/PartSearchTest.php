<?php

use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createSearchPart(array $overrides = []): Part
{
    $category = $overrides['partCategory'] ?? PartCategory::query()->firstOrCreate(
        ['slug' => 'screens'],
        ['name' => 'Screens', 'is_active' => true, 'status' => 'active'],
    );

    unset($overrides['partCategory']);

    $part = Part::query()->create(array_merge([
        'id' => 1001,
        'category_ids' => [(string) $category->id],
        'name' => 'iPhone 15 OLED Screen',
        'slug' => 'iphone-15-oled-screen',
        'sku' => 'MS-1001',
        'new_sku' => 'NEW-1001',
        'device_type' => 'Front',
        'brand' => 'Apple',
        'manufacturer_text' => 'Apple',
        'model_compatibility' => 'iPhone 15',
        'model_text' => ['iPhone 15'],
        'part_category' => $category->name,
        'description' => 'Premium OLED display replacement.',
        'price' => 50,
        'cost_price' => 50,
        'selling_price' => 65,
        'final_price' => 65,
        'quantity' => 4,
        'in_stock_qty' => 4,
        'is_in_stock' => true,
        'stock_status' => 'In stock',
        'availability_status' => 'In stock',
        'supplier' => 'MobileSentrix',
        'is_api_item' => true,
        'is_active' => true,
        'status' => 'active',
        'api_status' => 'active',
        'raw_payload' => ['access_token' => 'should-never-render'],
    ], $overrides));

    $part->partCategories()->syncWithoutDetaching([$category->id]);

    return $part;
}

function partSearchAdminUser(string $email): User
{
    $adminPermission = Permission::query()->where('name', 'admin')->firstOrFail();

    return User::query()->create([
        'name' => 'Parts Admin',
        'email' => $email,
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => $adminPermission->id,
        'status' => 'active',
    ]);
}

test('admin part creation stores raw category ids and direct pivot rows', function () {
    $admin = partSearchAdminUser('create-part-category-ids@example.com');

    $this->actingAs($admin)
        ->post(route('admin.parts.store'), [
            'name' => 'Direct Category Part',
            'device_type' => 'Phone',
            'brand' => 'Eclise',
            'category_ids' => '777,888,777',
            'price' => 25,
            'quantity' => 2,
            'stock_status' => 'In stock',
            'supplier' => 'Manual',
        ])
        ->assertRedirect(route('admin.parts.index'));

    $part = Part::query()->where('name', 'Direct Category Part')->firstOrFail();

    expect($part->category_ids)->toBe(['777', '888'])
        ->and(DB::table('part_category_part')->where('part_id', $part->id)->pluck('category_id')->sort()->values()->all())->toBe([777, 888])
        ->and(PartCategory::query()->whereIn('id', [777, 888])->exists())->toBeFalse();
});

test('public parts search returns dynamic results without exposing supplier cost or raw data', function () {
    createSearchPart();

    $response = $this->getJson(route('parts.search', [
        'q' => 'NEW-1001',
        'stock' => 'in',
        'min_price' => '60',
        'max_price' => '70',
    ]));

    $response->assertOk()
        ->assertJsonPath('count', 1);

    $html = $response->json('html');

    expect($html)->toContain('iPhone 15 OLED Screen')
        ->and($html)->toContain('row g-3 parts-card-grid')
        ->and($html)->toContain('col-12 col-sm-6 col-lg-4 parts-card-column')
        ->and($html)->toContain('parts-card-image-wrap')
        ->and($html)->toContain('parts-card-image')
        ->and($html)->toContain('parts-card-actions')
        ->and($html)->toContain('MS-1001')
        ->and($html)->toContain('$65.00')
        ->and($html)->not->toContain('$50.00')
        ->and($html)->not->toContain('col-xl-3')
        ->and($html)->not->toContain('access_token')
        ->and($html)->not->toContain('MSP-1001');
});

test('public parts autocomplete suggests parts skus brands and direct models', function () {
    createSearchPart();

    $response = $this->getJson(route('parts.suggestions', ['q' => 'iPhone']));

    $response->assertOk();

    $labels = collect($response->json('suggestions'))->pluck('label');

    expect($labels)->toContain('iPhone 15 OLED Screen')
        ->and($labels)->toContain('iPhone 15');
});

test('parts brand filter uses direct distinct part values and category filter is absent', function () {
    createSearchPart();
    createSearchPart([
        'id' => 1002,
        'sku' => 'MS-1002',
        'new_sku' => 'NEW-1002',
        'name' => 'Samsung Screen',
        'slug' => 'samsung-screen',
        'brand' => 'Samsung',
        'manufacturer_text' => 'Samsung',
    ]);

    $response = $this->get(route('parts.index', ['brand' => 'Apple']));

    $response->assertOk()
        ->assertSee('iPhone 15 OLED Screen')
        ->assertDontSee('Samsung Screen')
        ->assertDontSee('name="part_category"', false);
});

test('admin parts search can find mobilesentrix ids and show admin-only cost', function () {
    createSearchPart();
    $admin = partSearchAdminUser('parts-search-admin@example.com');

    $response = $this->actingAs($admin)->getJson(route('admin.parts.search', [
        'q' => '1001',
        'api_status' => 'active',
        'status' => 'active',
    ]));

    $response->assertOk()
        ->assertJsonPath('count', 1);

    $html = $response->json('html');

    expect($html)->toContain('iPhone 15 OLED Screen')
        ->and($html)->toContain('MS ID: 1001')
        ->and($html)->toContain('$50.00')
        ->and($html)->toContain('$65.00')
        ->and($html)->not->toContain('should-never-render');
});
