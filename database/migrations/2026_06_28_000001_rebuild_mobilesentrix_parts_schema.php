<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['parts', 'part_categories', 'part_brands', 'part_models', 'part_category_part'] as $table) {
            $this->backupTable($table);
        }

        foreach ([
            'part_related_parts',
            'part_part_tag',
            'part_tags',
            'part_images',
            'part_compatibilities',
            'part_model_part',
            'part_category_part',
            'parts',
            'part_categories',
            'part_models',
            'part_brands',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('part_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('external_brand_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('source_field')->nullable();
            $table->string('raw_value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('status')->default('active')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('part_models', function (Blueprint $table): void {
            $table->id();
            $table->string('external_model_id')->nullable()->index();
            $table->foreignId('part_brand_id')->nullable()->constrained('part_brands')->nullOnDelete();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('source_field')->nullable();
            $table->text('raw_value')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('description')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('part_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('parent_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->longText('meta_keywords')->nullable();
            $table->longText('meta_title')->nullable();
            $table->boolean('is_anchor')->default(false)->index();
            $table->boolean('is_part')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->boolean('has_children')->default(false)->index();
            $table->longText('image_url')->nullable();
            $table->unsignedInteger('level')->nullable()->index();
            $table->unsignedInteger('children_count')->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('part_brand_id')->nullable()->constrained('part_brands')->nullOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->foreignId('part_model_id')->nullable()->constrained('part_models')->nullOnDelete();

            $table->string('sku')->nullable()->index();
            $table->string('new_sku')->nullable()->index();
            $table->timestamp('api_updated_at')->nullable()->index();
            $table->string('api_status')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->nullable()->unique();
            $table->string('url_key')->nullable();
            $table->string('print_invoice_name')->nullable();
            $table->string('battery_volt')->nullable();
            $table->string('battery_mah')->nullable();
            $table->string('battery_wh')->nullable();
            $table->string('battery_weight')->nullable();
            $table->decimal('weight', 12, 4)->nullable();
            $table->decimal('height', 12, 4)->nullable();
            $table->decimal('width', 12, 4)->nullable();
            $table->decimal('length', 12, 4)->nullable();
            $table->dateTime('product_hold_date')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->string('front_position')->nullable()->index();
            $table->boolean('premium')->default(false)->index();
            $table->boolean('end_of_life')->default(false)->index();
            $table->string('warranty_period')->nullable();
            $table->string('product_badges')->nullable();
            $table->string('manufacturer')->nullable()->index();
            $table->longText('description')->nullable();
            $table->longText('short_description')->nullable();
            $table->longText('meta_title')->nullable();
            $table->longText('meta_keyword')->nullable();
            $table->longText('meta_description')->nullable();
            $table->string('model')->nullable()->index();
            $table->longText('product_extra_info')->nullable();
            $table->boolean('is_in_stock')->default(false)->index();
            $table->json('category_ids')->nullable();
            $table->longText('url')->nullable();
            $table->decimal('regular_price_with_tax', 12, 4)->nullable();
            $table->decimal('regular_price_without_tax', 12, 4)->nullable();
            $table->decimal('final_price_with_tax', 12, 4)->nullable();
            $table->decimal('final_price_without_tax', 12, 4)->nullable();
            $table->decimal('customer_price', 12, 4)->nullable();
            $table->string('display_currency')->nullable();
            $table->boolean('is_saleable')->nullable()->index();
            $table->longText('image_url')->nullable();
            $table->json('image_gallery')->nullable();
            $table->unsignedInteger('in_stock_qty')->default(0)->index();
            $table->boolean('is_preorder')->default(false)->index();
            $table->string('stock_id')->nullable()->index();
            $table->string('attribute_set')->nullable()->index();
            $table->json('related_product')->nullable();
            $table->string('brand_text')->nullable()->index();
            $table->string('color_bg')->nullable();
            $table->string('color_text')->nullable()->index();
            $table->string('device_carrier_text')->nullable()->index();
            $table->string('device_color_text')->nullable()->index();
            $table->string('device_grade_text')->nullable()->index();
            $table->string('device_manufacturer_text')->nullable()->index();
            $table->string('device_model_text')->nullable()->index();
            $table->string('device_size_text')->nullable()->index();
            $table->string('front_position_text')->nullable()->index();
            $table->string('manufacturer_text')->nullable()->index();
            $table->json('model_text')->nullable();
            $table->string('product_badges_bg')->nullable();
            $table->string('product_badges_text')->nullable();
            $table->string('product_order_status_text')->nullable();
            $table->longText('specification_text')->nullable();
            $table->string('warranty_period_text')->nullable();
            $table->string('color')->nullable()->index();
            $table->string('product_order_status')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->longText('specification')->nullable();
            $table->unsignedInteger('total_reviews_count')->nullable();
            $table->json('tier_price')->nullable();
            $table->boolean('has_custom_options')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('hst_code')->nullable();
            $table->text('hst_description')->nullable();
            $table->longText('buy_now_url')->nullable();

            $table->string('internal_sku')->nullable()->index();
            $table->string('external_api_id')->nullable()->index();
            $table->string('external_api_source')->nullable()->index();
            $table->decimal('cost_price', 12, 4)->nullable();
            $table->string('device_type')->nullable()->index();
            $table->string('model_compatibility')->nullable()->index();
            $table->json('compatibility')->nullable();
            $table->json('specifications')->nullable();
            $table->string('part_category')->nullable()->index();
            $table->string('image_path')->nullable();
            $table->longText('default_image')->nullable();
            $table->string('local_image_path')->nullable();
            $table->decimal('price', 12, 4)->nullable();
            $table->decimal('selling_price', 12, 4)->nullable();
            $table->string('markup_type')->default('none');
            $table->decimal('markup_value', 12, 4)->default(0);
            $table->decimal('api_price', 12, 4)->nullable();
            $table->decimal('final_price', 12, 4)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('api_quantity')->nullable();
            $table->string('availability_status')->nullable();
            $table->string('condition')->nullable();
            $table->string('stock_status')->default('Check availability');
            $table->string('supplier')->default('MobileSentrix')->index();
            $table->boolean('is_api_item')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('status')->default('active')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_price_synced_at')->nullable();
            $table->timestamp('last_stock_synced_at')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('part_category_part', function (Blueprint $table): void {
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('part_categories')->cascadeOnDelete();
            $table->primary(['part_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('part_model_part', function (Blueprint $table): void {
            $table->unsignedBigInteger('part_id');
            $table->foreignId('part_model_id')->constrained('part_models')->cascadeOnDelete();
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->primary(['part_id', 'part_model_id']);
            $table->index('part_model_id');
        });

        Schema::create('part_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('part_part_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('part_id');
            $table->foreignId('part_tag_id')->constrained('part_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->primary(['part_id', 'part_tag_id']);
            $table->index('part_tag_id');
        });

        Schema::create('part_compatibilities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->string('name')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
        });

        Schema::create('part_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->longText('image_url');
            $table->unsignedInteger('position')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
        });

        Schema::create('part_related_parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('related_part_id');
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->foreign('related_part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->primary(['part_id', 'related_part_id']);
            $table->index('related_part_id');
        });
    }

    public function down(): void
    {
        foreach ([
            'part_related_parts',
            'part_part_tag',
            'part_tags',
            'part_images',
            'part_compatibilities',
            'part_model_part',
            'part_category_part',
            'parts',
            'part_categories',
            'part_models',
            'part_brands',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['part_brands', 'part_models', 'part_categories', 'parts', 'part_category_part'] as $table) {
            $backup = $table.'_step12_backup';

            if (Schema::hasTable($backup) && ! Schema::hasTable($table)) {
                Schema::rename($backup, $table);
            }
        }
    }

    private function backupTable(string $table): void
    {
        $backup = $table.'_step12_backup';

        if (! Schema::hasTable($table) || Schema::hasTable($backup) || DB::table($table)->count() === 0) {
            return;
        }

        DB::statement("CREATE TABLE {$backup} AS SELECT * FROM {$table}");
    }
};
