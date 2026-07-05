<?php

use App\Models\Part;
use App\Models\PartBadge;
use App\Models\PartCategory;
use App\Models\PartWarranty;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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

        if (str_contains($url, '/api/rest/products/151485')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $record = [
                'entity_id' => '151485',
                'sku' => '107082022487',
                'status' => 1,
                'name' => 'Replacement Battery For iPhone 14 Pro Max',
                'description' => '<ul><li>Battery replacement.</li></ul>',
                'price' => '25.41',
                'category_ids' => ['165'],
                'is_in_stock' => true,
                'in_stock_qty' => 5,
                'image_url' => 'https://cdn.example.test/battery-small.jpg',
                'warranty_period' => '5941',
                'warranty_period_text' => '1 Year',
                'product_badges' => '33755',
                'product_badges_text' => 'Batteries - Ampsentrix Basic',
                'product_badges_icon_url' => 'https://cdn.example.test/basic-badge.png',
            ];

            if (str_contains((string) ($query['load'] ?? ''), 'image_gallery')) {
                $record['image_gallery'] = [
                    'https://cdn.example.test/battery-1.jpg',
                    'https://cdn.example.test/battery-2.jpg',
                    'https://cdn.example.test/battery-1.jpg',
                ];
            }

            if (str_contains((string) ($query['load'] ?? ''), 'related_product')) {
                $record['related_product'] = ['74'];
            }

            return Http::response(['data' => $record]);
        }

        if (str_contains($url, '/api/rest/products?')) {
            return Http::response([
                [
                    'entity_id' => '151485',
                    'sku' => '107082022487',
                    'status' => 1,
                    'name' => 'Replacement Battery For iPhone 14 Pro Max',
                    'description' => 'Battery replacement.',
                    'price' => '25.41',
                    'category_ids' => ['165'],
                    'is_in_stock' => true,
                    'in_stock_qty' => 5,
                    'image_url' => 'https://cdn.example.test/battery-small.jpg',
                ],
            ]);
        }

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
                'warranty_period' => '7645',
                'warranty_period_text' => 'Lifetime Warranty',
                'warranty_icon_url' => 'https://cdn.example.test/lifetime-warranty.png',
                'image_url' => 'https://cdn.example.test/default.jpg',
                'product_badges' => '7627',
                'product_badges_text' => 'Premium',
                'product_badges_bg' => '#1677ff',
                'product_badges_icon_url' => 'https://cdn.example.test/premium-badge.png',
            ];

            if (str_contains((string) ($query['load'] ?? ''), 'image_gallery')) {
                $record['image_gallery'] = [
                    ['url' => 'https://cdn.example.test/gallery-1.jpg', 'thumbnail_url' => 'https://cdn.example.test/gallery-1-thumb.jpg', 'label' => 'Front view'],
                    ['url' => 'https://cdn.example.test/gallery-2.jpg', 'large_image_url' => 'https://cdn.example.test/gallery-2-large.jpg', 'label' => 'Back view'],
                    ['url' => 'https://cdn.example.test/gallery-1.jpg', 'label' => 'Duplicate front view'],
                ];
            }

            if (str_contains((string) ($query['load'] ?? ''), 'related_product')) {
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
                'product_badges' => '990',
                'product_badges_text' => 'Genuine',
                'device_color_text' => 'Black',
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
                [
                    'sku' => '107082022487',
                    'new_sku' => null,
                    'tag' => ['battery', 'ampsentrix basic'],
                    'compatibility' => [
                        'Apple' => ['iPhone 14 Pro Max'],
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
        ->assertSee('Product Description')
        ->assertSee('Add To Cart')
        ->assertSee('OLED')
        ->assertSee('iPhone 14 Pro Max')
        ->assertSee('Related Products')
        ->assertSee('data-part-gallery-image', false)
        ->assertSee('/images/parts/warranties/warranty-lifetime.svg', false)
        ->assertSee('/images/parts/badges/badge-default.svg', false)
        ->assertDontSee('alert("x")', false)
        ->assertDontSee('onclick', false)
        ->assertDontSee('src=""', false);

    $part = $part->fresh();

    expect($part->last_enriched_at)->not->toBeNull()
        ->and($part->raw_payload['entity_id'])->toBe('73')
        ->and($part->tags_raw_payload[0]['sku'])->toBe('MS-73')
        ->and($part->gallery_images)->toHaveCount(3)
        ->and($part->gallery_images->where('image_url', 'https://cdn.example.test/gallery-1.jpg'))->toHaveCount(1)
        ->and($part->tag_labels)->toContain('OLED')
        ->and($part->display_badge_name)->toBe('Premium')
        ->and($part->display_badge_icon_url)->toContain('/images/parts/badges/badge-default.svg')
        ->and($part->warranty_period)->toBe('7645')
        ->and($part->display_warranty_label)->toBe('Lifetime Warranty')
        ->and($part->display_warranty_icon_url)->toContain('/images/parts/warranties/warranty-lifetime.svg')
        ->and($part->compatibility_labels)->toContain('Apple: iPhone 14 Pro Max')
        ->and($part->related_product_parts->pluck('id'))->toContain(74);
});

test('MobileSentrix enrich product command resolves sku and stores enrichment data', function () {
    createEnrichmentPart();
    fakeMobileSentrixProductEnrichmentHttp();

    $exitCode = Artisan::call('mobilesentrix:enrich-product', [
        'part_id_or_sku' => 'MS-73',
        '--force' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('MobileSentrix part 73 enriched.')
        ->and($output)->toContain('Description updated: yes')
        ->and($output)->toContain('Warranty detected: Lifetime Warranty')
        ->and(Part::query()->findOrFail(73)->tag_labels)->toContain('Premium');
});

test('MobileSentrix enrich product command resolves numeric sku 107082022487 and stores actual gallery warranty and badge data', function () {
    fakeMobileSentrixProductEnrichmentHttp();

    $exitCode = Artisan::call('mobilesentrix:enrich-product', [
        'part_id_or_sku' => '107082022487',
        '--force' => true,
    ]);
    $output = Artisan::output();

    $part = Part::query()->where('sku', '107082022487')->firstOrFail();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('MobileSentrix part 151485 enriched.')
        ->and($part->gallery_images)->toHaveCount(3)
        ->and($part->display_warranty_label)->toBe('1 Year')
        ->and($part->display_warranty_icon_url)->toContain('/images/parts/warranties/warranty-1-year.svg')
        ->and($part->display_badge_name)->toBe('Basic')
        ->and($part->display_badge_icon_url)->toContain('/images/parts/badges/badge-basic.svg')
        ->and($part->related_product_parts->pluck('id'))->toContain(74);
});

test('obsolete lookup schema is removed while display resolvers and category schema remain', function () {
    expect(Schema::hasTable('part_warranties'))->toBeFalse()
        ->and(Schema::hasTable('part_badges'))->toBeFalse()
        ->and(Schema::hasTable('part_images'))->toBeFalse()
        ->and(Schema::hasTable('part_tags'))->toBeFalse()
        ->and(Schema::hasTable('part_compatibilities'))->toBeFalse()
        ->and(Schema::hasColumn('parts', 'part_warranty_id'))->toBeFalse()
        ->and(Schema::hasColumn('parts', 'part_category_id'))->toBeTrue()
        ->and(Schema::hasTable('part_categories'))->toBeTrue()
        ->and(Schema::hasTable('part_category_part'))->toBeTrue()
        ->and(PartWarranty::WARRANTY_LABELS['7627'])->toBe('No Warranty')
        ->and(PartWarranty::WARRANTY_LABELS['7630'])->toBe('30 Days')
        ->and(PartWarranty::WARRANTY_LABELS['7633'])->toBe('60 Days')
        ->and(PartWarranty::WARRANTY_LABELS['7636'])->toBe('90 Days')
        ->and(PartWarranty::WARRANTY_LABELS['7642'])->toBe('6 Months')
        ->and(PartWarranty::WARRANTY_LABELS['7648'])->toBe('1 Year')
        ->and(PartWarranty::WARRANTY_LABELS['7645'])->toBe('Lifetime Warranty')
        ->and(PartWarranty::displayIconUrl('7645'))->toContain('/images/parts/warranties/warranty-lifetime.svg')
        ->and(PartBadge::displayIconUrl('Genuine'))->toContain('/images/parts/badges/badge-genuine.svg');
});

test('part image gallery enrichment is idempotent in direct part data', function () {
    $part = createEnrichmentPart();
    fakeMobileSentrixProductEnrichmentHttp();

    Artisan::call('mobilesentrix:enrich-product', ['part_id_or_sku' => 'MS-73', '--force' => true]);
    Artisan::call('mobilesentrix:enrich-product', ['part_id_or_sku' => 'MS-73', '--force' => true]);

    expect($part->fresh()->gallery_images)->toHaveCount(3)
        ->and($part->fresh()->gallery_images->where('image_url', 'https://cdn.example.test/gallery-1.jpg'))->toHaveCount(1);
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
