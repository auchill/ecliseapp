<?php

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use Database\Seeders\CatalogTaxonomySeeder;
use Database\Seeders\ProductLookupSeeder;
use Illuminate\Support\Str;

function shopFilterLookup(string $modelClass, string $name, array $attributes = [])
{
    return $modelClass::query()->create(array_merge([
        'name' => $name,
        'slug' => Str::slug($name),
        'status' => 'active',
        'sort_order' => 10,
    ], $attributes));
}

function shopFilterCategory(string $name): ProductCategory
{
    return ProductCategory::query()->create([
        'name' => $name,
        'slug' => Str::slug($name),
        'code' => strtoupper(substr(Str::slug($name, ''), 0, 3)),
        'is_active' => true,
        'sort_order' => 10,
    ]);
}

function shopFilterProduct(string $name, array $relations, array $attributes = []): Product
{
    $brand = $relations['brand'];
    $model = ProductModel::query()->firstOrCreate(
        ['slug' => Str::slug($brand->name.' Test Model')],
        ['name' => 'Test Model', 'product_brand_id' => $brand->id, 'status' => 'active'],
    );
    $network = ProductNetwork::query()->firstOrCreate(
        ['slug' => 'unlocked'],
        ['name' => 'Unlocked', 'status' => 'active'],
    );
    $size = ProductSize::query()->firstOrCreate(
        ['slug' => 'storage-128gb'],
        ['name' => '128GB', 'type' => 'storage', 'is_active' => true],
    );

    $product = Product::query()->create(array_merge([
        'product_category_id' => $relations['category']->id,
        'product_brand_id' => $brand->id,
        'product_model_id' => $model->id,
        'product_condition_id' => $relations['condition']->id,
        'product_grade_id' => $relations['grade']->id,
        'product_color_id' => $relations['color']->id,
        'product_network_id' => $network->id,
        'name' => $name,
        'slug' => Str::slug($name),
        'sku' => strtoupper(Str::slug($name, '-')),
        'short_description' => $attributes['short_description'] ?? null,
        'description' => $attributes['description'] ?? null,
        'regular_price' => $attributes['regular_price'] ?? 100,
        'sale_price' => $attributes['sale_price'] ?? null,
        'quantity' => $attributes['quantity'] ?? 2,
        'source' => 'manual',
        'is_featured' => false,
        'is_active' => $attributes['is_active'] ?? true,
    ], $attributes));

    $product->sizes()->sync([$size->id]);

    return $product;
}

function seedShopFilterProducts(): array
{
    $phones = shopFilterCategory('Phones');
    $tablets = shopFilterCategory('Tablets');
    $apple = shopFilterLookup(ProductBrand::class, 'Apple');
    $samsung = shopFilterLookup(ProductBrand::class, 'Samsung', ['sort_order' => 20]);
    $google = shopFilterLookup(ProductBrand::class, 'Google', ['sort_order' => 30]);
    $new = shopFilterLookup(ProductCondition::class, 'New');
    $excellent = shopFilterLookup(ProductCondition::class, 'Excellent', ['sort_order' => 20]);
    $good = shopFilterLookup(ProductCondition::class, 'Good', ['sort_order' => 30]);
    $gradeA = shopFilterLookup(ProductGrade::class, 'Grade A');
    $gradeB = shopFilterLookup(ProductGrade::class, 'Grade B', ['sort_order' => 20]);
    $black = shopFilterLookup(ProductColor::class, 'Black');
    $blue = shopFilterLookup(ProductColor::class, 'Blue', ['sort_order' => 20]);
    $red = shopFilterLookup(ProductColor::class, 'Red', ['sort_order' => 30]);

    shopFilterProduct('Apple New Black Phone', ['category' => $phones, 'brand' => $apple, 'condition' => $new, 'grade' => $gradeA, 'color' => $black], ['regular_price' => 100]);
    shopFilterProduct('Samsung Excellent Blue Phone', ['category' => $phones, 'brand' => $samsung, 'condition' => $excellent, 'grade' => $gradeA, 'color' => $blue], ['regular_price' => 200]);
    shopFilterProduct('Google Excellent Black Phone', ['category' => $phones, 'brand' => $google, 'condition' => $excellent, 'grade' => $gradeB, 'color' => $black], ['regular_price' => 300]);
    shopFilterProduct('Apple Excellent Blue Tablet', ['category' => $tablets, 'brand' => $apple, 'condition' => $excellent, 'grade' => $gradeB, 'color' => $blue], ['regular_price' => 400, 'short_description' => 'Tablet keyword']);
    shopFilterProduct('Inactive Apple Phone', ['category' => $phones, 'brand' => $apple, 'condition' => $new, 'grade' => $gradeA, 'color' => $red], ['is_active' => false]);

    return compact('phones', 'tablets', 'apple', 'samsung', 'google', 'new', 'excellent', 'good', 'gradeA', 'gradeB', 'black', 'blue', 'red');
}

test('shop filter sidebar uses category buttons and allowed checkbox groups only', function () {
    seedShopFilterProducts();

    $response = $this->get(route('shop.index'));

    $response->assertOk()
        ->assertSee('data-shop-category="phones"', false)
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('name="brands[]"', false)
        ->assertSee('name="conditions[]"', false)
        ->assertSee('name="grades[]"', false)
        ->assertSee('name="colors[]"', false)
        ->assertDontSee('name="model"', false)
        ->assertDontSee('name="size"', false)
        ->assertDontSee('Apply Filters')
        ->assertSee('offcanvas offcanvas-start', false)
        ->assertSee('AbortController', false)
        ->assertSee('cancelPendingRequest', false)
        ->assertDontSee('document.querySelector(selectors.desktopFilters).innerHTML', false)
        ->assertDontSee('document.querySelector(selectors.mobileFilters).innerHTML', false);
});

test('shop dynamic refresh failures do not render persistent technical error banners', function () {
    seedShopFilterProducts();

    $response = $this->get(route('shop.index'));

    $response->assertOk()
        ->assertSee('data-shop-notice', false)
        ->assertSee('handleNonFatalFilterError', false)
        ->assertSee('existing content was preserved', false)
        ->assertDontSee('Product filters could not be updated', false)
        ->assertDontSee('data-shop-error', false)
        ->assertDontSee('setError(', false)
        ->assertDontSee('The product search encountered a server error', false)
        ->assertDontSee('One or more filter values are invalid', false)
        ->assertDontSee('<div class="alert alert-danger d-none"', false);
});

test('shop filter hides unselected zero count options on initial load', function () {
    seedShopFilterProducts();
    shopFilterCategory('Laptops');
    shopFilterLookup(ProductBrand::class, 'Dell');
    shopFilterLookup(ProductCondition::class, 'Fair');
    shopFilterLookup(ProductGrade::class, 'Grade C');

    $response = $this->get(route('shop.index'));

    $response->assertOk()
        ->assertDontSee('data-shop-category="laptops"', false)
        ->assertDontSee('>Dell<', false)
        ->assertDontSee('>Fair<', false)
        ->assertDontSee('>Grade C<', false)
        ->assertDontSee('>Red<', false)
        ->assertSee('All Products');
});

test('shop filters use OR within groups and AND between groups', function () {
    seedShopFilterProducts();

    $this->get(route('shop.index', [
        'brands' => ['apple', 'samsung'],
    ]))
        ->assertOk()
        ->assertSee('Apple New Black Phone')
        ->assertSee('Samsung Excellent Blue Phone')
        ->assertSee('Apple Excellent Blue Tablet')
        ->assertDontSee('Google Excellent Black Phone');

    $this->get(route('shop.index', [
        'brands' => ['apple', 'samsung'],
        'conditions' => ['excellent'],
        'colors' => ['blue'],
    ]))
        ->assertOk()
        ->assertSee('Samsung Excellent Blue Phone')
        ->assertSee('Apple Excellent Blue Tablet')
        ->assertDontSee('Apple New Black Phone')
        ->assertDontSee('Google Excellent Black Phone');
});

test('shop filters support category selection keyword search and inactive exclusion', function () {
    seedShopFilterProducts();

    $this->get(route('shop.index', ['category' => 'phones']))
        ->assertOk()
        ->assertSee('Apple New Black Phone')
        ->assertSee('Samsung Excellent Blue Phone')
        ->assertDontSee('Apple Excellent Blue Tablet')
        ->assertDontSee('Inactive Apple Phone');

    $this->get(route('shop.index', ['q' => 'Tablet keyword']))
        ->assertOk()
        ->assertSee('Apple Excellent Blue Tablet')
        ->assertDontSee('Apple New Black Phone');
});

test('shop ajax filter response returns updated partials counts and active chips', function () {
    seedShopFilterProducts();

    $response = $this->getJson(route('shop.index', [
        'brands' => ['apple'],
        'grades' => ['grade-b'],
        'sort' => 'price_desc',
    ]));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('total', 1)
        ->assertJsonPath('activeFilterCount', 2);

    expect($response->json('results'))->toContain('Apple Excellent Blue Tablet')
        ->and($response->json('results'))->not->toContain('Apple New Black Phone')
        ->and($response->json('activeFilters'))->toContain('Apple')
        ->and($response->json('activeFilters'))->toContain('Grade B')
        ->and($response->json('desktopFilters'))->toContain('name="brands[]"')
        ->and($response->json('desktopFilters'))->not->toContain('name="model"')
        ->and($response->json('desktopFilters'))->not->toContain('name="size"');
});

test('shop ajax search handles encoded special characters and search alias', function () {
    $fixtures = seedShopFilterProducts();
    shopFilterProduct('iPhone 15 Pro USB-C Men\'s A&B 128 GB C++ 20% Phone', [
        'category' => $fixtures['phones'],
        'brand' => $fixtures['apple'],
        'condition' => $fixtures['new'],
        'grade' => $fixtures['gradeA'],
        'color' => $fixtures['black'],
    ], ['regular_price' => 599]);

    foreach (['iPhone 15 Pro', 'USB-C', 'Men\'s', 'A&B', '128 GB', 'C++', '20%'] as $term) {
        $response = $this->getJson(route('shop.index', ['q' => $term]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        expect($response->headers->get('content-type'))->toContain('application/json')
            ->and($response->json('results'))->toContain('iPhone 15 Pro USB-C Men&#039;s A&amp;B 128 GB C++ 20% Phone');
    }

    $aliasResponse = $this->getJson(route('shop.index', ['search' => 'USB-C']));

    $aliasResponse->assertOk()
        ->assertJsonPath('success', true);

    expect($aliasResponse->json('activeFilters'))->toContain('Search: USB-C');
});

test('shop ajax invalid filters return controlled validation json', function () {
    seedShopFilterProducts();

    $priceResponse = $this->getJson(route('shop.index', [
        'min_price' => 500,
        'max_price' => 100,
    ]));

    $priceResponse->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The selected product filters are invalid.')
        ->assertJsonValidationErrors(['max_price']);

    $sortResponse = $this->getJson(route('shop.index', ['sort' => 'invalid-sort']));

    $sortResponse->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['sort']);
});

test('shop ajax filter groups remove stale zero count options and restore them when available', function () {
    seedShopFilterProducts();

    $samsungResponse = $this->getJson(route('shop.index', ['q' => 'Samsung']));

    $samsungResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total', 1);

    expect($samsungResponse->json('desktopFilterGroups'))->toContain('Samsung')
        ->and($samsungResponse->json('desktopFilterGroups'))->not->toContain('Apple')
        ->and($samsungResponse->json('desktopFilterGroups'))->not->toContain('Google')
        ->and($samsungResponse->json('desktopFilterGroups'))->not->toContain('Tablet');

    $defaultResponse = $this->getJson(route('shop.index'));

    $defaultResponse->assertOk();

    expect($defaultResponse->json('desktopFilterGroups'))->toContain('Apple')
        ->and($defaultResponse->json('desktopFilterGroups'))->toContain('Samsung')
        ->and($defaultResponse->json('desktopFilterGroups'))->toContain('Google');
});

test('selected zero count options stay visible and empty groups are hidden', function () {
    seedShopFilterProducts();

    $selectedZeroResponse = $this->getJson(route('shop.index', [
        'brands' => ['apple'],
        'conditions' => ['good'],
    ]));

    $selectedZeroResponse->assertOk()
        ->assertJsonPath('total', 0);

    expect($selectedZeroResponse->json('desktopFilterGroups'))->toContain('Apple')
        ->and($selectedZeroResponse->json('desktopFilterGroups'))->toContain('Good')
        ->and($selectedZeroResponse->json('desktopFilterGroups'))->toContain('<small>0</small>');

    $emptyResponse = $this->getJson(route('shop.index', ['q' => 'No matching product text']));

    $emptyResponse->assertOk()
        ->assertJsonPath('total', 0);

    expect($emptyResponse->json('desktopFilterGroups'))->toContain('All Products')
        ->and($emptyResponse->json('desktopFilterGroups'))->not->toContain('<legend>Brand</legend>')
        ->and($emptyResponse->json('desktopFilterGroups'))->not->toContain('<legend>Condition</legend>')
        ->and($emptyResponse->json('desktopFilterGroups'))->not->toContain('<legend>Grade</legend>')
        ->and($emptyResponse->json('desktopFilterGroups'))->not->toContain('<legend>Color</legend>');
});

test('product lookup seeder avoids accidental singular plural core category duplicates', function () {
    $this->seed(CatalogTaxonomySeeder::class);
    $this->seed(ProductLookupSeeder::class);

    expect(ProductCategory::query()->whereIn('slug', ['phones', 'tablets', 'laptops', 'desktops', 'smart-watches'])->where('is_active', true)->count())->toBe(0)
        ->and(ProductCategory::query()->whereIn('slug', ['phone', 'tablet', 'laptop', 'desktop', 'smart-watch'])->where('is_active', true)->count())->toBe(5);
});

test('shop pagination preserves selected multi-select filter parameters', function () {
    $fixtures = seedShopFilterProducts();

    for ($index = 1; $index <= 13; $index++) {
        shopFilterProduct('Apple Extra Phone '.$index, [
            'category' => $fixtures['phones'],
            'brand' => $fixtures['apple'],
            'condition' => $fixtures['new'],
            'grade' => $fixtures['gradeA'],
            'color' => $fixtures['black'],
        ], ['regular_price' => 100 + $index]);
    }

    $response = $this->get(route('shop.index', [
        'brands' => ['apple'],
        'conditions' => ['new'],
    ]));

    $response->assertOk()
        ->assertSee('brands%5B0%5D=apple', false)
        ->assertSee('conditions%5B0%5D=new', false);
});
