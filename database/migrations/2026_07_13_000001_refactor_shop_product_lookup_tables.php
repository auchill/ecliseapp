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
        $this->renameLookupTables();
        $this->ensureLookupColumns();
        $this->ensureProductModelBrand();
        $this->ensureProductColumns();
        $this->ensureProductSizePivot();
        $this->ensureProductImages();

        $this->backfillProductCategories();
        $this->backfillProductModelBrands();
        $this->backfillProductNetworks();
        $this->backfillProductSizes();
        $this->backfillProductImages();

        $this->addProductForeignKeys();
        $this->dropObsoleteProductColumns();

        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('product');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable()->index();
                }
                if (! Schema::hasColumn('products', 'product_size_id')) {
                    $table->unsignedBigInteger('product_size_id')->nullable()->index();
                }
                if (! Schema::hasColumn('products', 'product_carrier_id')) {
                    $table->unsignedBigInteger('product_carrier_id')->nullable()->index();
                }
            });
        }

        $this->dropColumnIfExists('products', 'product_network_id');
        Schema::dropIfExists('product_product_size');
        Schema::dropIfExists('product_images');

        if (Schema::hasTable('product_networks') && ! Schema::hasTable('product_carriers')) {
            Schema::rename('product_networks', 'product_carriers');
        }

        if (Schema::hasTable('product_conditions') && ! Schema::hasTable('productconditions')) {
            Schema::rename('product_conditions', 'productconditions');
        }
    }

    private function renameLookupTables(): void
    {
        if (Schema::hasTable('productconditions') && ! Schema::hasTable('product_conditions')) {
            Schema::rename('productconditions', 'product_conditions');
        }

        if (Schema::hasTable('product_carriers') && ! Schema::hasTable('product_networks')) {
            Schema::rename('product_carriers', 'product_networks');
        }

        $this->createStatusLookupTable('product_conditions');
        $this->createStatusLookupTable('product_networks');
    }

    private function ensureLookupColumns(): void
    {
        foreach ([
            'product_brands',
            'product_models',
            'product_grades',
            'product_conditions',
            'product_colors',
            'product_networks',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                $this->createStatusLookupTable($tableName);
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'status')) {
                    $table->string('status')->default('active')->index();
                }
                if (! Schema::hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0);
                }
            });
        }

        if (! Schema::hasTable('product_sizes')) {
            Schema::create('product_sizes', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->index();
                $table->string('slug')->unique();
                $table->string('type')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('product_sizes', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_sizes', 'type')) {
                    $table->string('type')->nullable()->index();
                }
                if (! Schema::hasColumn('product_sizes', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
            });

            if (Schema::hasColumn('product_sizes', 'status')) {
                DB::table('product_sizes')->where('status', 'inactive')->update(['is_active' => false]);
                $this->dropColumnIfExists('product_sizes', 'status');
            }

            foreach (['code', 'source', 'description'] as $obsoleteSizeColumn) {
                $this->dropColumnIfExists('product_sizes', $obsoleteSizeColumn);
            }
        }
    }

    private function ensureProductModelBrand(): void
    {
        if (! Schema::hasTable('product_models')) {
            return;
        }

        Schema::table('product_models', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_models', 'product_brand_id')) {
                $table->unsignedBigInteger('product_brand_id')->nullable()->index();
            }
        });
    }

    private function ensureProductColumns(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'product_network_id')) {
                $table->unsignedBigInteger('product_network_id')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'serial_number')) {
                $table->string('serial_number')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'regular_price')) {
                $table->decimal('regular_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(0);
            }
            if (! Schema::hasColumn('products', 'source')) {
                $table->string('source')->default('manual')->index();
            }
            if (! Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->index();
            }
            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->index();
            }
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('products', 'price') && Schema::hasColumn('products', 'regular_price')) {
            DB::table('products')
                ->whereNull('regular_price')
                ->whereNotNull('price')
                ->update(['regular_price' => DB::raw('price')]);
        }

        if (Schema::hasColumn('products', 'status') && Schema::hasColumn('products', 'is_active')) {
            DB::table('products')
                ->whereIn('status', ['Inactive', 'Out of Stock'])
                ->update(['is_active' => false]);
        }
    }

    private function ensureProductSizePivot(): void
    {
        if (! Schema::hasTable('product_product_size')) {
            Schema::create('product_product_size', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['product_id', 'product_size_id']);
            });
        }
    }

    private function ensureProductImages(): void
    {
        if (Schema::hasTable('product_images')) {
            return;
        }

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });
    }

    private function backfillProductCategories(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories') || ! Schema::hasTable('product_categories')) {
            return;
        }

        if (! Schema::hasColumn('products', 'category_id') || ! Schema::hasColumn('products', 'product_category_id')) {
            return;
        }

        DB::table('products')
            ->whereNull('product_category_id')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get(['id', 'category_id'])
            ->each(function ($product): void {
                $legacyCategory = DB::table('categories')->where('id', $product->category_id)->first(['name', 'slug']);

                if (! $legacyCategory) {
                    return;
                }

                $productCategory = DB::table('product_categories')->where('slug', $legacyCategory->slug)->first(['id']);

                if (! $productCategory) {
                    $productCategoryId = DB::table('product_categories')->insertGetId([
                        'name' => $legacyCategory->name,
                        'slug' => $legacyCategory->slug ?: Str::slug($legacyCategory->name),
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $productCategoryId = $productCategory->id;
                }

                DB::table('products')->where('id', $product->id)->update([
                    'product_category_id' => $productCategoryId,
                ]);
            });
    }

    private function backfillProductModelBrands(): void
    {
        if (
            ! Schema::hasTable('products')
            || ! Schema::hasTable('product_models')
            || ! Schema::hasColumn('product_models', 'product_brand_id')
            || ! Schema::hasColumn('products', 'product_model_id')
            || ! Schema::hasColumn('products', 'product_brand_id')
        ) {
            return;
        }

        DB::table('products')
            ->whereNotNull('product_model_id')
            ->whereNotNull('product_brand_id')
            ->select('product_model_id', 'product_brand_id')
            ->distinct()
            ->orderBy('product_model_id')
            ->get()
            ->each(function ($row): void {
                DB::table('product_models')
                    ->where('id', $row->product_model_id)
                    ->whereNull('product_brand_id')
                    ->update(['product_brand_id' => $row->product_brand_id]);
            });
    }

    private function backfillProductNetworks(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'product_network_id')) {
            return;
        }

        if (Schema::hasColumn('products', 'product_carrier_id')) {
            DB::table('products')
                ->whereNull('product_network_id')
                ->whereNotNull('product_carrier_id')
                ->update(['product_network_id' => DB::raw('product_carrier_id')]);
        }
    }

    private function backfillProductSizes(): void
    {
        if (
            ! Schema::hasTable('products')
            || ! Schema::hasTable('product_product_size')
            || ! Schema::hasColumn('products', 'product_size_id')
        ) {
            return;
        }

        DB::table('products')
            ->whereNotNull('product_size_id')
            ->orderBy('id')
            ->get(['id', 'product_size_id', 'created_at', 'updated_at'])
            ->each(function ($product): void {
                DB::table('product_product_size')->updateOrInsert(
                    [
                        'product_id' => $product->id,
                        'product_size_id' => $product->product_size_id,
                    ],
                    [
                        'created_at' => $product->created_at ?? now(),
                        'updated_at' => $product->updated_at ?? now(),
                    ],
                );
            });
    }

    private function backfillProductImages(): void
    {
        if (
            ! Schema::hasTable('products')
            || ! Schema::hasTable('product_images')
            || ! Schema::hasColumn('products', 'image_path')
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
                    'is_primary' => ! DB::table('product_images')->where('product_id', $product->id)->where('is_primary', true)->exists(),
                    'created_at' => $product->created_at ?? now(),
                    'updated_at' => $product->updated_at ?? now(),
                ]);
            });
    }

    private function addProductForeignKeys(): void
    {
        $this->addForeignIfMissing('product_models', 'product_brand_id', 'product_brands');
        $this->addForeignIfMissing('products', 'product_network_id', 'product_networks');
    }

    private function dropObsoleteProductColumns(): void
    {
        foreach (['category_id', 'product_size_id', 'product_carrier_id'] as $column) {
            $this->dropColumnIfExists('products', $column);
        }
    }

    private function createStatusLookupTable(string $tableName): void
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

    private function addForeignIfMissing(string $tableName, string $column, string $foreignTable): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column) || ! Schema::hasTable($foreignTable)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $tableName)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();

            if ($exists) {
                return;
            }
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($column, $foreignTable): void {
                $table->foreign($column)->references('id')->on($foreignTable)->nullOnDelete();
            });
        } catch (Throwable) {
            // Existing SQLite test databases may already have the equivalent constraint.
        }
    }

    private function dropColumnIfExists(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $this->dropIndexesForColumn($tableName, $column);
        $this->dropForeignKeysForColumn($tableName, $column);

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    private function dropIndexesForColumn(string $tableName, string $column): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropIndex([$column]);
            });
        } catch (Throwable) {
            // The column may not have Laravel's conventional single-column index.
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

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

    private function dropForeignKeysForColumn(string $tableName, string $column): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $tableName)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('CONSTRAINT_NAME');

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraint}`");
            }

            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        } catch (Throwable) {
            // SQLite drops/rebuilds columns without explicit foreign key removal.
        }
    }
};
