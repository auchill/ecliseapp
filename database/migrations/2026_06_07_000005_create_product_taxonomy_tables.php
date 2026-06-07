<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_brand_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->after('product_brand_id')->constrained()->nullOnDelete();
        });

        $now = now();

        DB::table('categories')
            ->where('type', 'product')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'created_at', 'updated_at'])
            ->each(function ($category) use ($now): void {
                DB::table('product_categories')->updateOrInsert(
                    ['slug' => $category->slug],
                    [
                        'name' => $category->name,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => $category->created_at ?? $now,
                        'updated_at' => $category->updated_at ?? $now,
                    ],
                );
            });

        DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->each(function (string $brand) use ($now): void {
                DB::table('product_brands')->updateOrInsert(
                    ['slug' => Str::slug($brand)],
                    [
                        'name' => $brand,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

        DB::table('products')
            ->orderBy('id')
            ->get(['id', 'category_id', 'brand'])
            ->each(function ($product): void {
                $updates = [];

                if ($product->category_id) {
                    $legacyCategory = DB::table('categories')->where('id', $product->category_id)->first(['slug']);
                    $productCategory = $legacyCategory
                        ? DB::table('product_categories')->where('slug', $legacyCategory->slug)->first(['id'])
                        : null;

                    if ($productCategory) {
                        $updates['product_category_id'] = $productCategory->id;
                    }
                }

                if ($product->brand) {
                    $productBrand = DB::table('product_brands')->where('slug', Str::slug($product->brand))->first(['id']);

                    if ($productBrand) {
                        $updates['product_brand_id'] = $productBrand->id;
                    }
                }

                if ($updates !== []) {
                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropConstrainedForeignId('product_brand_id');
        });

        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_brands');
    }
};
