<?php

namespace Database\Seeders;

use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Apple', 'Samsung', 'HP', 'Dell', 'Lenovo', 'Eclise'] as $index => $name) {
            ProductBrand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
        }

        foreach (['Phones', 'Laptops', 'Accessories', 'Tablets', 'Chargers'] as $index => $name) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
        }

        // foreach (['Apple Parts', 'Samsung Parts', 'HP Parts', 'Dell Parts', 'Lenovo Parts'] as $index => $name) {
        //     PartBrand::query()->updateOrCreate(
        //         ['slug' => Str::slug($name)],
        //         ['name' => $name, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
        //     );
        // }

        // foreach (['Screens', 'Batteries', 'Charging Ports', 'Keyboards', 'Cameras', 'Motherboards'] as $index => $name) {
        //     PartCategory::query()->updateOrCreate(
        //         ['slug' => Str::slug($name)],
        //         ['name' => $name, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
        //     );
        // }
    }
}
