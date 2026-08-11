<?php

namespace Database\Seeders;

use App\Models\EcliseMarkup;
use App\Services\MobileSentrixMarkupService;
use Illuminate\Database\Seeder;

/**
 * Eclise retail markup applied on top of MobileSentrix cost.
 *
 * Procurement records the MobileSentrix base price, never these marked-up figures.
 */
class EcliseMarkupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'item_type' => EcliseMarkup::ITEM_TYPE_PARTS,
                'scope_type' => EcliseMarkup::SCOPE_ALL,
                'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
                'markup_value' => 25.00,
                'priority' => 1,
            ],
            [
                'item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES,
                'scope_type' => EcliseMarkup::SCOPE_ALL,
                'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
                'markup_value' => 20.00,
                'priority' => 1,
            ],
            [
                'item_type' => EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES,
                'scope_type' => EcliseMarkup::SCOPE_BRAND,
                'brand_text' => 'Apple',
                'brand_normalized' => EcliseMarkup::normalizeBrand('Apple'),
                'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
                'markup_value' => 28.00,
                'priority' => 10,
            ],
        ] as $rule) {
            EcliseMarkup::query()->updateOrCreate(
                [
                    'item_type' => $rule['item_type'],
                    'scope_type' => $rule['scope_type'],
                    'brand_normalized' => $rule['brand_normalized'] ?? null,
                ],
                $rule + ['is_active' => true],
            );
        }

        MobileSentrixMarkupService::flushRuleCache();
    }
}
