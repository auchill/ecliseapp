<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORY_SLUGS = [
        'phones' => ['canonical' => 'phone', 'name' => 'Phone', 'code' => 'PHN'],
        'tablets' => ['canonical' => 'tablet', 'name' => 'Tablet', 'code' => 'TAB'],
        'laptops' => ['canonical' => 'laptop', 'name' => 'Laptop', 'code' => 'LAP'],
        'desktops' => ['canonical' => 'desktop', 'name' => 'Desktop', 'code' => 'DES'],
        'smart-watches' => ['canonical' => 'smart-watch', 'name' => 'Smart Watch', 'code' => 'WAT'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('product_categories') || ! Schema::hasTable('products')) {
            return;
        }

        foreach (self::CATEGORY_SLUGS as $duplicateSlug => $canonical) {
            $duplicate = DB::table('product_categories')->where('slug', $duplicateSlug)->first();
            $canonicalCategory = DB::table('product_categories')->where('slug', $canonical['canonical'])->first();

            if (! $duplicate) {
                continue;
            }

            if (! $canonicalCategory) {
                DB::table('product_categories')->where('id', $duplicate->id)->update([
                    'name' => $canonical['name'],
                    'slug' => $canonical['canonical'],
                    'code' => $canonical['code'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('products')
                ->where('product_category_id', $duplicate->id)
                ->update([
                    'product_category_id' => $canonicalCategory->id,
                    'updated_at' => now(),
                ]);

            DB::table('product_categories')->where('id', $duplicate->id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        DB::table('product_categories')
            ->whereIn('slug', array_keys(self::CATEGORY_SLUGS))
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
