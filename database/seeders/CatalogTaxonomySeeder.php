<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Phone',
            'Laptop',
            'Tablet',
            'Desktop',
            'Game Console',
            'Smart Watch',
            'Accessories',
            'Parts',
            // 'Phones',
            // 'Laptops',
            // 'Chargers',
        ] as $index => $name) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
        }

    }
}
