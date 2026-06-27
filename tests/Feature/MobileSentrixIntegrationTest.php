<?php

use App\Models\MobileSentrixApiSetting;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\User;
use App\Services\MobileSentrix\MobileSentrixAuthService;
use App\Services\MobileSentrix\MobileSentrixClient;
use App\Services\MobileSentrix\MobileSentrixException;
use Illuminate\Support\Facades\Artisan;
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
        'mobilesentrix.allow_browser_secret_redirect' => false,
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
        ->and($settings->consumer_key)->toBe('consumer-key')
        ->and($raw->access_token)->not->toBe('stored-access-token')
        ->and($raw->access_token_secret)->not->toBe('stored-access-secret')
        ->and($raw->consumer_key)->not->toBe('consumer-key')
        ->and($settings->last_authenticated_at)->not->toBeNull()
        ->and($settings->toArray())->not->toHaveKeys(['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret']);
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
            && ! str_contains($authorization, 'env-consumer-key')
            && ! str_contains($authorization, 'env-access-token');
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
        ->assertSee('Copy Safe Support Message')
        ->assertSee('Callback route exists')
        ->assertSee('Callback URL allowed')
        ->assertSee('Cloudflare Ray ID')
        ->assertSee('Browser authentication is disabled')
        ->assertSee('Please confirm')
        ->assertDontSee('consumer-key')
        ->assertDontSee('very-secret-value')
        ->assertDontSee('token-secret-value');
});
