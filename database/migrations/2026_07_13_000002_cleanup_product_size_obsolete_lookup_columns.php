<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['status', 'code', 'source', 'description'] as $column) {
            $this->dropColumnIfExists('product_sizes', $column);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_sizes')) {
            return;
        }

        Schema::table('product_sizes', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_sizes', 'status')) {
                $table->string('status')->default('active')->index();
            }
            if (! Schema::hasColumn('product_sizes', 'code')) {
                $table->string('code')->nullable()->index();
            }
            if (! Schema::hasColumn('product_sizes', 'source')) {
                $table->string('source')->nullable()->index();
            }
            if (! Schema::hasColumn('product_sizes', 'description')) {
                $table->text('description')->nullable();
            }
        });
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
};
