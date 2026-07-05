<?php

use App\Jobs\MobileSentrix\SyncMobileSentrixCategoriesJob;
use App\Jobs\MobileSentrix\SyncMobileSentrixPartsFullJob;
use App\Jobs\MobileSentrix\SyncMobileSentrixPartsJob;
use App\Models\MobileSentrixApiSetting;
use App\Models\MobileSentrixSyncLog;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\User;
use App\Services\MobileSentrix\MobileSentrixAuthService;
use App\Services\MobileSentrix\MobileSentrixClient;
use App\Services\MobileSentrix\MobileSentrixException;
use App\Services\MobileSentrix\MobileSentrixSyncService;
use App\Services\MobileSentrix\PartCategoryPivotService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

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
        'mobilesentrix.allow_browser_secret_redirect' => false,
        'mobilesentrix.auth_transport' => 'oauth_header',
        'mobilesentrix.timeout' => 120,
        'mobilesentrix.connect_timeout' => 20,
        'mobilesentrix.default_markup_type' => 'percentage',
        'mobilesentrix.default_markup_value' => 20,
        'mobilesentrix.sync_request_delay_ms' => 0,
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
        ->and($settings->consumer_key)->toBe('consumer-key')
        ->and($raw->access_token)->not->toBe('stored-access-token')
        ->and($raw->access_token_secret)->not->toBe('stored-access-secret')
        ->and($raw->consumer_key)->not->toBe('consumer-key')
        ->and($settings->last_authenticated_at)->not->toBeNull()
        ->and($settings->toArray())->not->toHaveKeys(['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret']);
});

test('mobile sentrix oauth exchange deactivates older active rows for the same environment', function () {
    $oldBaseRow = MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://old-preprod.mobilesentrix.ca',
        'consumer_key' => 'old-consumer-key',
        'consumer_secret' => 'old-consumer-secret',
        'access_token' => 'old-access-token',
        'access_token_secret' => 'old-access-secret',
        'is_active' => true,
        'updated_at' => now()->subDays(2),
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'previous-consumer-key',
        'consumer_secret' => 'previous-consumer-secret',
        'access_token' => 'previous-access-token',
        'access_token_secret' => 'previous-access-secret',
        'is_active' => true,
        'updated_at' => now()->subDay(),
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifiercallback' => Http::response([
            'data' => [
                'access_token' => 'new-active-access-token',
                'access_token_secret' => 'new-active-access-secret',
            ],
        ]),
    ]);

    app(MobileSentrixAuthService::class)->exchangeToken('oauth-token', 'oauth-verifier');

    $activeRows = MobileSentrixApiSetting::query()->active()->where('environment', 'staging')->get();

    expect($activeRows)->toHaveCount(1)
        ->and($activeRows->first()->base_url)->toBe('https://preprod.mobilesentrix.ca')
        ->and($activeRows->first()->access_token)->toBe('new-active-access-token')
        ->and($oldBaseRow->fresh()->is_active)->toBeFalse();
});

test('mobile sentrix can request temporary oauth token and verifier from identifier endpoint', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifier*' => Http::response([
            'oauth_token' => 'temporary-token',
            'oauth_verifier' => 'temporary-verifier',
        ]),
    ]);

    $tokens = app(MobileSentrixAuthService::class)->requestTemporaryCredentials();

    expect($tokens)->toBe([
        'oauth_token' => 'temporary-token',
        'oauth_verifier' => 'temporary-verifier',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/authorize/identifier')
        && $request['consumer_key'] === 'consumer-key'
        && $request['consumer_secret'] === 'consumer-secret'
        && $request['callback'] === 'http://ecliseapp.test/admin/parts/mobilesentrix/callback');
});

test('mobile sentrix authorization url uses rfc3986 encoded query values', function () {
    config([
        'mobilesentrix.consumer_name' => 'Eclise Technology Inc.',
        'mobilesentrix.consumer_key' => 'key with spaces+plus',
        'mobilesentrix.consumer_secret' => 'secret/value?x=1',
        'mobilesentrix.callback_url' => 'http://127.0.0.1:8000/admin/parts/mobilesentrix/callback?return=admin panel',
    ]);

    $url = app(MobileSentrixAuthService::class)->authorizationUrl();

    expect($url)->toContain('consumer=Eclise%20Technology%20Inc.')
        ->and($url)->toContain('consumer_key=key%20with%20spaces%2Bplus')
        ->and($url)->toContain('consumer_secret=secret%2Fvalue%3Fx%3D1')
        ->and($url)->toContain('callback=http%3A%2F%2F127.0.0.1%3A8000%2Fadmin%2Fparts%2Fmobilesentrix%2Fcallback%3Freturn%3Dadmin%20panel')
        ->and($url)->not->toContain('admin panel')
        ->and($url)->not->toContain('secret/value');
});

test('mobile sentrix authenticate command exchanges live oauth responses and masks output', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifiercallback' => Http::response([
            'data' => [
                'access_token' => 'live-access-token',
                'access_token_secret' => 'live-access-secret',
            ],
        ]),
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifier*' => Http::response([
            'oauth_token' => 'temporary-token',
            'oauth_verifier' => 'temporary-verifier',
        ]),
    ]);

    $exitCode = Artisan::call('mobilesentrix:authenticate');
    $output = Artisan::output();
    $settings = MobileSentrixApiSetting::query()->firstOrFail();

    expect($exitCode)->toBe(0)
        ->and($settings->access_token)->toBe('live-access-token')
        ->and($settings->access_token_secret)->toBe('live-access-secret')
        ->and($output)->toContain('MobileSentrix authentication completed')
        ->and($output)->toContain('Access Token: live********oken')
        ->and($output)->toContain('Access Token Secret: live********cret')
        ->and($output)->not->toContain('live-access-token')
        ->and($output)->not->toContain('live-access-secret');
});

test('mobile sentrix authenticate command reports http 403 safely without exposing secrets', function () {
    config([
        'mobilesentrix.consumer_key' => 'blocked-consumer-key',
        'mobilesentrix.consumer_secret' => 'blocked-consumer-secret',
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifier*' => Http::response('<html>Cloudflare block</html>', 403),
    ]);

    $exitCode = Artisan::call('mobilesentrix:authenticate');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('MobileSentrix rejected or blocked the OAuth request with HTTP 403')
        ->and($output)->toContain('Cloudflare Ray ID')
        ->and($output)->not->toContain('blocked-consumer-key')
        ->and($output)->not->toContain('blocked-consumer-secret')
        ->and(MobileSentrixApiSetting::query()->count())->toBe(0);
});

test('mobile sentrix test connection command uses stored tokens', function () {
    config([
        'mobilesentrix.access_token' => null,
        'mobilesentrix.access_token_secret' => null,
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories' => Http::response([
            ['entity_id' => '165', 'name' => 'iPhone Screens'],
        ]),
    ]);

    $exitCode = Artisan::call('mobilesentrix:test-connection');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('MobileSentrix API connection successful.')
        ->and($output)->toContain('Auth transport: oauth_header')
        ->and($output)->toContain('Category response count: 1')
        ->and($output)->not->toContain('db-access-token')
        ->and($output)->not->toContain('db-access-secret');
});

test('mobile sentrix test connection command explains missing authentication', function () {
    config([
        'mobilesentrix.access_token' => null,
        'mobilesentrix.access_token_secret' => null,
    ]);

    $exitCode = Artisan::call('mobilesentrix:test-connection');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('MobileSentrix is not authenticated yet. Run php artisan mobilesentrix:authenticate or use the admin Authenticate Server-Side button.');
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
        'id' => 165,
        'name' => 'iPhone Screens',
        'status' => 'active',
    ]);
    expect(PartCategory::query()->findOrFail(165)->raw_payload['entity_id'])->toBe('165');
    expect(Schema::hasColumn('part_categories', 'mobilesentrix_category_id'))->toBeFalse();

    $this->assertDatabaseHas('mobilesentrix_sync_logs', [
        'sync_type' => 'categories',
        'status' => 'success',
        'created_count' => 1,
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
});

test('mobile sentrix category sync skips recursive duplicates and respects depth option', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories' => Http::response([
            [
                'entity_id' => '100',
                'name' => 'Root Screens',
                'level' => 1,
                'children_count' => 2,
                'is_active' => 1,
                'has_children' => true,
                'is_part' => true,
            ],
        ]),
        'https://preprod.mobilesentrix.ca/api/rest/categories/100' => Http::response([
            [
                'entity_id' => '100',
                'name' => 'Root Screens Duplicate',
                'level' => 1,
                'children_count' => 2,
                'is_active' => 1,
                'has_children' => true,
                'is_part' => true,
            ],
            [
                'entity_id' => '101',
                'name' => 'iPhone Screens',
                'level' => 2,
                'children_count' => 1,
                'is_active' => 1,
                'has_children' => true,
                'is_part' => true,
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-categories', ['--depth' => 2])->assertSuccessful();

    $this->assertDatabaseHas('part_categories', [
        'id' => 100,
        'name' => 'Root Screens',
    ]);
    $this->assertDatabaseHas('part_categories', [
        'id' => 101,
        'name' => 'iPhone Screens',
    ]);

    $log = MobileSentrixSyncLog::query()->where('sync_type', 'categories')->latest()->firstOrFail();

    expect($log->status)->toBe('success')
        ->and($log->skipped_count)->toBeGreaterThanOrEqual(2)
        ->and(json_encode($log->error_details))->toContain('Skipped duplicate MobileSentrix category 100')
        ->and(json_encode($log->error_details))->toContain('maximum depth 2 reached');

    Http::assertSentCount(2);
});

test('mobile sentrix parts sync maps products into parts without exposing supplier cost publicly', function () {
    config([
        'mobilesentrix.access_token' => null,
        'mobilesentrix.access_token_secret' => null,
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    $category = PartCategory::query()->create([
        'id' => 165,
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
                'image_url' => 'https://cdn.example.test/part.jpg',
                'image_gallery' => ['https://cdn.example.test/gallery.jpg'],
                'attribute_set' => 20,
                'related_product' => ['9002'],
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-parts', ['--category' => '165'])->assertSuccessful();

    $part = Part::query()->where('sku', 'MS-9001')->firstOrFail();

    expect($part->id)->toBe(9001)
        ->and($part->new_sku)->toBe('NEW-9001')
        ->and($part->brandName())->toBe('Apple')
        ->and($part->modelName())->toBe('iPhone 14')
        ->and($part->attribute_set)->toBe('20')
        ->and($part->raw_payload['related_product'])->toBe(['9002'])
        ->and($part->category_ids)->toBe(['165'])
        ->and((float) $part->cost_price)->toBe(50.0)
        ->and((float) $part->selling_price)->toBe(60.0)
        ->and($part->categories()->exists())->toBeFalse()
        ->and($part->gallery_images->pluck('image_url'))->toContain('https://cdn.example.test/part.jpg')
        ->and(Schema::hasColumn('parts', 'mobilesentrix_product_id'))->toBeFalse()
        ->and(Schema::hasColumn('parts', 'part_brand_id'))->toBeFalse()
        ->and(Schema::hasColumn('parts', 'part_model_id'))->toBeFalse()
        ->and(Schema::hasTable('part_brands'))->toBeFalse()
        ->and(Schema::hasTable('part_models'))->toBeFalse()
        ->and(Schema::hasTable('part_categories'))->toBeTrue()
        ->and(Schema::hasTable('part_category_part'))->toBeTrue();

    $this->artisan('mobilesentrix:generate-part-category-pivot')->assertSuccessful();

    expect($part->categories()->whereKey($category->id)->exists())->toBeTrue();

    $this->get(route('parts.index'))
        ->assertOk()
        ->assertSee('iPhone 14 OLED Screen')
        ->assertSee('$60.00')
        ->assertDontSee('$50.00')
        ->assertDontSee('MobileSentrix');
});

test('mobile sentrix parts sync keeps missing category ids raw without creating placeholders', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/products*' => Http::response([
            [
                'entity_id' => '9051',
                'sku' => 'MS-MISSING-CATEGORY',
                'status' => 1,
                'name' => 'Part With Missing Category',
                'price' => '12.00',
                'category_ids' => ['999999'],
                'is_in_stock' => true,
                'in_stock_qty' => 2,
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-parts', ['--limit' => 1])->assertSuccessful();

    $part = Part::query()->where('sku', 'MS-MISSING-CATEGORY')->firstOrFail();
    $log = MobileSentrixSyncLog::query()->where('sync_type', 'parts_full')->latest()->firstOrFail();

    expect($part->raw_payload['category_ids'])->toBe(['999999'])
        ->and($part->category_ids)->toBe(['999999'])
        ->and($part->categories()->count())->toBe(0)
        ->and($log->skipped_count)->toBe(0)
        ->and(json_encode($log->error_details))->not->toContain('missing category 999999');

    $this->artisan('mobilesentrix:generate-part-category-pivot')->assertSuccessful();

    $pivotLog = MobileSentrixSyncLog::query()->where('sync_type', 'part_category_pivot')->latest()->firstOrFail();

    expect($pivotLog->failed_count)->toBe(0)
        ->and($pivotLog->warning_count)->toBe(1)
        ->and(json_encode($pivotLog->error_details))->toContain('missing category 999999');

    $this->assertDatabaseMissing('part_categories', [
        'name' => 'MobileSentrix Category 999999',
    ]);
});

test('part category pivot generation parses supported values and is idempotent', function () {
    foreach ([165, 166] as $categoryId) {
        PartCategory::query()->create([
            'id' => $categoryId,
            'name' => "Category {$categoryId}",
            'slug' => "category-{$categoryId}",
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    $values = [
        9501 => ['165', '166', '165'],
        9502 => '165,999999',
        9503 => '["166","bad"]',
        9504 => 165,
        9505 => null,
    ];

    foreach ($values as $partId => $categoryIds) {
        Part::query()->create([
            'id' => $partId,
            'name' => "Pivot Part {$partId}",
            'slug' => "pivot-part-{$partId}",
            'sku' => "PIVOT-{$partId}",
            'category_ids' => $categoryIds,
        ]);
    }

    $this->artisan('mobilesentrix:generate-part-category-pivot', ['--chunk' => 2])->assertSuccessful();

    $firstLog = MobileSentrixSyncLog::query()->where('sync_type', 'part_category_pivot')->latest('id')->firstOrFail();

    expect(DB::table('part_category_part')->count())->toBe(5)
        ->and($firstLog->created_count)->toBe(5)
        ->and($firstLog->failed_count)->toBe(0)
        ->and($firstLog->warning_count)->toBe(2)
        ->and(json_encode($firstLog->error_details))->toContain('"invalid_id_count":1')
        ->and(json_encode($firstLog->error_details))->toContain('"missing_category_count":1')
        ->and(json_encode($firstLog->error_details))->toContain('"no_category_ids_count":1');

    $this->artisan('mobilesentrix:generate-part-category-pivot', ['--chunk' => 2])->assertSuccessful();

    $secondLog = MobileSentrixSyncLog::query()->where('sync_type', 'part_category_pivot')->latest('id')->firstOrFail();

    expect(DB::table('part_category_part')->count())->toBe(5)
        ->and($secondLog->created_count)->toBe(0)
        ->and($secondLog->updated_count)->toBe(5)
        ->and($secondLog->failed_count)->toBe(0);
});

test('mobile sentrix full parts command runs all three stages in order', function () {
    $syncService = Mockery::mock(MobileSentrixSyncService::class);
    $pivotService = Mockery::mock(PartCategoryPivotService::class);

    $syncService->shouldReceive('syncCategories')
        ->once()
        ->ordered()
        ->with(null, null)
        ->andReturn([
            'status' => 'success',
            'message' => 'Categories complete.',
            'created_count' => 2,
            'updated_count' => 0,
            'skipped_count' => 0,
            'warning_count' => 0,
            'failed_count' => 0,
            'log_id' => 10,
        ]);
    $syncService->shouldReceive('syncParts')
        ->once()
        ->ordered()
        ->with(null, [], ['limit' => 10, 'force' => false])
        ->andReturn([
            'status' => 'success',
            'message' => 'Parts complete.',
            'created_count' => 3,
            'updated_count' => 1,
            'skipped_count' => 0,
            'warning_count' => 0,
            'failed_count' => 0,
            'log_id' => 11,
        ]);
    $pivotService->shouldReceive('generate')
        ->once()
        ->ordered()
        ->andReturn([
            'status' => 'success',
            'message' => 'Pivot complete.',
            'created_count' => 4,
            'updated_count' => 0,
            'skipped_count' => 0,
            'warning_count' => 0,
            'failed_count' => 0,
            'log_id' => 12,
        ]);

    $this->app->instance(MobileSentrixSyncService::class, $syncService);
    $this->app->instance(PartCategoryPivotService::class, $pivotService);

    $this->artisan('mobilesentrix:sync-parts-full', ['--limit' => 10])->assertSuccessful();

    $this->assertDatabaseHas('mobilesentrix_sync_logs', [
        'sync_type' => 'parts_full_process',
        'status' => 'success',
        'created_count' => 9,
        'updated_count' => 1,
        'warning_count' => 0,
        'failed_count' => 0,
    ]);
});

test('mobile sentrix full parts sync paginates products and logs parts full', function () {
    Http::fake(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = (int) ($query['page'] ?? 1);

        $records = [
            1 => [
                [
                    'entity_id' => '9101',
                    'sku' => 'MS-9101',
                    'name' => 'iPhone 15 Battery',
                    'price' => '25.00',
                    'category_ids' => ['200'],
                    'is_in_stock' => true,
                    'in_stock_qty' => 4,
                    'manufacturer_text' => 'Apple',
                    'model_text' => ['iPhone 15'],
                ],
                [
                    'entity_id' => '9102',
                    'sku' => 'MS-9102',
                    'name' => 'Galaxy S24 Screen',
                    'price' => '80.00',
                    'category_ids' => ['201'],
                    'is_in_stock' => true,
                    'in_stock_qty' => 2,
                    'manufacturer_text' => 'Samsung',
                    'model_text' => ['Galaxy S24'],
                ],
            ],
            2 => [
                [
                    'entity_id' => '9103',
                    'sku' => 'MS-9103',
                    'name' => 'Pixel 9 Charging Port',
                    'price' => '15.00',
                    'category_ids' => ['202'],
                    'is_in_stock' => false,
                    'in_stock_qty' => 0,
                    'manufacturer_text' => 'Google',
                    'model_text' => ['Pixel 9'],
                ],
                [
                    'entity_id' => '9104',
                    'sku' => 'MS-9104',
                    'name' => 'Generic Tablet Cable',
                    'price' => '6.00',
                    'category_ids' => ['203'],
                    'is_in_stock' => true,
                    'in_stock_qty' => 8,
                    'model' => '111,222,333',
                    'model_text' => ['Tablet'],
                    'url' => 'https://preprod.mobilesentrix.ca/'.str_repeat('very-long-url-segment-', 20),
                ],
            ],
        ];

        return Http::response([
            'data' => [
                'items' => $records[$page] ?? [],
                'page_info' => [
                    'current_page' => $page,
                    'total_pages' => 2,
                    'page_size' => 2,
                    'total_count' => 4,
                ],
            ],
        ]);
    });

    $this->artisan('mobilesentrix:sync-parts')->assertSuccessful();

    $this->assertDatabaseHas('parts', ['id' => 9101, 'sku' => 'MS-9101']);
    $this->assertDatabaseHas('parts', ['id' => 9102, 'sku' => 'MS-9102']);
    $this->assertDatabaseHas('parts', ['id' => 9103, 'sku' => 'MS-9103']);
    $this->assertDatabaseHas('parts', ['id' => 9104, 'sku' => 'MS-9104', 'brand' => 'MobileSentrix', 'model' => '111,222,333']);
    expect(strlen((string) Part::query()->where('sku', 'MS-9104')->value('url')))->toBeLessThanOrEqual(255);
    expect(Part::query()->findOrFail(9104)->modelName())->toBe('Tablet')
        ->and(Part::query()->findOrFail(9104)->raw_payload['model'])->toBe('111,222,333');
    $this->assertDatabaseHas('mobilesentrix_sync_logs', [
        'sync_type' => 'parts_full',
        'status' => 'success',
        'created_count' => 4,
    ]);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/rest/products')
        && ! str_contains($request->url(), 'category_id='));
});

test('mobile sentrix parts dry run fetches products without saving', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/products*' => Http::response([
            'data' => [
                'items' => [
                    [
                        'entity_id' => '9201',
                        'sku' => 'MS-9201',
                        'name' => 'Dry Run Screen',
                        'price' => '42.00',
                        'is_in_stock' => true,
                        'in_stock_qty' => 5,
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('mobilesentrix:sync-parts', ['--dry-run' => true, '--limit' => 1])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run only');

    $this->assertDatabaseMissing('parts', ['id' => 9201]);
    $this->assertDatabaseHas('mobilesentrix_sync_logs', [
        'sync_type' => 'parts_full',
        'status' => 'success',
        'created_count' => 1,
    ]);
});

test('mobile sentrix client uses stored encrypted credentials before env fallback for search', function () {
    config([
        'mobilesentrix.consumer_key' => 'env-consumer-key',
        'mobilesentrix.consumer_secret' => 'env-consumer-secret',
        'mobilesentrix.access_token' => 'env-access-token',
        'mobilesentrix.access_token_secret' => 'env-access-secret',
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/searchproduct*' => Http::response([
            'data' => ['items' => []],
        ]),
    ]);

    app(MobileSentrixClient::class)->searchProducts('iphone');

    Http::assertSent(function ($request) {
        $authorization = $request->header('Authorization')[0] ?? '';

        return str_contains($request->url(), '/api/rest/searchproduct')
            && str_contains($authorization, 'db-consumer-key')
            && str_contains($authorization, 'db-access-token')
            && str_contains($authorization, 'oauth_version="1.0"')
            && ! str_contains($authorization, 'env-consumer-key')
            && ! str_contains($authorization, 'env-access-token');
    });
});

test('mobile sentrix client uses newest active database row matching configured environment and base url', function () {
    config([
        'mobilesentrix.consumer_key' => 'env-consumer-key',
        'mobilesentrix.consumer_secret' => 'env-consumer-secret',
        'mobilesentrix.access_token' => 'env-access-token',
        'mobilesentrix.access_token_secret' => 'env-access-secret',
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://wrong.mobilesentrix.ca',
        'consumer_key' => 'wrong-consumer-key',
        'consumer_secret' => 'wrong-consumer-secret',
        'access_token' => 'wrong-access-token',
        'access_token_secret' => 'wrong-access-secret',
        'is_active' => true,
        'updated_at' => now()->addMinute(),
    ]);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'matching-consumer-key',
        'consumer_secret' => 'matching-consumer-secret',
        'access_token' => 'matching-access-token',
        'access_token_secret' => 'matching-access-secret',
        'is_active' => true,
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories' => Http::response([
            ['entity_id' => '165', 'name' => 'iPhone Screens'],
        ]),
    ]);

    app(MobileSentrixClient::class)->categories();

    Http::assertSent(function ($request) {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://preprod.mobilesentrix.ca/api/rest/categories'
            && str_contains($authorization, 'matching-consumer-key')
            && str_contains($authorization, 'matching-access-token')
            && ! str_contains($authorization, 'wrong-consumer-key')
            && ! str_contains($authorization, 'env-consumer-key');
    });
});

test('mobile sentrix client supports query parameter auth transport server side', function () {
    config(['mobilesentrix.auth_transport' => 'query_params']);

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories*' => Http::response([
            ['entity_id' => '165', 'name' => 'iPhone Screens'],
        ]),
    ]);

    app(MobileSentrixClient::class)->categories();

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ! $request->hasHeader('Authorization')
            && $query['consumer_key'] === 'db-consumer-key'
            && $query['consumer_secret'] === 'db-consumer-secret'
            && $query['access_token'] === 'db-access-token'
            && $query['access_token_secret'] === 'db-access-secret';
    });
});

test('mobile sentrix test connection command accepts auth transport override', function () {
    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories*' => Http::response([
            ['entity_id' => '165', 'name' => 'iPhone Screens'],
        ]),
    ]);

    $exitCode = Artisan::call('mobilesentrix:test-connection', [
        '--auth-transport' => 'query_params',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Auth transport: query_params')
        ->and($output)->not->toContain('db-consumer-secret')
        ->and($output)->not->toContain('db-access-secret');
});

test('mobile sentrix debug auth command prints credential presence only', function () {
    $settings = MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_name' => 'Database Consumer',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
        'last_authenticated_at' => now(),
    ]);

    $exitCode = Artisan::call('mobilesentrix:debug-auth');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Environment: staging')
        ->and($output)->toContain('Base URL: https://preprod.mobilesentrix.ca')
        ->and($output)->toContain('Consumer Key configured: Yes')
        ->and($output)->toContain('Access Token Secret configured: Yes')
        ->and($output)->toContain('Active DB settings row ID: '.$settings->id)
        ->and($output)->toContain('Token source: database')
        ->and($output)->toContain('Auth transport: oauth_header')
        ->and($output)->not->toContain('db-consumer-key')
        ->and($output)->not->toContain('db-consumer-secret')
        ->and($output)->not->toContain('db-access-token')
        ->and($output)->not->toContain('db-access-secret');
});

test('mobile sentrix http 401 diagnostics are safe and actionable', function () {
    Log::spy();

    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'db-consumer-key',
        'consumer_secret' => 'db-consumer-secret',
        'access_token' => 'db-access-token',
        'access_token_secret' => 'db-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/categories' => Http::response('Invalid OAuth credentials', 401),
    ]);

    $exitCode = Artisan::call('mobilesentrix:test-connection');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain(MobileSentrixClient::HTTP_401_MESSAGE)
        ->and($output)->not->toContain('db-consumer-key')
        ->and($output)->not->toContain('db-consumer-secret')
        ->and($output)->not->toContain('db-access-token')
        ->and($output)->not->toContain('db-access-secret');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context);

            return $message === 'MobileSentrix API request rejected with HTTP 401.'
                && $context['endpoint_path'] === '/api/rest/categories'
                && $context['status'] === 401
                && $context['auth_transport'] === 'oauth_header'
                && $context['token_source'] === 'database'
                && $context['environment'] === 'staging'
                && $context['base_url'] === 'https://preprod.mobilesentrix.ca'
                && str_contains($context['response_body'], 'Invalid OAuth credentials')
                && ! str_contains($encoded, 'db-consumer-key')
                && ! str_contains($encoded, 'db-consumer-secret')
                && ! str_contains($encoded, 'db-access-token')
                && ! str_contains($encoded, 'db-access-secret');
        });
});

test('mobile sentrix client fails safely when access tokens are missing', function () {
    config([
        'mobilesentrix.access_token' => null,
        'mobilesentrix.access_token_secret' => null,
    ]);

    expect(fn () => app(MobileSentrixClient::class)->categories())
        ->toThrow(MobileSentrixException::class, 'Please authenticate MobileSentrix first');
});

test('mobile sentrix reauthentication updates stored encrypted tokens', function () {
    MobileSentrixApiSetting::query()->create([
        'environment' => 'staging',
        'base_url' => 'https://preprod.mobilesentrix.ca',
        'consumer_key' => 'old-consumer-key',
        'consumer_secret' => 'old-consumer-secret',
        'access_token' => 'old-access-token',
        'access_token_secret' => 'old-access-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifiercallback' => Http::response([
            'data' => [
                'access_token' => 'new-access-token',
                'access_token_secret' => 'new-access-secret',
            ],
        ]),
    ]);

    app(MobileSentrixAuthService::class)->exchangeToken('oauth-token', 'oauth-verifier');

    expect(MobileSentrixApiSetting::query()->count())->toBe(1)
        ->and(MobileSentrixApiSetting::query()->first()->access_token)->toBe('new-access-token')
        ->and(MobileSentrixApiSetting::query()->first()->access_token_secret)->toBe('new-access-secret');
});

function mobileSentrixAdminUser(string $email): User
{
    $adminPermission = Permission::query()->where('name', 'admin')->firstOrFail();

    return User::query()->create([
        'name' => 'MobileSentrix Admin',
        'email' => $email,
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => $adminPermission->id,
        'status' => 'active',
    ]);
}

test('mobile sentrix admin server authentication exchanges credentials without browser url exposure', function () {
    Http::fake([
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifiercallback' => Http::response([
            'data' => [
                'access_token' => 'admin-access-token',
                'access_token_secret' => 'admin-access-secret',
            ],
        ]),
        'https://preprod.mobilesentrix.ca/oauth/authorize/identifier*' => Http::response([
            'oauth_token' => 'admin-temporary-token',
            'oauth_verifier' => 'admin-temporary-verifier',
        ]),
    ]);

    $admin = mobileSentrixAdminUser('server-auth-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.authenticate-server'), ['_token' => 'test-token']);

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));

    expect(MobileSentrixApiSetting::query()->first()->access_token)->toBe('admin-access-token')
        ->and(MobileSentrixApiSetting::query()->first()->access_token_secret)->toBe('admin-access-secret');
});

test('mobile sentrix admin browser oauth redirect is blocked by default when url contains secrets', function () {
    Http::fake();

    $admin = mobileSentrixAdminUser('browser-blocked-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.authorize'), ['_token' => 'test-token']);
    $location = $response->headers->get('Location');

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $response->assertSessionHasErrors([
        'mobilesentrix' => 'MobileSentrix authentication cannot be opened in the browser because the authorization URL includes sensitive credentials. Use the secure server-side authentication command or contact MobileSentrix to confirm the correct OAuth flow.',
    ]);

    expect($location)->not->toContain('preprod.mobilesentrix.ca')
        ->and($location)->not->toContain('consumer-key')
        ->and($location)->not->toContain('consumer-secret');

    Http::assertNothingSent();
});

test('mobile sentrix browser oauth redirect requires explicit config and confirmation', function () {
    config(['mobilesentrix.allow_browser_secret_redirect' => true]);

    $admin = mobileSentrixAdminUser('browser-confirm-admin@example.com');

    $unconfirmed = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.authorize'), ['_token' => 'test-token']);

    $unconfirmed->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $unconfirmed->assertSessionHasErrors([
        'mobilesentrix' => 'Warning: MobileSentrix browser authentication will expose Consumer Key and Consumer Secret in the browser URL. Continue only if MobileSentrix has confirmed this is required.',
    ]);

    $confirmed = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.parts.mobilesentrix.authorize'), [
            '_token' => 'test-token',
            'confirm_secret_redirect' => '1',
        ]);
    $location = $confirmed->headers->get('Location');

    $confirmed->assertRedirect();

    expect($location)->not->toBeNull()
        ->and(str_starts_with((string) $location, 'https://preprod.mobilesentrix.ca/oauth/authorize/identifier'))->toBeTrue()
        ->and($location)->toContain('consumer_key=consumer-key')
        ->and($location)->toContain('consumer_secret=consumer-secret');
});

test('mobile sentrix admin page redacts configured secrets and uses admin login redirect', function () {
    config([
        'mobilesentrix.consumer_secret' => 'very-secret-value',
        'mobilesentrix.access_token_secret' => 'token-secret-value',
    ]);

    $this->get(route('admin.parts.mobilesentrix.index'))->assertRedirect(route('admin.login'));

    $admin = mobileSentrixAdminUser('api-admin@example.com');

    $this->actingAs($admin)
        ->get(route('admin.parts.mobilesentrix.index'))
        ->assertOk()
        ->assertSee('MobileSentrix API')
        ->assertSee('Yes')
        ->assertSee('Authenticate Server-Side')
        ->assertSee('Test Live Connection')
        ->assertSee('Connection status')
        ->assertSee('Callback route exists')
        ->assertSee('Callback URL allowed')
        ->assertSee('Browser authentication is disabled')
        ->assertSee('Queue Complete Sync')
        ->assertSee('Runs categories, parts-only import, then category assignments.')
        ->assertSee('Warnings')
        ->assertDontSee('Copy Safe Support Message')
        ->assertDontSee('Cloudflare Ray ID')
        ->assertDontSee('Please confirm')
        ->assertDontSee('consumer-key')
        ->assertDontSee('very-secret-value')
        ->assertDontSee('token-secret-value');

    $this->actingAs($admin)
        ->withSession(['mobilesentrix_connection_status' => 'Failed'])
        ->get(route('admin.parts.mobilesentrix.index'))
        ->assertOk()
        ->assertSee('Copy Safe Support Message')
        ->assertSee('Cloudflare Ray ID')
        ->assertSee('Please confirm')
        ->assertDontSee('consumer-key')
        ->assertDontSee('very-secret-value')
        ->assertDontSee('token-secret-value');
});

test('mobile sentrix admin category sync queues instead of running inside the request', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $admin = mobileSentrixAdminUser('queue-categories-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.sync-categories'), [
            '_token' => 'test-token',
            'category_id' => '165',
            'depth' => '3',
        ]);

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $response->assertSessionHas('status', 'MobileSentrix category sync has been queued. Check Sync Logs for progress.');

    Queue::assertPushed(SyncMobileSentrixCategoriesJob::class, function (SyncMobileSentrixCategoriesJob $job): bool {
        return $job->categoryId === '165' && $job->depth === 3;
    });
});

test('mobile sentrix admin parts sync queues instead of running inside the request', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $admin = mobileSentrixAdminUser('queue-parts-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.sync-parts'), [
            '_token' => 'test-token',
            'category_id' => '165',
        ]);

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $response->assertSessionHas('status', 'MobileSentrix parts-only sync for category 165 has been queued. Check Sync Logs for progress.');

    Queue::assertPushed(SyncMobileSentrixPartsJob::class, function (SyncMobileSentrixPartsJob $job): bool {
        return $job->categoryId === '165';
    });
});

test('mobile sentrix admin all parts sync queues without a category', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $admin = mobileSentrixAdminUser('queue-all-parts-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.sync-parts'), [
            '_token' => 'test-token',
            'limit' => '500',
        ]);

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $response->assertSessionHas('status', 'Complete MobileSentrix parts sync has been queued: categories, parts, then category assignments. Check Sync Logs for progress.');

    Queue::assertPushed(SyncMobileSentrixPartsFullJob::class, function (SyncMobileSentrixPartsFullJob $job): bool {
        return $job->limit === 500;
    });
});

test('mobile sentrix admin sync shows cli command when queue is synchronous', function () {
    config(['queue.default' => 'sync']);
    Queue::fake();

    $admin = mobileSentrixAdminUser('sync-queue-admin@example.com');

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.parts.mobilesentrix.index'))
        ->post(route('admin.parts.mobilesentrix.sync-categories'), [
            '_token' => 'test-token',
            'category_id' => '165',
            'depth' => '3',
        ]);

    $response->assertRedirect(route('admin.parts.mobilesentrix.index'));
    $response->assertSessionHasErrors('mobilesentrix');

    $errors = session('errors')->get('mobilesentrix');

    expect($errors[0])->toContain('php -d max_execution_time=0 artisan mobilesentrix:sync-parts-categories')
        ->and($errors[0])->toContain("--category='165'")
        ->and($errors[0])->toContain('--depth=3');

    Queue::assertNothingPushed();
});
