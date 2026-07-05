<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEVICE_LOOKUP_MAP = [
        'device_sizes' => 'product_sizes',
        'device_models' => 'product_models',
        'device_manufacturers' => 'product_brands',
        'device_grades' => 'product_grades',
        'device_conditions' => 'productconditions',
        'device_colors' => 'product_colors',
        'device_carriers' => 'product_carriers',
    ];

    private const MOBILESENTRIX_LOOKUP_COLUMNS = [
        'device_grade_id',
        'device_size_id',
        'device_carrier_id',
        'device_condition_id',
        'device_color_id',
        'device_model_id',
        'device_manufacturer_id',
    ];

    public function up(): void
    {
        $this->ensureMobileSentrixDirectColumns();
        $this->refactorExistingProductLookups();

        foreach (array_unique(array_values(self::DEVICE_LOOKUP_MAP)) as $table) {
            $this->createLookupTable($table);
        }

        $this->copyLookupRows('device_manufacturers', 'product_brands');
        $this->copyLookupRows('device_brands', 'product_brands');
        $this->copyLookupRows('device_models', 'product_models');
        $this->copyLookupRows('device_sizes', 'product_sizes');
        $this->copyLookupRows('device_grades', 'product_grades');
        $this->copyLookupRows('device_conditions', 'productconditions');
        $this->copyLookupRows('device_colors', 'product_colors');
        $this->copyLookupRows('device_carriers', 'product_carriers');

        $this->addProductLookupColumns();
        $this->backfillProductConditions();
        $this->addRepairProductLookupColumns();
        $this->backfillRepairProductLookups();

        $this->dropColumnsWithForeignKeys('mobilesentrix_devices', self::MOBILESENTRIX_LOOKUP_COLUMNS);
        $this->dropColumnsWithForeignKeys('quotes', ['device_brand_id', 'device_model_id']);
        $this->dropColumnsWithForeignKeys('repair_bookings', ['device_brand_id', 'device_model_id']);

        $this->addLookupForeignKeys();

        foreach (array_keys(self::DEVICE_LOOKUP_MAP) as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('device_brands');

        if (Schema::hasTable('product_brands') && Schema::hasColumn('product_brands', 'is_active')) {
            Schema::table('product_brands', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::DEVICE_LOOKUP_MAP) as $table) {
            $this->createLookupTable($table);
        }
        $this->createLookupTable('device_brands');

        $this->copyLookupRows('product_brands', 'device_manufacturers');
        $this->copyLookupRows('product_brands', 'device_brands');
        $this->copyLookupRows('product_models', 'device_models');
        $this->copyLookupRows('product_sizes', 'device_sizes');
        $this->copyLookupRows('product_grades', 'device_grades');
        $this->copyLookupRows('productconditions', 'device_conditions');
        $this->copyLookupRows('product_colors', 'device_colors');
        $this->copyLookupRows('product_carriers', 'device_carriers');

        $this->restoreMobileSentrixLookupColumns();
        $this->restoreLegacyRepairColumns();
        $this->dropColumnsWithForeignKeys('quotes', ['product_brand_id', 'product_model_id']);
        $this->dropColumnsWithForeignKeys('repair_bookings', ['product_brand_id', 'product_model_id']);
        $this->dropColumnsWithForeignKeys('products', [
            'product_size_id',
            'product_grade_id',
            'product_condition_id',
            'product_color_id',
            'product_carrier_id',
        ]);

        foreach (['product_sizes', 'product_grades', 'productconditions', 'product_colors', 'product_carriers'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('product_brands') && ! Schema::hasColumn('product_brands', 'is_active')) {
            Schema::table('product_brands', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });

            DB::table('product_brands')->where('status', 'inactive')->update(['is_active' => false]);
        }
    }

    private function refactorExistingProductLookups(): void
    {
        if (Schema::hasTable('product_brands')) {
            Schema::table('product_brands', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_brands', 'code')) {
                    $table->string('code')->nullable()->index();
                }
                if (! Schema::hasColumn('product_brands', 'source')) {
                    $table->string('source')->nullable()->index();
                }
                if (! Schema::hasColumn('product_brands', 'status')) {
                    $table->string('status')->default('active')->index();
                }
            });

            if (Schema::hasColumn('product_brands', 'is_active')) {
                DB::table('product_brands')->where('is_active', false)->update(['status' => 'inactive']);
            }
        } else {
            $this->createLookupTable('product_brands');
        }

        if (Schema::hasTable('product_models')) {
            Schema::table('product_models', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_models', 'code')) {
                    $table->string('code')->nullable()->index();
                }
                if (! Schema::hasColumn('product_models', 'source')) {
                    $table->string('source')->nullable()->index();
                }
            });
        } else {
            $this->createLookupTable('product_models');
        }
    }

    private function ensureMobileSentrixDirectColumns(): void
    {
        if (! Schema::hasTable('mobilesentrix_devices')) {
            return;
        }

        Schema::table('mobilesentrix_devices', function (Blueprint $table): void {
            foreach ([
                'manufacturer_text',
                'device_model_text',
                'device_size_text',
                'device_color_text',
                'condition_text',
                'device_carrier_text',
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

            foreach (['price', 'final_price'] as $column) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->decimal($column, 12, 2)->nullable();
                }
            }
        });
    }

    private function createLookupTable(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
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

    private function copyLookupRows(string $source, string $target): void
    {
        if (! Schema::hasTable($source) || ! Schema::hasTable($target)) {
            return;
        }

        DB::table($source)->orderBy('id')->get()->each(function ($row) use ($target): void {
            $name = trim((string) ($row->name ?? ''));
            $slug = trim((string) ($row->slug ?? '')) ?: Str::slug($name);

            if ($name === '' || $slug === '') {
                return;
            }

            $existing = DB::table($target)->where('slug', $slug)->first();
            $status = isset($row->status)
                ? (string) $row->status
                : ((isset($row->is_active) && ! $row->is_active) ? 'inactive' : 'active');
            $values = [
                'name' => $existing->name ?? $name,
                'code' => $existing->code ?? ($row->code ?? null),
                'source' => $existing->source ?? ($row->source ?? 'legacy'),
                'status' => $existing->status ?? $status,
                'description' => $existing->description ?? ($row->description ?? null),
                'sort_order' => $existing->sort_order ?? ($row->sort_order ?? 0),
                'created_at' => $existing->created_at ?? ($row->created_at ?? now()),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table($target)->where('id', $existing->id)->update($values);
            } else {
                DB::table($target)->insert(array_merge(['slug' => $slug], $values));
            }
        });
    }

    private function addProductLookupColumns(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'product_size_id',
                'product_grade_id',
                'product_condition_id',
                'product_color_id',
                'product_carrier_id',
            ] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->unsignedBigInteger($column)->nullable()->index();
                }
            }
        });
    }

    private function backfillProductConditions(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('productconditions')) {
            return;
        }

        DB::table('products')
            ->whereNotNull('condition')
            ->where('condition', '!=', '')
            ->distinct()
            ->pluck('condition')
            ->each(function (string $condition): void {
                $conditionId = $this->ensureLookupValue('productconditions', $condition, 'products');

                DB::table('products')
                    ->where('condition', $condition)
                    ->whereNull('product_condition_id')
                    ->update(['product_condition_id' => $conditionId]);
            });
    }

    private function addRepairProductLookupColumns(): void
    {
        foreach (['quotes', 'repair_bookings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'product_brand_id')) {
                    $table->unsignedBigInteger('product_brand_id')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'product_model_id')) {
                    $table->unsignedBigInteger('product_model_id')->nullable()->index();
                }
            });
        }
    }

    private function backfillRepairProductLookups(): void
    {
        $brandNames = $this->lookupNamesById('device_brands');
        $modelNames = $this->lookupNamesById('device_models');

        if (Schema::hasTable('quotes')) {
            DB::table('quotes')->orderBy('id')->get()->each(function ($quote) use ($brandNames, $modelNames): void {
                $brandName = $brandNames[(int) ($quote->device_brand_id ?? 0)] ?? null;
                $modelName = $modelNames[(int) ($quote->device_model_id ?? 0)] ?? ($quote->device_model ?? null);

                DB::table('quotes')->where('id', $quote->id)->update([
                    'product_brand_id' => $this->ensureLookupValue('product_brands', $brandName, 'repairs'),
                    'product_model_id' => $this->ensureLookupValue('product_models', $modelName, 'repairs'),
                ]);
            });
        }

        if (Schema::hasTable('repair_bookings')) {
            DB::table('repair_bookings')->orderBy('id')->get()->each(function ($booking) use ($brandNames, $modelNames): void {
                $brandName = $brandNames[(int) ($booking->device_brand_id ?? 0)] ?? ($booking->device_brand ?? null);
                $modelName = $modelNames[(int) ($booking->device_model_id ?? 0)] ?? ($booking->device_model ?? null);

                DB::table('repair_bookings')->where('id', $booking->id)->update([
                    'product_brand_id' => $this->ensureLookupValue('product_brands', $brandName, 'repairs'),
                    'product_model_id' => $this->ensureLookupValue('product_models', $modelName, 'repairs'),
                ]);
            });
        }
    }

    private function lookupNamesById(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->pluck('name', 'id')->mapWithKeys(
            fn ($name, $id): array => [(int) $id => (string) $name],
        )->all();
    }

    private function ensureLookupValue(string $table, mixed $name, string $source): ?int
    {
        $name = trim((string) $name);
        $slug = Str::slug($name);

        if ($name === '' || $slug === '' || ! Schema::hasTable($table)) {
            return null;
        }

        $existing = DB::table($table)->where('slug', $slug)->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'source' => $source,
            'status' => 'active',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addLookupForeignKeys(): void
    {
        $map = [
            'products' => [
                'product_size_id' => 'product_sizes',
                'product_grade_id' => 'product_grades',
                'product_condition_id' => 'productconditions',
                'product_color_id' => 'product_colors',
                'product_carrier_id' => 'product_carriers',
            ],
            'quotes' => [
                'product_brand_id' => 'product_brands',
                'product_model_id' => 'product_models',
            ],
            'repair_bookings' => [
                'product_brand_id' => 'product_brands',
                'product_model_id' => 'product_models',
            ],
        ];

        foreach ($map as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column => $foreignTable) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->foreign($column)->references('id')->on($foreignTable)->nullOnDelete();
                    }
                }
            });
        }
    }

    private function dropColumnsWithForeignKeys(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                continue;
            }

            $this->dropForeignKeysForColumn($tableName, $column);

            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('CONSTRAINT_NAME');

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        } catch (Throwable) {
            // SQLite rebuilds the table when the column is dropped.
        }
    }

    private function restoreLegacyRepairColumns(): void
    {
        foreach (['quotes', 'repair_bookings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'device_brand_id')) {
                    $table->unsignedBigInteger('device_brand_id')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'device_model_id')) {
                    $table->unsignedBigInteger('device_model_id')->nullable()->index();
                }
            });

            DB::table($tableName)->orderBy('id')->get()->each(function ($record) use ($tableName): void {
                $brandName = Schema::hasTable('product_brands')
                    ? DB::table('product_brands')->where('id', $record->product_brand_id ?? null)->value('name')
                    : null;
                $modelName = Schema::hasTable('product_models')
                    ? DB::table('product_models')->where('id', $record->product_model_id ?? null)->value('name')
                    : null;

                DB::table($tableName)->where('id', $record->id)->update([
                    'device_brand_id' => $this->ensureLookupValue('device_brands', $brandName, 'rollback'),
                    'device_model_id' => $this->ensureLookupValue('device_models', $modelName, 'rollback'),
                ]);
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('device_brand_id')->references('id')->on('device_brands')->nullOnDelete();
                $table->foreign('device_model_id')->references('id')->on('device_models')->nullOnDelete();
            });
        }
    }

    private function restoreMobileSentrixLookupColumns(): void
    {
        if (! Schema::hasTable('mobilesentrix_devices')) {
            return;
        }

        $map = [
            'device_grade_id' => ['device_grades', 'device_grade_text'],
            'device_size_id' => ['device_sizes', 'device_size_text'],
            'device_carrier_id' => ['device_carriers', 'device_carrier_text'],
            'device_condition_id' => ['device_conditions', 'condition_text'],
            'device_color_id' => ['device_colors', 'device_color_text'],
            'device_model_id' => ['device_models', 'device_model_text'],
            'device_manufacturer_id' => ['device_manufacturers', 'manufacturer_text'],
        ];

        Schema::table('mobilesentrix_devices', function (Blueprint $table) use ($map): void {
            foreach (array_keys($map) as $column) {
                if (! Schema::hasColumn('mobilesentrix_devices', $column)) {
                    $table->unsignedBigInteger($column)->nullable()->index();
                }
            }
        });

        DB::table('mobilesentrix_devices')->orderBy('id')->get()->each(function ($device) use ($map): void {
            $updates = [];

            foreach ($map as $column => [$lookupTable, $textColumn]) {
                $updates[$column] = $this->ensureLookupValue($lookupTable, $device->{$textColumn} ?? null, 'rollback');
            }

            DB::table('mobilesentrix_devices')->where('id', $device->id)->update($updates);
        });

        Schema::table('mobilesentrix_devices', function (Blueprint $table) use ($map): void {
            foreach ($map as $column => [$lookupTable]) {
                $table->foreign($column)->references('id')->on($lookupTable)->nullOnDelete();
            }
        });
    }
};
