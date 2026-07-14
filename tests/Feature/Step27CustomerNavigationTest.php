<?php

use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use Illuminate\Support\Str;

function step27LookupProduct(string $name, array $overrides = []): Product
{
    $brand = $overrides['brand'] ?? ProductBrand::query()->firstOrCreate(['slug' => 'apple'], [
        'name' => 'Apple',
        'status' => 'active',
    ]);
    $category = $overrides['category'] ?? ProductCategory::query()->firstOrCreate(['slug' => 'phones'], [
        'name' => 'Phones',
        'is_active' => true,
    ]);
    $model = $overrides['model'] ?? ProductModel::query()->firstOrCreate(['slug' => 'iphone-15'], [
        'name' => 'iPhone 15',
        'product_brand_id' => $brand->id,
        'status' => 'active',
    ]);
    $condition = $overrides['condition'] ?? ProductCondition::query()->firstOrCreate(['slug' => 'excellent'], [
        'name' => 'Excellent',
        'status' => 'active',
    ]);
    $grade = $overrides['grade'] ?? ProductGrade::query()->firstOrCreate(['slug' => 'grade-a'], [
        'name' => 'Grade A',
        'status' => 'active',
    ]);
    $color = $overrides['color'] ?? ProductColor::query()->firstOrCreate(['slug' => 'blue'], [
        'name' => 'Blue',
        'status' => 'active',
    ]);
    $network = $overrides['network'] ?? ProductNetwork::query()->firstOrCreate(['slug' => 'unlocked'], [
        'name' => 'Unlocked',
        'status' => 'active',
    ]);
    $size = $overrides['size'] ?? ProductSize::query()->firstOrCreate(['slug' => 'storage-128gb'], [
        'name' => '128GB',
        'type' => 'storage',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'product_brand_id' => $brand->id,
        'product_model_id' => $model->id,
        'product_condition_id' => $condition->id,
        'product_grade_id' => $grade->id,
        'product_color_id' => $color->id,
        'product_network_id' => $network->id,
        'name' => $name,
        'slug' => $overrides['slug'] ?? Str::slug($name),
        'sku' => $overrides['sku'] ?? strtoupper(Str::slug($name, '-')),
        'regular_price' => $overrides['regular_price'] ?? 500,
        'sale_price' => $overrides['sale_price'] ?? null,
        'quantity' => $overrides['quantity'] ?? 2,
        'source' => 'manual',
        'is_featured' => $overrides['is_featured'] ?? false,
        'is_active' => $overrides['is_active'] ?? true,
    ]);
    $product->sizes()->sync([$size->id]);

    return $product;
}

function step27PartCategory(int $id, string $name, ?int $parentId = null, int $level = 1): PartCategory
{
    return PartCategory::query()->create([
        'id' => $id,
        'name' => $name,
        'slug' => Str::slug($name.' '.$id),
        'parent_id' => $parentId,
        'level' => $level,
        'has_children' => false,
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);
}

test('shop products use dynamic sidebar filters and preserve three card columns', function () {
    $appleBrand = ProductBrand::query()->create(['name' => 'Apple', 'slug' => 'apple', 'status' => 'active']);
    $samsungBrand = ProductBrand::query()->create(['name' => 'Samsung', 'slug' => 'samsung', 'status' => 'active']);
    $category = ProductCategory::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
    $appleModel = ProductModel::query()->create(['name' => 'iPhone 15', 'slug' => 'iphone-15', 'product_brand_id' => $appleBrand->id, 'status' => 'active']);
    $samsungModel = ProductModel::query()->create(['name' => 'Galaxy S24', 'slug' => 'galaxy-s24', 'product_brand_id' => $samsungBrand->id, 'status' => 'active']);

    step27LookupProduct('Apple Premium Phone', [
        'brand' => $appleBrand,
        'category' => $category,
        'model' => $appleModel,
        'regular_price' => 600,
    ]);
    step27LookupProduct('Apple Budget Phone', [
        'brand' => $appleBrand,
        'category' => $category,
        'model' => $appleModel,
        'regular_price' => 300,
    ]);
    step27LookupProduct('Samsung Retail Phone', [
        'brand' => $samsungBrand,
        'category' => $category,
        'model' => $samsungModel,
        'regular_price' => 450,
    ]);

    $response = $this->get(route('shop.index', ['brands' => ['apple'], 'sort' => 'price_asc']));

    $response->assertOk()
        ->assertSee('shop-filter-sidebar', false)
        ->assertSee('Apple')
        ->assertSee('Apple Budget Phone')
        ->assertSee('Apple Premium Phone')
        ->assertDontSee('Samsung Retail Phone')
        ->assertSee('shop-filter-chip')
        ->assertSee('name="brands[]"', false)
        ->assertDontSee('name="model"', false)
        ->assertDontSee('name="size"', false)
        ->assertSee('col-12 col-sm-6 col-lg-4', false)
        ->assertSee('offcanvas offcanvas-start', false)
        ->assertSeeInOrder(['Apple Budget Phone', 'Apple Premium Phone']);
});

test('customer breadcrumb renders nested parts category hierarchy', function () {
    $replacement = step27PartCategory(165, 'Replacement Parts');
    $apple = step27PartCategory(756, 'Apple', $replacement->id, 2);
    $iphone = step27PartCategory(166, 'iPhone', $apple->id, 3);
    $screen = step27PartCategory(16490, 'Screen', $iphone->id, 4);

    $part = Part::query()->create([
        'id' => 270001,
        'category_ids' => [(string) $screen->id],
        'name' => 'iPhone 15 Display Assembly',
        'slug' => 'iphone-15-display-assembly',
        'sku' => 'STEP27-PART',
        'price' => 100,
        'quantity' => 2,
        'in_stock_qty' => 2,
        'is_in_stock' => true,
        'stock_status' => 'In stock',
        'is_api_item' => false,
        'is_active' => true,
        'status' => 'active',
    ]);
    $part->categories()->sync([$screen->id]);

    $response = $this->get(route('parts.show', $part));

    $response->assertOk()
        ->assertSee('aria-label="breadcrumb"', false)
        ->assertSee('breadcrumb-scroll', false)
        ->assertSeeInOrder(['Home', 'Parts', 'Replacement Parts', 'Apple', 'iPhone', 'Screen', 'iPhone 15 Display Assembly'])
        ->assertSee('aria-current="page"', false);
});
