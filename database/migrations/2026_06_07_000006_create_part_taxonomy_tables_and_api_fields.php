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
        Schema::create('part_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('part_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('parts', function (Blueprint $table): void {
            $table->foreignId('part_brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('part_category_id')->nullable()->after('part_brand_id')->constrained()->nullOnDelete();
            $table->string('sku')->nullable()->after('name');
            $table->string('internal_sku')->nullable()->after('sku');
            $table->string('external_api_id')->nullable()->after('internal_sku');
            $table->string('external_api_source')->nullable()->after('external_api_id');
            $table->text('description')->nullable()->after('external_api_source');
            $table->decimal('cost_price', 10, 2)->nullable()->after('description');
            $table->decimal('selling_price', 10, 2)->nullable()->after('price');
            $table->decimal('api_price', 10, 2)->nullable()->after('selling_price');
            $table->decimal('final_price', 10, 2)->nullable()->after('api_price');
            $table->unsignedInteger('quantity')->default(0)->after('final_price');
            $table->unsignedInteger('api_quantity')->nullable()->after('quantity');
            $table->string('availability_status')->nullable()->after('api_quantity');
            $table->string('condition')->nullable()->after('availability_status');
            $table->json('compatibility')->nullable()->after('model_compatibility');
            $table->json('specifications')->nullable()->after('compatibility');
            $table->string('image_url')->nullable()->after('image_path');
            $table->string('local_image_path')->nullable()->after('image_url');
            $table->boolean('is_api_item')->default(false)->after('local_image_path');
            $table->boolean('is_active')->default(true)->after('is_api_item');
            $table->unique(['external_api_source', 'external_api_id']);
        });

        $now = now();

        DB::table('parts')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->each(function (string $brand) use ($now): void {
                DB::table('part_brands')->updateOrInsert(
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

        DB::table('parts')
            ->whereNotNull('part_category')
            ->where('part_category', '!=', '')
            ->distinct()
            ->orderBy('part_category')
            ->pluck('part_category')
            ->each(function (string $category) use ($now): void {
                DB::table('part_categories')->updateOrInsert(
                    ['slug' => Str::slug($category)],
                    [
                        'name' => $category,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

        DB::table('parts')
            ->orderBy('id')
            ->get(['id', 'brand', 'part_category', 'price', 'stock_status', 'supplier', 'image_path'])
            ->each(function ($part): void {
                $brand = $part->brand
                    ? DB::table('part_brands')->where('slug', Str::slug($part->brand))->first(['id'])
                    : null;
                $category = $part->part_category
                    ? DB::table('part_categories')->where('slug', Str::slug($part->part_category))->first(['id'])
                    : null;

                DB::table('parts')->where('id', $part->id)->update([
                    'part_brand_id' => $brand?->id,
                    'part_category_id' => $category?->id,
                    'selling_price' => $part->price,
                    'final_price' => $part->price,
                    'availability_status' => $part->stock_status,
                    'external_api_source' => $part->supplier,
                    'local_image_path' => $part->image_path,
                    'is_api_item' => false,
                    'is_active' => true,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->dropUnique(['external_api_source', 'external_api_id']);
            $table->dropConstrainedForeignId('part_category_id');
            $table->dropConstrainedForeignId('part_brand_id');
            $table->dropColumn([
                'sku',
                'internal_sku',
                'external_api_id',
                'external_api_source',
                'description',
                'cost_price',
                'selling_price',
                'api_price',
                'final_price',
                'quantity',
                'api_quantity',
                'availability_status',
                'condition',
                'compatibility',
                'specifications',
                'image_url',
                'local_image_path',
                'is_api_item',
                'is_active',
            ]);
        });

        Schema::dropIfExists('part_categories');
        Schema::dropIfExists('part_brands');
    }
};
