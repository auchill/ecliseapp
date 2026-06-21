<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobilesentrix_api_settings')) {
            Schema::create('mobilesentrix_api_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('environment')->default('staging')->index();
                $table->string('base_url')->nullable();
                $table->string('consumer_name')->nullable();
                $table->text('consumer_key')->nullable();
                $table->text('consumer_secret')->nullable();
                $table->text('access_token')->nullable();
                $table->text('access_token_secret')->nullable();
                $table->string('callback_url')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_authenticated_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('part_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('part_categories', 'mobilesentrix_category_id')) {
                $table->string('mobilesentrix_category_id')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('part_categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('mobilesentrix_category_id')->constrained('part_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('part_categories', 'level')) {
                $table->unsignedInteger('level')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('part_categories', 'children_count')) {
                $table->unsignedInteger('children_count')->default(0)->after('level');
            }
            if (! Schema::hasColumn('part_categories', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('description');
            }
            if (! Schema::hasColumn('part_categories', 'meta_title')) {
                $table->text('meta_title')->nullable()->after('meta_keywords');
            }
            if (! Schema::hasColumn('part_categories', 'is_anchor')) {
                $table->boolean('is_anchor')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('part_categories', 'is_part')) {
                $table->boolean('is_part')->default(true)->after('is_anchor');
            }
            if (! Schema::hasColumn('part_categories', 'has_children')) {
                $table->boolean('has_children')->default(false)->after('is_part');
            }
            if (! Schema::hasColumn('part_categories', 'image_url')) {
                $table->string('image_url')->nullable()->after('has_children');
            }
            if (! Schema::hasColumn('part_categories', 'status')) {
                $table->string('status')->default('active')->after('image_url')->index();
            }
            if (! Schema::hasColumn('part_categories', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('status');
            }
            if (! Schema::hasColumn('part_categories', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('raw_payload');
            }
        });

        Schema::table('part_brands', function (Blueprint $table): void {
            if (! Schema::hasColumn('part_brands', 'mobilesentrix_manufacturer_id')) {
                $table->string('mobilesentrix_manufacturer_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('part_brands', 'status')) {
                $table->string('status')->default('active')->after('is_active')->index();
            }
            if (! Schema::hasColumn('part_brands', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('status');
            }
            if (! Schema::hasColumn('part_brands', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('raw_payload');
            }
        });

        Schema::table('part_models', function (Blueprint $table): void {
            if (! Schema::hasColumn('part_models', 'mobilesentrix_model_id')) {
                $table->string('mobilesentrix_model_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('part_models', 'part_brand_id')) {
                $table->foreignId('part_brand_id')->nullable()->after('mobilesentrix_model_id')->constrained('part_brands')->nullOnDelete();
            }
            if (! Schema::hasColumn('part_models', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('description');
            }
            if (! Schema::hasColumn('part_models', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('raw_payload');
            }
        });

        Schema::table('parts', function (Blueprint $table): void {
            if (! Schema::hasColumn('parts', 'mobilesentrix_product_id')) {
                $table->string('mobilesentrix_product_id')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('parts', 'slug')) {
                $table->string('slug')->nullable()->index()->after('name');
            }
            if (! Schema::hasColumn('parts', 'new_sku')) {
                $table->string('new_sku')->nullable()->index()->after('sku');
            }
            if (! Schema::hasColumn('parts', 'barcode')) {
                $table->string('barcode')->nullable()->after('new_sku');
            }
            if (! Schema::hasColumn('parts', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
            if (! Schema::hasColumn('parts', 'product_extra_info')) {
                $table->text('product_extra_info')->nullable()->after('short_description');
            }
            if (! Schema::hasColumn('parts', 'mobilesentrix_url_key')) {
                $table->string('mobilesentrix_url_key')->nullable()->after('product_extra_info');
            }
            if (! Schema::hasColumn('parts', 'mobilesentrix_url')) {
                $table->string('mobilesentrix_url')->nullable()->after('mobilesentrix_url_key');
            }
            if (! Schema::hasColumn('parts', 'default_image')) {
                $table->string('default_image')->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('parts', 'markup_type')) {
                $table->string('markup_type')->default('none')->after('selling_price');
            }
            if (! Schema::hasColumn('parts', 'markup_value')) {
                $table->decimal('markup_value', 10, 2)->default(0)->after('markup_type');
            }
            if (! Schema::hasColumn('parts', 'stock_id')) {
                $table->string('stock_id')->nullable()->after('api_quantity');
            }
            if (! Schema::hasColumn('parts', 'is_in_stock')) {
                $table->boolean('is_in_stock')->default(false)->after('stock_id');
            }
            if (! Schema::hasColumn('parts', 'in_stock_qty')) {
                $table->unsignedInteger('in_stock_qty')->default(0)->after('is_in_stock');
            }
            foreach (['weight', 'height', 'width', 'length'] as $dimension) {
                if (! Schema::hasColumn('parts', $dimension)) {
                    $table->decimal($dimension, 10, 4)->nullable();
                }
            }
            if (! Schema::hasColumn('parts', 'hst_code')) {
                $table->string('hst_code')->nullable();
            }
            if (! Schema::hasColumn('parts', 'hst_description')) {
                $table->text('hst_description')->nullable();
            }
            if (! Schema::hasColumn('parts', 'manufacturer_id')) {
                $table->string('manufacturer_id')->nullable()->index();
            }
            if (! Schema::hasColumn('parts', 'manufacturer_text')) {
                $table->string('manufacturer_text')->nullable()->index();
            }
            if (! Schema::hasColumn('parts', 'model_id')) {
                $table->string('model_id')->nullable()->index();
            }
            if (! Schema::hasColumn('parts', 'model_text')) {
                $table->json('model_text')->nullable();
            }
            if (! Schema::hasColumn('parts', 'front_position')) {
                $table->string('front_position')->nullable();
            }
            if (! Schema::hasColumn('parts', 'front_position_text')) {
                $table->string('front_position_text')->nullable()->index();
            }
            if (! Schema::hasColumn('parts', 'warranty_period')) {
                $table->string('warranty_period')->nullable();
            }
            if (! Schema::hasColumn('parts', 'warranty_period_text')) {
                $table->string('warranty_period_text')->nullable();
            }
            if (! Schema::hasColumn('parts', 'product_badges')) {
                $table->string('product_badges')->nullable();
            }
            if (! Schema::hasColumn('parts', 'product_badges_text')) {
                $table->string('product_badges_text')->nullable();
            }
            if (! Schema::hasColumn('parts', 'featured')) {
                $table->boolean('featured')->default(false);
            }
            if (! Schema::hasColumn('parts', 'premium')) {
                $table->boolean('premium')->default(false);
            }
            if (! Schema::hasColumn('parts', 'end_of_life')) {
                $table->boolean('end_of_life')->default(false);
            }
            if (! Schema::hasColumn('parts', 'api_status')) {
                $table->string('api_status')->nullable()->index();
            }
            if (! Schema::hasColumn('parts', 'status')) {
                $table->string('status')->default('active')->index();
            }
            if (! Schema::hasColumn('parts', 'raw_payload')) {
                $table->json('raw_payload')->nullable();
            }
            if (! Schema::hasColumn('parts', 'api_updated_at')) {
                $table->timestamp('api_updated_at')->nullable();
            }
            if (! Schema::hasColumn('parts', 'last_price_synced_at')) {
                $table->timestamp('last_price_synced_at')->nullable();
            }
            if (! Schema::hasColumn('parts', 'last_stock_synced_at')) {
                $table->timestamp('last_stock_synced_at')->nullable();
            }
            if (! Schema::hasColumn('parts', 'synced_at')) {
                $table->timestamp('synced_at')->nullable();
            }
        });

        if (! Schema::hasTable('part_category_part')) {
            Schema::create('part_category_part', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('part_id')->constrained()->cascadeOnDelete();
                $table->foreignId('part_category_id')->constrained()->cascadeOnDelete();
                $table->string('mobilesentrix_category_id')->nullable()->index();
                $table->timestamps();
                $table->unique(['part_id', 'part_category_id']);
            });
        }

        if (! Schema::hasTable('mobilesentrix_sync_logs')) {
            Schema::create('mobilesentrix_sync_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_type')->index();
                $table->string('status')->default('started')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->text('message')->nullable();
                $table->json('error_details')->nullable();
                $table->timestamps();
            });
        }

        DB::table('parts')
            ->whereNull('cost_price')
            ->update(['cost_price' => DB::raw('COALESCE(api_price, price)')]);

        DB::table('parts')
            ->whereNull('default_image')
            ->whereNotNull('image_url')
            ->update(['default_image' => DB::raw('image_url')]);

        DB::table('parts')
            ->whereNull('status')
            ->update(['status' => 'active']);

        DB::table('parts')
            ->whereNull('synced_at')
            ->whereNotNull('last_synced_at')
            ->update(['synced_at' => DB::raw('last_synced_at')]);

        DB::table('parts')
            ->whereNotNull('part_category_id')
            ->get(['id', 'part_category_id'])
            ->each(function ($part): void {
                DB::table('part_category_part')->updateOrInsert(
                    ['part_id' => $part->id, 'part_category_id' => $part->part_category_id],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobilesentrix_sync_logs');
        Schema::dropIfExists('part_category_part');
        Schema::dropIfExists('mobilesentrix_api_settings');
    }
};
