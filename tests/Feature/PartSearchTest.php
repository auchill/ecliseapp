<?php

use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PartModel;
use App\Models\Permission;
use App\Models\User;

function createSearchPart(array $overrides = []): Part
{
    $brand = $overrides['partBrand'] ?? PartBrand::query()->create([
        'name' => 'Apple',
        'slug' => 'apple',
        'is_active' => true,
        'status' => 'active',
    ]);
    $category = $overrides['partCategory'] ?? PartCategory::query()->create([
        'name' => 'Screens',
        'slug' => 'screens',
        'is_active' => true,
        'status' => 'active',
    ]);
    $model = $overrides['partModel'] ?? PartModel::query()->create([
        'name' => 'iPhone 15',
        'slug' => 'iphone-15',
        'status' => 'active',
    ]);

    unset($overrides['partBrand'], $overrides['partCategory'], $overrides['partModel']);

    $part = Part::query()->create(array_merge([
        'part_brand_id' => $brand->id,
        'part_category_id' => $category->id,
        'part_model_id' => $model->id,
        'mobilesentrix_product_id' => 'MSP-1001',
        'name' => 'iPhone 15 OLED Screen',
        'slug' => 'iphone-15-oled-screen',
        'sku' => 'MS-1001',
        'new_sku' => 'NEW-1001',
        'device_type' => 'Front',
        'brand' => $brand->name,
        'manufacturer_text' => $brand->name,
        'model_compatibility' => $model->name,
        'model_text' => [$model->name],
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
        ->and($html)->toContain('MS-1001')
        ->and($html)->toContain('$65.00')
        ->and($html)->not->toContain('$50.00')
        ->and($html)->not->toContain('access_token')
        ->and($html)->not->toContain('MSP-1001');
});

test('public parts autocomplete suggests parts skus brands models and categories', function () {
    createSearchPart();

    $response = $this->getJson(route('parts.suggestions', ['q' => 'iPhone']));

    $response->assertOk();

    $labels = collect($response->json('suggestions'))->pluck('label');

    expect($labels)->toContain('iPhone 15 OLED Screen')
        ->and($labels)->toContain('iPhone 15');
});

test('admin parts search can find mobilesentrix ids and show admin-only cost', function () {
    createSearchPart();
    $admin = partSearchAdminUser('parts-search-admin@example.com');

    $response = $this->actingAs($admin)->getJson(route('admin.parts.search', [
        'q' => 'MSP-1001',
        'api_status' => 'active',
        'status' => 'active',
    ]));

    $response->assertOk()
        ->assertJsonPath('count', 1);

    $html = $response->json('html');

    expect($html)->toContain('iPhone 15 OLED Screen')
        ->and($html)->toContain('MS ID: MSP-1001')
        ->and($html)->toContain('$50.00')
        ->and($html)->toContain('$65.00')
        ->and($html)->not->toContain('should-never-render');
});
