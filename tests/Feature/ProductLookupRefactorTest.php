<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function productLookupAdminUser(string $email): User
{
    return User::query()->create([
        'name' => 'Product Lookup Admin',
        'email' => $email,
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => Permission::query()->where('name', 'admin')->where('status', 'active')->value('id'),
        'status' => 'active',
    ]);
}

test('step 26 product lookups use dedicated shop tables and mobile sentrix direct fields', function () {
    foreach ([
        'product_brands',
        'product_categories',
        'product_models',
        'product_sizes',
        'product_grades',
        'product_conditions',
        'product_colors',
        'product_networks',
        'product_product_size',
        'product_images',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach ([
        'categories',
        'device_brands',
        'device_models',
        'device_sizes',
        'device_manufacturers',
        'device_grades',
        'device_conditions',
        'device_colors',
        'device_carriers',
        'product_carriers',
        'productconditions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    foreach ([
        'device_grade_id',
        'device_size_id',
        'device_carrier_id',
        'device_condition_id',
        'device_color_id',
        'device_model_id',
        'device_manufacturer_id',
    ] as $column) {
        expect(Schema::hasColumn('mobilesentrix_devices', $column))->toBeFalse();
    }

    foreach ([
        'price',
        'brand',
        'model',
        'condition',
        'image_path',
        'status',
        'category_id',
        'product_size_id',
        'product_carrier_id',
    ] as $column) {
        expect(Schema::hasColumn('products', $column))->toBeFalse();
    }

    foreach (['status', 'code', 'source', 'description'] as $column) {
        expect(Schema::hasColumn('product_sizes', $column))->toBeFalse();
    }

    expect((new ProductCondition)->getTable())->toBe('product_conditions')
        ->and(Route::has('admin.product-sizes.index'))->toBeTrue()
        ->and(Route::has('admin.product-networks.index'))->toBeTrue()
        ->and(Route::has('admin.product-carriers.index'))->toBeFalse()
        ->and(Route::has('admin.device-sizes.index'))->toBeFalse();
});

test('products resolve all seven product lookup relationships', function () {
    $brand = ProductBrand::query()->create(['name' => 'Example Brand', 'slug' => 'example-brand', 'status' => 'active']);
    $category = ProductCategory::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
    $model = ProductModel::query()->create(['name' => 'Example Model', 'slug' => 'example-model', 'product_brand_id' => $brand->id, 'status' => 'active']);
    $size = ProductSize::query()->create(['name' => '256GB', 'slug' => '256gb', 'type' => 'storage', 'is_active' => true]);
    $grade = ProductGrade::query()->create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
    $condition = ProductCondition::query()->create(['name' => 'Refurbished', 'slug' => 'refurbished', 'status' => 'active']);
    $color = ProductColor::query()->create(['name' => 'Blue', 'slug' => 'blue', 'status' => 'active']);
    $network = ProductNetwork::query()->create(['name' => 'Unlocked', 'slug' => 'unlocked', 'status' => 'active']);

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'product_brand_id' => $brand->id,
        'product_model_id' => $model->id,
        'product_grade_id' => $grade->id,
        'product_condition_id' => $condition->id,
        'product_color_id' => $color->id,
        'product_network_id' => $network->id,
        'name' => 'Lookup Product',
        'slug' => 'lookup-product',
        'sku' => 'LOOKUP-1',
        'regular_price' => 100,
        'sale_price' => 90,
        'quantity' => 1,
        'is_active' => true,
    ]);
    $product->sizes()->sync([$size->id]);
    $image = $product->images()->create([
        'image_url' => 'https://example.test/product.jpg',
        'is_primary' => true,
    ]);

    $product->refresh()->load('category', 'productBrand', 'productModel', 'sizes', 'productGrade', 'productCondition', 'productColor', 'network', 'primaryImage');

    expect($product->category->is($category))->toBeTrue()
        ->and($product->productBrand->is($brand))->toBeTrue()
        ->and($product->productModel->is($model))->toBeTrue()
        ->and($product->sizes->first()->is($size))->toBeTrue()
        ->and($product->productGrade->is($grade))->toBeTrue()
        ->and($product->productCondition->is($condition))->toBeTrue()
        ->and($product->productColor->is($color))->toBeTrue()
        ->and($product->network->is($network))->toBeTrue()
        ->and($product->primaryImage->is($image))->toBeTrue()
        ->and($product->currentPrice())->toBe(90.0);
});

test('product effective price uses sale price when present and regular price otherwise', function () {
    $category = ProductCategory::query()->create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);

    $regularOnlyProduct = Product::query()->create([
        'product_category_id' => $category->id,
        'name' => 'Regular Price Product',
        'slug' => 'regular-price-product',
        'sku' => 'REGULAR-1',
        'regular_price' => 120,
        'sale_price' => null,
        'quantity' => 1,
        'is_active' => true,
    ]);

    $saleProduct = Product::query()->create([
        'product_category_id' => $category->id,
        'name' => 'Sale Price Product',
        'slug' => 'sale-price-product',
        'sku' => 'SALE-1',
        'regular_price' => 120,
        'sale_price' => 95,
        'quantity' => 1,
        'is_active' => true,
    ]);

    expect($regularOnlyProduct->currentPrice())->toBe(120.0)
        ->and($saleProduct->currentPrice())->toBe(95.0);
});

test('product model validation rejects a model from a different brand', function () {
    $admin = productLookupAdminUser('product-model-validation-admin@example.com');
    $category = ProductCategory::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
    $brand = ProductBrand::query()->create(['name' => 'Apple', 'slug' => 'apple', 'status' => 'active']);
    $otherBrand = ProductBrand::query()->create(['name' => 'Samsung', 'slug' => 'samsung', 'status' => 'active']);
    $model = ProductModel::query()->create([
        'name' => 'Galaxy S',
        'slug' => 'galaxy-s',
        'product_brand_id' => $otherBrand->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Invalid Lookup Product',
            'product_category_id' => $category->id,
            'product_brand_id' => $brand->id,
            'product_model_id' => $model->id,
            'regular_price' => 100,
            'quantity' => 1,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('product_model_id');
});

test('admin shop products menu item is visible and active for authorized admins', function () {
    $admin = productLookupAdminUser('product-menu-admin@example.com');

    $this->actingAs($admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee('Product Management')
        ->assertSee('Products')
        ->assertSee('href="'.route('admin.products.index').'"', false)
        ->assertSee('admin-menu-link active', false);
});
