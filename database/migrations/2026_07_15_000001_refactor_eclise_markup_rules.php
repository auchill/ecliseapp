<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eclise_markup')) {
            return;
        }

        Schema::table('eclise_markup', function (Blueprint $table): void {
            if (! Schema::hasColumn('eclise_markup', 'brand_text')) {
                $table->string('brand_text')->nullable()->after('category_id');
            }

            if (! Schema::hasColumn('eclise_markup', 'brand_normalized')) {
                $table->string('brand_normalized')->nullable()->after('brand_text');
            }

            if (! Schema::hasColumn('eclise_markup', 'min_price')) {
                $table->decimal('min_price', 12, 2)->nullable()->after('brand_normalized');
            }

            if (! Schema::hasColumn('eclise_markup', 'max_price')) {
                $table->decimal('max_price', 12, 2)->nullable()->after('min_price');
            }
        });

        Schema::table('eclise_markup', function (Blueprint $table): void {
            $table->index(['item_type', 'scope_type', 'brand_normalized', 'is_active'], 'eclise_markup_brand_lookup_index');
            $table->index(['item_type', 'scope_type', 'min_price', 'max_price', 'is_active'], 'eclise_markup_price_range_lookup_index');
        });

        DB::table('eclise_markup')
            ->where('scope_type', 'category')
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('eclise_markup')) {
            return;
        }

        Schema::table('eclise_markup', function (Blueprint $table): void {
            $table->dropIndex('eclise_markup_brand_lookup_index');
            $table->dropIndex('eclise_markup_price_range_lookup_index');
        });

        Schema::table('eclise_markup', function (Blueprint $table): void {
            foreach (['brand_text', 'brand_normalized', 'min_price', 'max_price'] as $column) {
                if (Schema::hasColumn('eclise_markup', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
