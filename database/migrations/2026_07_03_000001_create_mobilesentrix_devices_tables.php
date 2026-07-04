<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTable('device_manufacturers');
        $this->createLookupTable('device_colors');
        $this->createLookupTable('device_conditions');
        $this->createLookupTable('device_carriers');
        $this->createLookupTable('device_sizes');
        $this->createLookupTable('device_grades');
        $this->addLookupColumns('device_types');
        $this->addLookupColumns('device_models');

        if (Schema::hasTable('devices') && ! Schema::hasTable('mobilesentrix_devices')) {
            Schema::rename('devices', 'mobilesentrix_devices');
        }

        if (! Schema::hasTable('mobilesentrix_devices')) {
            Schema::create('mobilesentrix_devices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('device_manufacturer_id')->nullable()->constrained('device_manufacturers')->nullOnDelete();
                $table->foreignId('device_model_id')->nullable()->constrained('device_models')->nullOnDelete();
                $table->foreignId('device_color_id')->nullable()->constrained('device_colors')->nullOnDelete();
                $table->foreignId('device_condition_id')->nullable()->constrained('device_conditions')->nullOnDelete();
                $table->foreignId('device_carrier_id')->nullable()->constrained('device_carriers')->nullOnDelete();
                $table->foreignId('device_size_id')->nullable()->constrained('device_sizes')->nullOnDelete();
                $table->foreignId('device_grade_id')->nullable()->constrained('device_grades')->nullOnDelete();
                $table->unsignedBigInteger('entity_id')->nullable()->unique();
                $table->string('sku')->nullable()->index();
                $table->string('name', 512)->nullable();
                $table->longText('url')->nullable();
                $table->string('url_key')->nullable();
                $table->string('manufacturer_text')->nullable()->index();
                $table->string('device_model_text')->nullable()->index();
                $table->string('device_color_text')->nullable()->index();
                $table->string('condition_text')->nullable()->index();
                $table->string('device_carrier_text')->nullable()->index();
                $table->string('device_size_text')->nullable()->index();
                $table->string('device_grade_text')->nullable()->index();
                $table->bigInteger('available_qty')->nullable()->index();
                $table->bigInteger('qty')->nullable()->index();
                $table->decimal('price', 12, 2)->nullable();
                $table->decimal('regular_price', 12, 2)->nullable();
                $table->decimal('final_price', 12, 2)->nullable();
                $table->decimal('cost', 12, 2)->nullable();
                $table->string('status')->nullable()->index();
                $table->string('product_type')->nullable()->index();
                $table->longText('image_url')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamp('synced_at')->nullable()->index();
                $table->timestamps();
            });

            return;
        }

        $this->addDeviceColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('mobilesentrix_devices');
        Schema::dropIfExists('device_grades');
        Schema::dropIfExists('device_sizes');
        Schema::dropIfExists('device_carriers');
        Schema::dropIfExists('device_conditions');
        Schema::dropIfExists('device_colors');
        Schema::dropIfExists('device_manufacturers');
    }

    private function createLookupTable(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            $this->addLookupColumns($tableName);

            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('code')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function addLookupColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'code')) {
                $table->string('code')->nullable()->index()->after('slug');
            }

            if (! Schema::hasColumn($tableName, 'source')) {
                $table->string('source')->nullable()->index()->after('code');
            }
        });
    }

    private function addDeviceColumns(): void
    {
        Schema::table('mobilesentrix_devices', function (Blueprint $table): void {
            foreach ([
                'device_manufacturer_id' => 'device_manufacturers',
                'device_model_id' => 'device_models',
                'device_color_id' => 'device_colors',
                'device_condition_id' => 'device_conditions',
                'device_carrier_id' => 'device_carriers',
                'device_size_id' => 'device_sizes',
                'device_grade_id' => 'device_grades',
            ] as $column => $foreignTable) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->foreignId($column)->nullable()->constrained($foreignTable)->nullOnDelete();
                }
            }

            if (! Schema::hasColumn('mobilesentrix_devices', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'sku')) {
                $table->string('sku')->nullable()->index();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'name')) {
                $table->string('name', 512)->nullable();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'url')) {
                $table->longText('url')->nullable();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'url_key')) {
                $table->string('url_key')->nullable();
            }
            foreach ([
                'manufacturer_text',
                'device_model_text',
                'device_color_text',
                'condition_text',
                'device_carrier_text',
                'device_size_text',
                'device_grade_text',
                'status',
                'product_type',
            ] as $column) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->string($column)->nullable()->index();
                }
            }
            foreach (['available_qty', 'qty'] as $column) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->bigInteger($column)->nullable()->index();
                }
            }
            foreach (['price', 'regular_price', 'final_price', 'cost'] as $column) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->decimal($column, 12, 2)->nullable();
                }
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'image_url')) {
                $table->longText('image_url')->nullable();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'raw_payload')) {
                $table->json('raw_payload')->nullable();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->index();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (! Schema::hasColumn('mobilesentrix_devices', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }
};
