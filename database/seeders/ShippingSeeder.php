<?php

namespace Database\Seeders;

use App\Models\ShippingDiscountRule;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $normal = ShippingMethod::query()->updateOrCreate(
            ['code' => 'normal'],
            [
                'name' => 'Normal Shipping',
                'description' => 'Standard shipping for orders and repaired devices.',
                'base_cost' => 20.00,
                'delivery_days_min' => 3,
                'delivery_days_max' => 7,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        $overnight = ShippingMethod::query()->updateOrCreate(
            ['code' => 'overnight'],
            [
                'name' => 'Overnight Shipping',
                'description' => 'Priority next-business-day shipping where available.',
                'base_cost' => 45.00,
                'delivery_days_min' => 1,
                'delivery_days_max' => 1,
                'is_active' => true,
                'sort_order' => 20,
            ],
        );

        ShippingDiscountRule::query()->updateOrCreate(
            ['name' => 'Free normal shipping over $300'],
            [
                'minimum_order_amount' => 300.00,
                'discount_type' => 'free_shipping',
                'discount_value' => null,
                'shipping_method_id' => $normal->id,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ],
        );

        ShippingDiscountRule::query()->updateOrCreate(
            ['name' => '50% off overnight shipping over $500'],
            [
                'minimum_order_amount' => 500.00,
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'shipping_method_id' => $overnight->id,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ],
        );
    }
}
