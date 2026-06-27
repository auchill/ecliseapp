<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->index('name', 'parts_name_search_idx');
            $table->index('sku', 'parts_sku_search_idx');
            $table->index('part_brand_id', 'parts_brand_search_idx');
            $table->index('part_model_id', 'parts_model_search_idx');
            $table->index('is_in_stock', 'parts_stock_search_idx');
        });

        Schema::table('part_brands', function (Blueprint $table): void {
            $table->index('name', 'part_brands_name_search_idx');
        });

        Schema::table('part_models', function (Blueprint $table): void {
            $table->index('name', 'part_models_name_search_idx');
        });
    }

    public function down(): void
    {
        Schema::table('part_models', function (Blueprint $table): void {
            $table->dropIndex('part_models_name_search_idx');
        });

        Schema::table('part_brands', function (Blueprint $table): void {
            $table->dropIndex('part_brands_name_search_idx');
        });

        Schema::table('parts', function (Blueprint $table): void {
            $table->dropIndex('parts_stock_search_idx');
            $table->dropIndex('parts_model_search_idx');
            $table->dropIndex('parts_brand_search_idx');
            $table->dropIndex('parts_sku_search_idx');
            $table->dropIndex('parts_name_search_idx');
        });
    }
};
