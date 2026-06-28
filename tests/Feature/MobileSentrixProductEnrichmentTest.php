<?php

use App\Models\Part;
use App\Models\PartCategory;
use Illuminate\Support\Facades\Artisan;
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
        'mobilesentrix.auth_transport' => 'oauth_header',
        'mobilesentrix.default_markup_type' => 'none',
        'mobilesentrix.default_markup_value' => 0,
        'mobilesentrix.product_enrichment_ttl_hours' => 12,
        'mobilesentrix.sync_request_delay_ms' => 0,
    ]);
});

function createEnrichmentPart(array $overrides = []): Part
{
    $category = PartCategory::query()->create([
        'id' => 165,
        'name' => 'iPhone Screens',
        'slug' => 'iphone-screens',
        'is_active' => true,
        'status' => 'active',
    ]);

    $part = Part::query()->create(array_merge([
        'id' => 73,
        'part_category_id' => $category->id,
        'name' => 'Local iPhone Screen',
        'slug' => 'local-iphone-screen',
        'sku' => 'MS-73',
        'new_sku' => 'NEW-73',
        'description' => 'Local description.',
        'price' => 50,
        'cost_price' => 50,
        'selling_price' => 50,
        'final_price' => 50,
        'quantity' => 1,
        'in_stock_qty' => 1,
        'is_in_stock' => true,
        'stock_status' => 'In stock',
        'availability_status' => 'In stock',
        'supplier' => 'MobileSentrix',
        'is_api_item' => true,
        'is_active' => true,
        'status' => 'active',
        'api_status' => 'active',
    ], $overrides));

    $part->categories()->sync([$category->id]);

    return $part;
}

function fakeMobileSentrixProductEnrichmentHttp(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/api/rest/products/73')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $record = [
                'entity_id' => '73',
                'sku' => 'MS-73',
                'new_sku' => 'NEW-73',
                'status' => 1,
                'name' => 'iPhone 14 Pro OLED Screen',
                'description' => '<p>Premium OLED display.</p><script>alert("x")</script><ul class="description"><li>Install ready</li></ul><span onclick="bad()">Safe label</span>',
                'price' => '79.99',
                'category_ids' => ['165'],
                'is_in_stock' => true,
                'in_stock_qty' => 8,
                'manufacturer_text' => 'Apple',
                'model_text' => ['iPhone 14 Pro'],
                'front_position_text' => 'Front',
                'warranty_period_text' => 'Lifetime Warranty',
                'image_url' => 'https://cdn.example.test/default.jpg',
                'product_badges' => '7627',
                'product_badges_text' => 'Premium',
                'product_badges_bg' => '#1677ff',
            ];

            if (isset($query['load'])) {
                $record['image_gallery'] = [
                    ['url' => 'https://cdn.example.test/gallery-1.jpg', 'label' => 'Front view'],
                    ['url' => 'https://cdn.example.test/gallery-2.jpg', 'label' => 'Back view'],
                ];
                $record['related_product'] = ['74'];
            }

            return Http::response(['data' => $record]);
        }

        if (str_contains($url, '/api/rest/products/74')) {
            return Http::response(['data' => [
                'entity_id' => '74',
                'sku' => 'MS-74',
                'status' => 1,
                'name' => 'iPhone 14 Pro Screen Adhesive',
                'description' => 'Adhesive gasket.',
                'price' => '5.00',
                'category_ids' => ['165'],
                'is_in_stock' => true,
                'in_stock_qty' => 20,
                'image_url' => 'https://cdn.example.test/adhesive.jpg',
            ]]);
        }

        if (str_contains($url, '/api/rest/tags')) {
            return Http::response([
                [
                    'sku' => 'MS-73',
                    'new_sku' => 'NEW-73',
                    'tag' => ['OLED', 'Premium'],
                    'compatibility' => [
                        'Apple' => ['iPhone 14 Pro', 'iPhone 14 Pro Max'],
                    ],
                ],
            ]);
        }

        return Http::response([], 404);
    });
}

test('public parts show enriches a MobileSentrix product with detail tags compatibility badges images and related parts', function () {
    $part = createEnrichmentPart();
    fakeMobileSentrixProductEnrichmentHttp();

    $response = $this->get(route('parts.show', $part));

    $response->assertOk()
        ->assertSee('iPhone 14 Pro OLED Screen')
        ->assertSee('$79.99')
        ->assertSee('Install ready')
        ->assertSee('Lifetime Warranty')
        ->assertSee('OLED')
        ->assertSee('iPhone 14 Pro Max')
        ->assertSee('Related Parts')
        ->assertDontSee('alert("x")', false)
        ->assertDontSee('onclick', false);

    $part = $part->fresh();

    expect($part->last_enriched_at)->not->toBeNull()
        ->and($part->raw_payload['entity_id'])->toBe('73')
        ->and($part->tags_raw_payload[0]['sku'])->toBe('MS-73')
        ->and($part->images()->count())->toBe(3)
        ->and($part->tags()->pluck('name')->all())->toContain('OLED')
        ->and($part->badges()->pluck('name')->all())->toContain('Premium')
        ->and($part->compatibilities()->pluck('name')->all())->toContain('Apple: iPhone 14 Pro Max')
        ->and($part->relatedParts()->whereKey(74)->exists())->toBeTrue();
});

test('MobileSentrix enrich product command resolves sku and stores enrichment data', function () {
    createEnrichmentPart();
    fakeMobileSentrixProductEnrichmentHttp();

    $exitCode = Artisan::call('mobilesentrix:enrich-product', [
        'part_id_or_sku' => 'MS-73',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('MobileSentrix part 73 enriched.')
        ->and(Part::query()->findOrFail(73)->tags()->where('name', 'Premium')->exists())->toBeTrue();
});

test('public parts show falls back to local data when MobileSentrix enrichment fails', function () {
    $part = createEnrichmentPart(['name' => 'Fallback Screen']);

    Http::fake([
        'https://preprod.mobilesentrix.ca/api/rest/products/*' => Http::response(['message' => 'failed'], 500),
        'https://preprod.mobilesentrix.ca/api/rest/tags*' => Http::response(['message' => 'failed'], 500),
    ]);

    $this->get(route('parts.show', $part))
        ->assertOk()
        ->assertSee('Fallback Screen')
        ->assertSee('$50.00');

    expect($part->fresh()->last_enriched_at)->toBeNull();
});
