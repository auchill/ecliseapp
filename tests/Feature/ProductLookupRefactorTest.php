<?php

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCarrier;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductSize;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('step 16 product lookups replace device lookups and mobile sentrix ids', function () {
    foreach ([
        'product_brands',
        'product_models',
        'product_sizes',
        'product_grades',
        'productconditions',
        'product_colors',
        'product_carriers',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach ([
        'device_brands',
        'device_models',
        'device_sizes',
        'device_manufacturers',
        'device_grades',
        'device_conditions',
        'device_colors',
        'device_carriers',
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

    expect((new ProductCondition)->getTable())->toBe('productconditions')
        ->and(Route::has('admin.product-sizes.index'))->toBeTrue()
        ->and(Route::has('admin.device-sizes.index'))->toBeFalse();
});

test('products resolve all seven product lookup relationships', function () {
    $brand = ProductBrand::query()->create(['name' => 'Example Brand', 'slug' => 'example-brand', 'status' => 'active']);
    $model = ProductModel::query()->create(['name' => 'Example Model', 'slug' => 'example-model', 'status' => 'active']);
    $size = ProductSize::query()->create(['name' => '256GB', 'slug' => '256gb', 'status' => 'active']);
    $grade = ProductGrade::query()->create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
    $condition = ProductCondition::query()->create(['name' => 'Refurbished', 'slug' => 'refurbished', 'status' => 'active']);
    $color = ProductColor::query()->create(['name' => 'Blue', 'slug' => 'blue', 'status' => 'active']);
    $carrier = ProductCarrier::query()->create(['name' => 'Unlocked', 'slug' => 'unlocked', 'status' => 'active']);

    $product = Product::query()->create([
        'product_brand_id' => $brand->id,
        'product_model_id' => $model->id,
        'product_size_id' => $size->id,
        'product_grade_id' => $grade->id,
        'product_condition_id' => $condition->id,
        'product_color_id' => $color->id,
        'product_carrier_id' => $carrier->id,
        'name' => 'Lookup Product',
        'slug' => 'lookup-product',
        'sku' => 'LOOKUP-1',
        'brand' => $brand->name,
        'model' => $model->name,
        'condition' => $condition->name,
        'price' => 100,
        'quantity' => 1,
        'status' => 'Active',
    ]);

    expect($product->productBrand->is($brand))->toBeTrue()
        ->and($product->productModel->is($model))->toBeTrue()
        ->and($product->productSize->is($size))->toBeTrue()
        ->and($product->productGrade->is($grade))->toBeTrue()
        ->and($product->productCondition->is($condition))->toBeTrue()
        ->and($product->productColor->is($color))->toBeTrue()
        ->and($product->productCarrier->is($carrier))->toBeTrue();
});
