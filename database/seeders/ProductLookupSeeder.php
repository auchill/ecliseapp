<?php

namespace Database\Seeders;

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

class ProductLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->categories();
        $brands = $this->brands();
        $this->conditions();
        $this->grades();
        $this->colors();
        $this->networks();
        $this->sizes();
        $this->models($brands);
    }

    private function categories(): void
    {
        $categories = [
            'Phone' => 'PHN',
            'Tablet' => 'TAB',
            'Laptop' => 'LAP',
            'Desktop' => 'DES',
            'Smart Watch' => 'WAT',
            'Accessories' => 'ACS',
            'Phone Accessories' => 'PHA',
            'Computer Accessories' => 'CTA',
            'Chargers' => 'CHG',
            'Cables' => 'CBL',
            'Cases' => 'CAS',
            'Screen Protectors' => 'SCP',
            'Keyboards' => 'KBD',
            'Mice' => 'MIC',
            'Monitors' => 'MON',
            'Storage Devices' => 'STO',
            'Networking' => 'NET',
        ];

        $position = 1;
        foreach ($categories as $name => $code) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'code' => $code, 'is_active' => true, 'sort_order' => $position * 10],
            );
            $position++;
        }
    }

    private function brands(): array
    {
        return collect([
            'Apple', 'Samsung', 'Google', 'Motorola', 'T-Mobile', 'Dell', 'HP', 'Lenovo',
            'ASUS', 'Acer', 'Microsoft', 'LG', 'Sony', 'Anker', 'Logitech', 'Eclise', 'Generic',
        ])->mapWithKeys(function (string $name, int $index): array {
            $brand = ProductBrand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
            );

            return [$name => $brand];
        })->all();
    }

    private function conditions(): void
    {
        foreach (['New', 'Open Box', 'Like New', 'Excellent', 'Good', 'Fair', 'For Parts or Repair'] as $index => $name) {
            ProductCondition::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    private function grades(): void
    {
        foreach (['Grade A', 'Grade B', 'Grade C', 'Grade D', 'Ungraded'] as $index => $name) {
            ProductGrade::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    private function colors(): void
    {
        foreach (['Black', 'White', 'Silver', 'Gold', 'Space Gray', 'Gray', 'Blue', 'Red', 'Green', 'Purple', 'Pink', 'Yellow', 'Orange', 'Natural Titanium', 'Blue Titanium', 'White Titanium', 'Black Titanium', 'Mixed', 'N/A'] as $index => $name) {
            ProductColor::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    private function networks(): void
    {
        foreach (['Unlocked', 'T-Mobile', 'AT&T', 'Verizon', 'Rogers', 'Bell', 'TELUS', 'Freedom Mobile', 'GSM', 'CDMA', 'CDMA / GSM', 'Wi-Fi', 'Wi-Fi + Cellular', 'GPS', 'GPS + Cellular', 'Ethernet', 'N/A'] as $index => $name) {
            ProductNetwork::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    private function sizes(): void
    {
        $groups = [
            'storage' => ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB', '2TB', 'N/A'],
            'memory' => ['4GB', '8GB', '12GB', '16GB', '24GB', '32GB', '64GB', '128GB', 'N/A'],
            'screen_size' => ['6.1-inch', '6.7-inch', '10.2-inch', '10.9-inch', '11-inch', '12.9-inch', '13-inch', '14-inch', '15.6-inch', '16-inch', '17.3-inch', '24-inch', '27-inch', '32-inch', 'N/A'],
            'watch_size' => ['38mm', '40mm', '41mm', '42mm', '44mm', '45mm', '46mm', '49mm', 'N/A'],
        ];

        foreach ($groups as $type => $sizes) {
            foreach ($sizes as $index => $name) {
                ProductSize::query()->updateOrCreate(
                    ['slug' => Str::slug($type.' '.$name)],
                    ['name' => $name, 'type' => $type, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
                );
            }
        }
    }

    private function models(array $brands): void
    {
        $models = [
            'Apple' => ['iPhone 11', 'iPhone 11 Pro', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 13', 'iPhone 13 Pro', 'iPhone 14', 'iPhone 14 Pro', 'iPhone 15', 'iPhone 15 Pro', 'iPhone 16', 'iPhone 16 Pro', 'iPad 9th Generation (2021)', 'iPad 10th Generation', 'iPad Air', 'iPad Pro 11-inch', 'MacBook Air 13-inch', 'MacBook Pro 14-inch', 'Apple Watch Series 9', 'Apple Watch Series 10', 'Apple Watch Ultra 2'],
            'Samsung' => ['Galaxy S20', 'Galaxy S20 Ultra', 'Galaxy S21', 'Galaxy S22', 'Galaxy S23', 'Galaxy S23 Ultra', 'Galaxy S24', 'Galaxy S24 Ultra', 'Galaxy Z Flip 5', 'Galaxy Z Fold 5', 'Galaxy Tab S9', 'Galaxy Watch 6'],
            'Google' => ['Pixel 7', 'Pixel 7 Pro', 'Pixel 8', 'Pixel 8 Pro', 'Pixel 9', 'Pixel 9 Pro', 'Pixel Watch 2'],
            'Motorola' => ['Moto G Power', 'Moto G Stylus', 'Motorola Razr'],
            'T-Mobile' => ['REVVL 6', 'REVVL 6 Pro', 'REVVL 6X'],
            'Dell' => ['Inspiron 15', 'Latitude 5420', 'Latitude 7430', 'XPS 13', 'XPS 15', 'OptiPlex 7090'],
            'HP' => ['HP 15', 'EliteBook 840', 'ProBook 450', 'Pavilion 15', 'Envy x360'],
            'Lenovo' => ['ThinkPad T14', 'ThinkPad X1 Carbon', 'IdeaPad 3', 'IdeaPad 5', 'Yoga 7'],
            'Anker' => ['30W USB-C Charger'],
            'Logitech' => ['Wireless Mouse'],
            'Generic' => ['USB-C Cable', 'Protective Case'],
        ];

        foreach ($models as $brandName => $names) {
            $brand = $brands[$brandName] ?? null;

            if (! $brand) {
                continue;
            }

            foreach ($names as $index => $name) {
                ProductModel::query()->updateOrCreate(
                    ['slug' => Str::slug($brandName.' '.$name)],
                    ['name' => $name, 'product_brand_id' => $brand->id, 'status' => 'active', 'sort_order' => ($index + 1) * 10],
                );
            }
        }
    }
}
