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
            ['name' => 'Phone', 'code' => 'PHN'],
            ['name' => 'Laptop', 'code' => 'LAP'],
            ['name' => 'Tablet', 'code' => 'TAB'],
            ['name' => 'Desktop', 'code' => 'DES'],
            ['name' => 'Game Console', 'code' => 'GAM'],
            ['name' => 'Smart Watch', 'code' => 'WAT'],
            ['name' => 'Accessories', 'code' => 'ACC'],
            ['name' => 'Parts', 'code' => 'OTH'],
        ] as $index => $category) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'code' => $category['code'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }
    }
}
