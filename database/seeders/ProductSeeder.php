<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['sku' => 'ECL-IPH13-128-USED', 'name' => 'Used Apple iPhone 13 128GB Unlocked', 'category' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 13', 'condition' => 'Good', 'grade' => 'Grade B', 'color' => 'Black', 'network' => 'Unlocked', 'sizes' => ['storage 128GB'], 'cost' => 310, 'regular' => 449, 'sale' => 419, 'qty' => 5],
            ['sku' => 'ECL-IPH14PRO-256-USED', 'name' => 'Used Apple iPhone 14 Pro 256GB Unlocked', 'category' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 14 Pro', 'condition' => 'Excellent', 'grade' => 'Grade A', 'color' => 'Space Gray', 'network' => 'Unlocked', 'sizes' => ['storage 256GB'], 'cost' => 570, 'regular' => 769, 'sale' => 729, 'qty' => 3],
            ['sku' => 'ECL-S23U-256-USED', 'name' => 'Used Samsung Galaxy S23 Ultra 256GB Unlocked', 'category' => 'Phone', 'brand' => 'Samsung', 'model' => 'Galaxy S23 Ultra', 'condition' => 'Excellent', 'grade' => 'Grade A', 'color' => 'Black', 'network' => 'Unlocked', 'sizes' => ['storage 256GB'], 'cost' => 520, 'regular' => 719, 'sale' => null, 'qty' => 4],
            ['sku' => 'ECL-PIX8-128-USED', 'name' => 'Used Google Pixel 8 128GB Unlocked', 'category' => 'Phone', 'brand' => 'Google', 'model' => 'Pixel 8', 'condition' => 'Good', 'grade' => 'Grade B', 'color' => 'Blue', 'network' => 'Unlocked', 'sizes' => ['storage 128GB'], 'cost' => 360, 'regular' => 529, 'sale' => 499, 'qty' => 4],
            ['sku' => 'ECL-DELL-LAT5420-RFB', 'name' => 'Refurbished Dell Latitude 5420 16GB RAM 512GB SSD', 'category' => 'Laptop', 'brand' => 'Dell', 'model' => 'Latitude 5420', 'condition' => 'Excellent', 'grade' => 'Grade A', 'color' => 'Gray', 'network' => 'Wi-Fi', 'sizes' => ['memory 16GB', 'storage 512GB', 'screen_size 14-inch'], 'cost' => 390, 'regular' => 589, 'sale' => 549, 'qty' => 2],
            ['sku' => 'ECL-LEN-T14-RFB', 'name' => 'Refurbished Lenovo ThinkPad T14 16GB RAM 512GB SSD', 'category' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'ThinkPad T14', 'condition' => 'Excellent', 'grade' => 'Grade A', 'color' => 'Black', 'network' => 'Wi-Fi', 'sizes' => ['memory 16GB', 'storage 512GB', 'screen_size 14-inch'], 'cost' => 410, 'regular' => 629, 'sale' => null, 'qty' => 2],
            ['sku' => 'ECL-ANKER-30W-NEW', 'name' => 'New Anker USB-C 30W Charger', 'category' => 'Chargers', 'brand' => 'Anker', 'model' => '30W USB-C Charger', 'condition' => 'New', 'grade' => 'Ungraded', 'color' => 'White', 'network' => 'N/A', 'sizes' => [], 'cost' => 14, 'regular' => 29.99, 'sale' => null, 'qty' => 24],
            ['sku' => 'ECL-USBC-CABLE-NEW', 'name' => 'New USB-C to USB-C Charging Cable', 'category' => 'Cables', 'brand' => 'Generic', 'model' => 'USB-C Cable', 'condition' => 'New', 'grade' => 'Ungraded', 'color' => 'White', 'network' => 'N/A', 'sizes' => [], 'cost' => 5, 'regular' => 14.99, 'sale' => 11.99, 'qty' => 40],
            ['sku' => 'ECL-IPH15-CASE-NEW', 'name' => 'New iPhone 15 Protective Case', 'category' => 'Cases', 'brand' => 'Generic', 'model' => 'Protective Case', 'condition' => 'New', 'grade' => 'Ungraded', 'color' => 'Black', 'network' => 'N/A', 'sizes' => [], 'cost' => 8, 'regular' => 24.99, 'sale' => null, 'qty' => 32],
            ['sku' => 'ECL-LOGI-MOUSE-NEW', 'name' => 'New Logitech Wireless Mouse', 'category' => 'Mice', 'brand' => 'Logitech', 'model' => 'Wireless Mouse', 'condition' => 'New', 'grade' => 'Ungraded', 'color' => 'Black', 'network' => 'N/A', 'sizes' => [], 'cost' => 18, 'regular' => 39.99, 'sale' => 34.99, 'qty' => 18],
        ];

        foreach ($products as $index => $data) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'product_category_id' => ProductCategory::query()->where('slug', Str::slug($data['category']))->value('id'),
                    'product_brand_id' => ProductBrand::query()->where('slug', Str::slug($data['brand']))->value('id'),
                    'product_model_id' => ProductModel::query()->where('slug', Str::slug($data['brand'].' '.$data['model']))->value('id'),
                    'product_condition_id' => ProductCondition::query()->where('slug', Str::slug($data['condition']))->value('id'),
                    'product_grade_id' => ProductGrade::query()->where('slug', Str::slug($data['grade']))->value('id'),
                    'product_color_id' => ProductColor::query()->where('slug', Str::slug($data['color']))->value('id'),
                    'product_network_id' => ProductNetwork::query()->where('slug', Str::slug($data['network']))->value('id'),
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'short_description' => 'Eclise-owned sample inventory item for storefront testing.',
                    'description' => $data['name'].' inspected and prepared by Eclise Technology Inc.',
                    'cost_price' => $data['cost'],
                    'regular_price' => $data['regular'],
                    'sale_price' => $data['sale'],
                    'quantity' => $data['qty'],
                    'low_stock_threshold' => 1,
                    'source' => 'manual',
                    'is_featured' => $index < 4,
                    'is_active' => true,
                ],
            );

            $sizeIds = collect($data['sizes'])
                ->map(fn (string $size): ?int => ProductSize::query()->where('slug', Str::slug($size))->value('id'))
                ->filter()
                ->values()
                ->all();
            $product->sizes()->sync($sizeIds);

            $product->images()->updateOrCreate(
                ['sort_order' => 0],
                [
                    'image_url' => '/images/brand/eclise-thumb-grey.png',
                    'alt_text' => $product->name,
                    'is_primary' => true,
                ],
            );
        }
    }
}
