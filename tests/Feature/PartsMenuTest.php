<?php

use App\Models\Part;
use App\Models\PartCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createPartsMenuCategory(array $attributes): PartCategory
{
    $name = $attributes['name'] ?? 'Category';
    $id = $attributes['id'] ?? random_int(100000, 999999);

    return PartCategory::query()->create(array_merge([
        'id' => $id,
        'name' => $name,
        'slug' => Str::slug($name.' '.$id),
        'parent_id' => null,
        'has_children' => false,
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
        'sort_order' => 0,
    ], $attributes));
}

function seedPartsMenuTree(): array
{
    $replacement = createPartsMenuCategory(['id' => 165, 'name' => 'Replacement Parts', 'has_children' => true]);

    $categories = [
        'replacement' => $replacement,
        'apple' => createPartsMenuCategory(['id' => 756, 'parent_id' => 165, 'name' => 'Apple', 'has_children' => true]),
        'samsung' => createPartsMenuCategory(['id' => 757, 'parent_id' => 165, 'name' => 'Samsung', 'has_children' => true]),
        'motorola' => createPartsMenuCategory(['id' => 779, 'parent_id' => 165, 'name' => 'Motorola', 'has_children' => true]),
        'google' => createPartsMenuCategory(['id' => 570, 'parent_id' => 165, 'name' => 'Google', 'has_children' => true]),
        'others' => createPartsMenuCategory(['id' => 167, 'parent_id' => 165, 'name' => 'Others']),
        'game' => createPartsMenuCategory(['id' => 630, 'name' => 'Game Console Parts', 'has_children' => true]),
        'refurbishing' => createPartsMenuCategory(['id' => 227, 'name' => 'Refurbishing', 'has_children' => true]),
        'board' => createPartsMenuCategory(['id' => 587, 'name' => 'Board Components', 'has_children' => true]),
    ];

    createPartsMenuCategory(['id' => 8363, 'name' => 'Accessories']);
    createPartsMenuCategory(['id' => 3958, 'name' => 'Brands']);
    createPartsMenuCategory(['id' => 1505, 'name' => 'Pre-Owned Devices']);
    createPartsMenuCategory(['id' => 9001, 'parent_id' => 165, 'name' => 'Hidden Inactive', 'is_active' => false]);

    return $categories;
}

function createPartsMenuPart(PartCategory $category, array $attributes = []): Part
{
    $id = $attributes['id'] ?? random_int(1000000, 9999999);
    $name = $attributes['name'] ?? 'iPhone Test Part';

    $part = Part::query()->create(array_merge([
        'id' => $id,
        'category_ids' => [(string) $category->id],
        'name' => $name,
        'slug' => Str::slug($name.' '.$id),
        'sku' => 'PM-'.$id,
        'device_type' => 'Phone',
        'brand' => 'Apple',
        'manufacturer_text' => 'Apple',
        'model' => 'iPhone',
        'model_compatibility' => 'iPhone 15',
        'part_category' => $category->name,
        'price' => 20,
        'selling_price' => 30,
        'final_price' => 30,
        'quantity' => 5,
        'in_stock_qty' => 5,
        'is_in_stock' => true,
        'stock_status' => 'In stock',
        'supplier' => 'MobileSentrix',
        'is_api_item' => true,
        'is_active' => true,
        'status' => 'active',
    ], $attributes));

    $part->partCategories()->syncWithoutDetaching([$category->id]);

    return $part;
}

test('parts menu displays the approved top-level customer categories only', function () {
    seedPartsMenuTree();

    $response = $this->getJson(route('parts.menu'));

    $response->assertOk();

    expect(collect($response->json('menu'))->pluck('name')->all())->toBe([
        'Apple',
        'Samsung',
        'Motorola',
        'Google',
        'Other Parts',
        'Game Console',
        'Refurbishing',
        'Board Components',
    ]);

    $this->get(route('parts.index'))
        ->assertOk()
        ->assertSee('Apple')
        ->assertSee('Other Parts')
        ->assertSee('Game Console')
        ->assertSee('data-fallback-image="http://ecliseapp.test/images/brand/logo_main.png"', false)
        ->assertDontSee('data-parts-menu-load-more', false)
        ->assertDontSee('data-category-id="165"', false)
        ->assertDontSee('data-category-id="8363"', false)
        ->assertDontSee('data-category-id="3958"', false)
        ->assertDontSee('data-category-id="1505"', false);
});

test('parts category children endpoint returns active direct children', function () {
    $tree = seedPartsMenuTree();

    createPartsMenuCategory(['id' => 2001, 'parent_id' => $tree['apple']->id, 'name' => 'iPhone', 'sort_order' => 1]);
    createPartsMenuCategory(['id' => 2002, 'parent_id' => $tree['apple']->id, 'name' => 'iPad', 'sort_order' => 2]);
    createPartsMenuCategory(['id' => 2003, 'parent_id' => $tree['apple']->id, 'name' => 'Hidden iPod', 'is_active' => false, 'sort_order' => 3]);

    $response = $this->getJson(route('parts.category.children', $tree['apple']));

    $response->assertOk();

    expect(collect($response->json('children'))->pluck('name')->all())->toBe(['iPhone', 'iPad']);
});

test('parts category parts endpoint returns paginated active parts from the pivot', function () {
    $tree = seedPartsMenuTree();
    $leaf = createPartsMenuCategory(['id' => 2101, 'parent_id' => $tree['apple']->id, 'name' => 'iPhone Screens']);

    $first = createPartsMenuPart($leaf, ['id' => 3101, 'name' => 'A iPhone Screen']);
    createPartsMenuPart($leaf, ['id' => 3102, 'name' => 'B iPhone Screen']);
    createPartsMenuPart($leaf, ['id' => 3103, 'name' => 'Inactive iPhone Screen', 'is_active' => false, 'status' => 'inactive']);

    DB::table('part_category_part')->insert([
        'part_id' => $first->id,
        'category_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson(route('parts.category.parts', [$leaf, 'per_page' => 1]));

    $response->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('current_page', 1)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('has_more', true)
        ->assertJsonPath('next_page', 2);

    expect($response->json('html'))
        ->toContain('A iPhone Screen')
        ->toContain('parts-card-image-wrap parts-menu-part-image')
        ->toContain('class="parts-card-image"')
        ->toContain('parts-card-actions parts-menu-part-footer')
        ->toContain('images/brand/logo_main.png')
        ->toContain('onerror="this.onerror=null;this.src=')
        ->not->toContain('B iPhone Screen')
        ->not->toContain('Inactive iPhone Screen');
});

test('parts menu search returns matching categories and parts', function () {
    $tree = seedPartsMenuTree();
    $iphone = createPartsMenuCategory(['id' => 2201, 'parent_id' => $tree['apple']->id, 'name' => 'iPhone']);
    createPartsMenuPart($iphone, ['id' => 3201, 'name' => 'iPhone 15 Battery', 'sku' => 'IP15-BAT']);

    $response = $this->getJson(route('parts.search', ['q' => 'iPhone']));

    $response->assertOk()
        ->assertJsonPath('count', 1);

    expect(collect($response->json('categories'))->pluck('name'))->toContain('iPhone')
        ->and(collect($response->json('parts'))->pluck('name'))->toContain('iPhone 15 Battery')
        ->and($response->json('parts.0.image_url'))->toContain('images/brand/logo_main.png')
        ->and($response->json('parts.0.fallback_image_url'))->toContain('images/brand/logo_main.png')
        ->and($response->json('html'))->toContain('iPhone 15 Battery');
});
