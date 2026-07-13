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
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->backfillRegularPrice();
        $this->backfillLookupIds();
        $this->backfillProductImages();
        $this->mapLegacyStatus();
        $this->assertNoLegacyLookupConflicts();
        $this->assertNoUnsafeLegacyDataWillBeDropped();

        foreach (['price', 'brand', 'model', 'condition', 'image_path', 'status'] as $column) {
            $this->dropColumnIfExists('products', $column);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable();
            }
            if (! Schema::hasColumn('products', 'model')) {
                $table->string('model')->nullable();
            }
            if (! Schema::hasColumn('products', 'condition')) {
                $table->string('condition')->nullable();
            }
            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable();
            }
            if (! Schema::hasColumn('products', 'status')) {
                $table->string('status')->nullable();
            }
        });

        DB::table('products')->whereNull('price')->update(['price' => DB::raw('regular_price')]);
        DB::table('products')->whereNull('status')->update(['status' => DB::raw("CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END")]);
    }

    private function backfillRegularPrice(): void
    {
        if (! Schema::hasColumn('products', 'price') || ! Schema::hasColumn('products', 'regular_price')) {
            return;
        }

        $conflicts = DB::table('products')
            ->whereNotNull('price')
            ->whereNotNull('regular_price')
            ->whereRaw('regular_price <> price')
            ->count();

        if ($conflicts > 0) {
            throw new RuntimeException("Cannot drop products.price: {$conflicts} records conflict with regular_price.");
        }

        DB::table('products')
            ->whereNull('regular_price')
            ->whereNotNull('price')
            ->update(['regular_price' => DB::raw('price')]);
    }

    private function backfillLookupIds(): void
    {
        if (Schema::hasColumn('products', 'brand') && Schema::hasTable('product_brands')) {
            DB::table('products')
                ->whereNull('product_brand_id')
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->orderBy('id')
                ->get(['id', 'brand'])
                ->each(function ($product): void {
                    $brandId = DB::table('product_brands')
                        ->where('slug', Str::slug($product->brand))
                        ->value('id');

                    if ($brandId) {
                        DB::table('products')->where('id', $product->id)->update(['product_brand_id' => $brandId]);
                    }
                });
        }

        if (Schema::hasColumn('products', 'model') && Schema::hasTable('product_models')) {
            DB::table('products')
                ->whereNull('product_model_id')
                ->whereNotNull('model')
                ->where('model', '!=', '')
                ->orderBy('id')
                ->get(['id', 'model', 'product_brand_id'])
                ->each(function ($product): void {
                    $query = DB::table('product_models')->where('slug', Str::slug($product->model));

                    if ($product->product_brand_id && Schema::hasColumn('product_models', 'product_brand_id')) {
                        $query->where(function ($query) use ($product): void {
                            $query->whereNull('product_brand_id')
                                ->orWhere('product_brand_id', $product->product_brand_id);
                        });
                    }

                    $modelId = $query->value('id');

                    if ($modelId) {
                        DB::table('products')->where('id', $product->id)->update(['product_model_id' => $modelId]);
                    }
                });
        }

        if (Schema::hasColumn('products', 'condition') && Schema::hasTable('product_conditions')) {
            DB::table('products')
                ->whereNull('product_condition_id')
                ->whereNotNull('condition')
                ->where('condition', '!=', '')
                ->orderBy('id')
                ->get(['id', 'condition'])
                ->each(function ($product): void {
                    $conditionId = DB::table('product_conditions')
                        ->where('slug', Str::slug($product->condition))
                        ->value('id');

                    if ($conditionId) {
                        DB::table('products')->where('id', $product->id)->update(['product_condition_id' => $conditionId]);
                    }
                });
        }
    }

    private function backfillProductImages(): void
    {
        if (
            ! Schema::hasColumn('products', 'image_path')
            || ! Schema::hasTable('product_images')
        ) {
            return;
        }

        DB::table('products')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'image_path', 'created_at', 'updated_at'])
            ->each(function ($product): void {
                $exists = DB::table('product_images')
                    ->where('product_id', $product->id)
                    ->where('image_path', $product->image_path)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('product_images')->insert([
                    'product_id' => $product->id,
                    'image_path' => $product->image_path,
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => ! DB::table('product_images')
                        ->where('product_id', $product->id)
                        ->where('is_primary', true)
                        ->exists(),
                    'created_at' => $product->created_at ?? now(),
                    'updated_at' => $product->updated_at ?? now(),
                ]);
            });
    }

    private function mapLegacyStatus(): void
    {
        if (! Schema::hasColumn('products', 'status') || ! Schema::hasColumn('products', 'is_active')) {
            return;
        }

        $activeValues = ['active', 'enabled', '1'];
        $inactiveValues = ['inactive', 'disabled', '0', 'out of stock'];
        $allowedValues = array_merge($activeValues, $inactiveValues);

        $unknown = DB::table('products')
            ->whereNotNull('status')
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->filter(fn ($status): bool => ! in_array(Str::lower(trim((string) $status)), $allowedValues, true))
            ->values();

        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('Cannot drop products.status: unknown values found: '.$unknown->implode(', '));
        }

        foreach ($activeValues as $status) {
            DB::table('products')
                ->whereRaw('LOWER(TRIM(status)) = ?', [$status])
                ->update(['is_active' => true]);
        }

        foreach ($inactiveValues as $status) {
            DB::table('products')
                ->whereRaw('LOWER(TRIM(status)) = ?', [$status])
                ->update(['is_active' => false]);
        }
    }

    private function assertNoUnsafeLegacyDataWillBeDropped(): void
    {
        $checks = [
            'brand' => 'product_brand_id',
            'model' => 'product_model_id',
            'condition' => 'product_condition_id',
        ];

        foreach ($checks as $legacyColumn => $foreignKey) {
            if (! Schema::hasColumn('products', $legacyColumn) || ! Schema::hasColumn('products', $foreignKey)) {
                continue;
            }

            $unmatched = DB::table('products')
                ->whereNotNull($legacyColumn)
                ->where($legacyColumn, '!=', '')
                ->whereNull($foreignKey)
                ->count();

            if ($unmatched > 0) {
                throw new RuntimeException("Cannot drop products.{$legacyColumn}: {$unmatched} populated values have no {$foreignKey} match.");
            }
        }
    }

    private function assertNoLegacyLookupConflicts(): void
    {
        $checks = [
            'brand' => ['table' => 'product_brands', 'foreign_key' => 'product_brand_id'],
            'model' => ['table' => 'product_models', 'foreign_key' => 'product_model_id'],
            'condition' => ['table' => 'product_conditions', 'foreign_key' => 'product_condition_id'],
        ];

        foreach ($checks as $legacyColumn => $config) {
            $lookupTable = $config['table'];
            $foreignKey = $config['foreign_key'];

            if (
                ! Schema::hasColumn('products', $legacyColumn)
                || ! Schema::hasColumn('products', $foreignKey)
                || ! Schema::hasTable($lookupTable)
            ) {
                continue;
            }

            $conflicts = DB::table('products as products')
                ->join("{$lookupTable} as lookup", "products.{$foreignKey}", '=', 'lookup.id')
                ->whereNotNull("products.{$legacyColumn}")
                ->where("products.{$legacyColumn}", '!=', '')
                ->select([
                    "products.{$legacyColumn} as legacy_value",
                    'lookup.slug as lookup_slug',
                ])
                ->get()
                ->filter(fn ($row): bool => Str::slug((string) $row->legacy_value) !== (string) $row->lookup_slug)
                ->count();

            if ($conflicts > 0) {
                throw new RuntimeException("Cannot drop products.{$legacyColumn}: {$conflicts} populated values conflict with {$foreignKey}.");
            }
        }
    }

    private function dropColumnIfExists(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $this->dropIndexesForColumn($tableName, $column);

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    private function dropIndexesForColumn(string $tableName, string $column): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $indexes = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $tableName)
                ->where('COLUMN_NAME', $column)
                ->where('SEQ_IN_INDEX', 1)
                ->pluck('INDEX_NAME')
                ->reject(fn ($index): bool => $index === 'PRIMARY')
                ->unique();

            foreach ($indexes as $index) {
                DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$index}`");
            }

            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$tableName}')") as $index) {
                $indexName = $index->name ?? null;

                if (! $indexName || str_starts_with((string) $indexName, 'sqlite_autoindex')) {
                    continue;
                }

                $columns = collect(DB::select("PRAGMA index_info('{$indexName}')"))
                    ->pluck('name')
                    ->all();

                if ($columns === [$column]) {
                    DB::statement('DROP INDEX "'.$indexName.'"');
                }
            }
        }
    }
};
