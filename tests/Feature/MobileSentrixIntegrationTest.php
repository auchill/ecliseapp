<?php

use App\Models\MobileSentrixApiSetting;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\User;
use App\Services\MobileSentrix\MobileSentrixAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'mobilesentrix.env' => 'staging',
        'mobilesentrix.base_url' => 'https://preprod.mobilesentrix.ca',
        'mobilesentrix.consumer_name' => 'Eclise Test',
        'mobilesentrix.consumer_key' => 'consumer-key',
        'mobilesentrix.consumer_secret' => 'consumer-secret',
        'mobilesentrix.access_token' => 'access-token',
        'mobilesentrix.access_token_secret' => 'access-secret',
        'mobilesentrix.callback_url' => 'http://ecliseapp.test/admin/parts/mobilesentrix/callback',
        'mobilesentrix.default_markup_type' => 'percentage',
        'mobilesentrix.default_markup_value' => 20,
    ]);
});

test('mobile sentrix oauth exchange stores access tokens encrypted', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifiercallback' => Http::response([
            'data' => [
                'access_token' => 'stored-access-token',
                'access_token_secret' => 'stored-access-secret',
            ],
        ]),
    ]);

    app(MobileSentrixAuthService::class)->exchangeToken('oauth-token', 'oauth-verifier');

    $settings = MobileSentrixApiSetting::query()->firstOrFail();
    $raw = DB::table('mobilesentrix_api_settings')->first();

    expect($settings->access_token)->toBe('stored-access-token')
        ->and($settings->access_token_secret)->toBe('stored-access-secret')
        ->and($raw->access_token)->not->toBe('stored-access-token')
        ->and($raw->access_token_secret)->not->toBe('stored-access-secret');
});

test('mobile sentrix category sync stores local categories and logs the run', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories' => Http::response([
            [
                'entity_id' => '165',
                'name' => 'iPhone Screens',
                'level' => 2,
                'children_count' => 0,
                'is_active' => 1,
                'has_children' => false,
                'is_part' => true,
                'image_url' => 'https://cdn.example.test/category.jpg',
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-categories')->assertSuccessful();

    $this->assertDatabaseHas('part_categories', [
        'mobilesentrix_category_id' => '165',
        'name' => 'iPhone Screens',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('mobilesentrix_sync_logs', [
        'sync_type' => 'categories',
        'status' => 'success',
        'created_count' => 1,
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
});

test('mobile sentrix parts sync maps products into parts without exposing supplier cost publicly', function () {
    config([
        'mobilesentrix.access_token' => null,
        'mobilesentrix.access_token_secret' => null,
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    $category = PartCategory::query()->create([
        'mobilesentrix_category_id' => '165',
        'name' => 'iPhone Screens',
        'slug' => 'iphone-screens',
        'is_active' => true,
        'status' => 'active',
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/products*' => Http::response([
            [
                'entity_id' => '9001',
                'sku' => 'MS-9001',
                'new_sku' => 'NEW-9001',
                'status' => 1,
                'name' => 'iPhone 14 OLED Screen',
                'description' => 'OLED replacement screen.',
                'price' => '50.00',
                'category_ids' => ['165'],
                'is_in_stock' => true,
                'in_stock_qty' => 7,
                'manufacturer' => '10',
                'manufacturer_text' => 'Apple',
                'model' => ['141'],
                'model_text' => ['iPhone 14'],
                'front_position_text' => 'Front',
                'default_image' => 'https://cdn.example.test/part.jpg',
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-parts', ['--category' => '165'])->assertSuccessful();

    $part = Part::query()->where('sku', 'MS-9001')->firstOrFail();

    expect($part->mobilesentrix_product_id)->toBe('9001')
        ->and($part->new_sku)->toBe('NEW-9001')
        ->and($part->brandName())->toBe('Apple')
        ->and($part->modelName())->toBe('iPhone 14')
        ->and((float) $part->cost_price)->toBe(50.0)
        ->and((float) $part->selling_price)->toBe(60.0)
        ->and($part->partCategories()->whereKey($category->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('part_brands', ['name' => 'Apple', 'status' => 'active']);
    $this->assertDatabaseHas('part_models', ['name' => 'iPhone 14']);

    $this->get(route('parts.index'))
        ->assertOk()
        ->assertSee('iPhone 14 OLED Screen')
        ->assertSee('$60.00')
        ->assertDontSee('$50.00')
        ->assertDontSee('MobileSentrix');
});

test('mobile sentrix admin page redacts configured secrets and uses admin login redirect', function () {
    config([
        'mobilesentrix.consumer_secret' => 'very-secret-value',
        'mobilesentrix.access_token_secret' => 'token-secret-value',
    ]);

    $this->get(route('admin.parts.mobilesentrix.index'))->assertRedirect(route('admin.login'));

    $adminPermission = Permission::query()->where('name', 'admin')->firstOrFail();
    $admin = User::query()->create([
        'name' => 'API Admin',
        'email' => 'api-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => $adminPermission->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.parts.mobilesentrix.index'))
        ->assertOk()
        ->assertSee('MobileSentrix API')
        ->assertSee('Configured')
        ->assertDontSee('very-secret-value')
        ->assertDontSee('token-secret-value');
});
