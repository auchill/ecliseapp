<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OBSOLETE_COLUMNS = [
        'part_brand_id',
        'part_model_id',
        'part_warranty_id',
    ];

    private const OBSOLETE_TABLES = [
        'part_part_tag',
        'part_part_badge',
        'part_model_part',
        'part_related_parts',
        'part_images',
        'part_compatibilities',
        'part_tags',
        'part_badges',
        'part_models',
        'part_warranties',
        'part_brands',
    ];

    public function up(): void
    {
        if (Schema::hasTable('parts')) {
            foreach (self::OBSOLETE_COLUMNS as $column) {
                if (! Schema::hasColumn('parts', $column)) {
                    continue;
                }

                $this->dropForeignKeysForColumn('parts', $column);

                Schema::table('parts', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (self::OBSOLETE_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Removed lookup records cannot be reconstructed safely from direct part fields.
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
            // SQLite rebuilds the table while dropping the column.
        }
    }
};
