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
        foreach ([
            'product_models',
            'part_models',
            'device_types',
            'device_brands',
            'device_models',
            'issue_categories',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->string('slug')->unique();
                    $table->string('status')->default('active');
                    $table->text('description')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();
                });
            }
        }

        if (! Schema::hasColumn('products', 'product_model_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('product_model_id')
                    ->nullable()
                    ->after('product_category_id')
                    ->constrained('product_models')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('parts', 'part_model_id')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->foreignId('part_model_id')
                    ->nullable()
                    ->after('part_category_id')
                    ->constrained('part_models')
                    ->nullOnDelete();
            });
        }

        $this->backfillProductModels();
        $this->backfillPartModels();
        $this->backfillRepairReferences();
    }

    public function down(): void
    {
        if (Schema::hasColumn('parts', 'part_model_id')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('part_model_id');
            });
        }

        if (Schema::hasColumn('products', 'product_model_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_model_id');
            });
        }

        Schema::dropIfExists('issue_categories');
        Schema::dropIfExists('device_models');
        Schema::dropIfExists('device_brands');
        Schema::dropIfExists('device_types');
        Schema::dropIfExists('part_models');
        Schema::dropIfExists('product_models');
    }

    private function backfillProductModels(): void
    {
        $now = now();

        DB::table('products')
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->each(function (string $model) use ($now): void {
                DB::table('product_models')->updateOrInsert(
                    ['slug' => Str::slug($model)],
                    [
                        'name' => $model,
                        'status' => 'active',
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

        DB::table('products')
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->get(['id', 'model'])
            ->each(function ($product): void {
                $model = DB::table('product_models')->where('slug', Str::slug($product->model))->first(['id']);

                if ($model) {
                    DB::table('products')->where('id', $product->id)->update(['product_model_id' => $model->id]);
                }
            });
    }

    private function backfillPartModels(): void
    {
        $now = now();

        DB::table('parts')
            ->whereNotNull('model_compatibility')
            ->where('model_compatibility', '!=', '')
            ->distinct()
            ->orderBy('model_compatibility')
            ->pluck('model_compatibility')
            ->each(function (string $model) use ($now): void {
                DB::table('part_models')->updateOrInsert(
                    ['slug' => Str::slug($model)],
                    [
                        'name' => $model,
                        'status' => 'active',
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

        DB::table('parts')
            ->whereNotNull('model_compatibility')
            ->where('model_compatibility', '!=', '')
            ->get(['id', 'model_compatibility'])
            ->each(function ($part): void {
                $model = DB::table('part_models')->where('slug', Str::slug($part->model_compatibility))->first(['id']);

                if ($model) {
                    DB::table('parts')->where('id', $part->id)->update(['part_model_id' => $model->id]);
                }
            });
    }

    private function backfillRepairReferences(): void
    {
        $now = now();

        $map = [
            'device_type' => 'device_types',
            'device_brand' => 'device_brands',
            'device_model' => 'device_models',
            'issue_category' => 'issue_categories',
        ];

        foreach ($map as $column => $table) {
            DB::table('repair_bookings')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->each(function (string $value) use ($table, $now): void {
                    DB::table($table)->updateOrInsert(
                        ['slug' => Str::slug($value)],
                        [
                            'name' => $value,
                            'status' => 'active',
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                });
        }
    }
};
