<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillCategoryIds();
        $this->dropLegacyCategoryColumns();
        $this->rebuildPivot();
    }

    public function down(): void
    {
        if (! Schema::hasTable('parts') || Schema::hasColumn('parts', 'part_category_id')) {
            return;
        }

        Schema::table('parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('part_category_id')->nullable()->index();
        });

        if (Schema::hasTable('part_categories')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->foreign('part_category_id')->references('id')->on('part_categories')->nullOnDelete();
            });

            DB::table('part_category_part')
                ->whereNotNull('category_id')
                ->whereIn('category_id', DB::table('part_categories')->select('id'))
                ->orderBy('part_id')
                ->get(['part_id', 'category_id'])
                ->unique('part_id')
                ->each(function ($row): void {
                    DB::table('parts')->where('id', $row->part_id)->update([
                        'part_category_id' => $row->category_id,
                    ]);
                });
        }
    }

    private function backfillCategoryIds(): void
    {
        if (! Schema::hasTable('parts')
            || ! Schema::hasColumn('parts', 'part_category_id')
            || ! Schema::hasColumn('parts', 'category_ids')) {
            return;
        }

        DB::table('parts')
            ->whereNotNull('part_category_id')
            ->select(['id', 'part_category_id', 'category_ids'])
            ->orderBy('id')
            ->chunkById(500, function ($parts): void {
                foreach ($parts as $part) {
                    $categoryIds = $this->parseCategoryIds($part->category_ids);
                    $categoryIds[] = (string) $part->part_category_id;

                    DB::table('parts')->where('id', $part->id)->update([
                        'category_ids' => json_encode(array_values(array_unique($categoryIds))),
                    ]);
                }
            });
    }

    private function dropLegacyCategoryColumns(): void
    {
        if (! Schema::hasTable('parts')) {
            return;
        }

        foreach (['category_id', 'part_category_id'] as $column) {
            if (! Schema::hasColumn('parts', $column)) {
                continue;
            }

            $this->dropForeignKeysForColumn('parts', $column);

            Schema::table('parts', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function rebuildPivot(): void
    {
        if (! Schema::hasTable('parts')) {
            return;
        }

        $temporaryTable = 'part_category_part_step20';
        Schema::dropIfExists($temporaryTable);

        Schema::create($temporaryTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->unique(['part_id', 'category_id']);
            $table->index('category_id');
        });

        if (Schema::hasTable('part_category_part')) {
            DB::table('part_category_part')
                ->select(['part_id', 'category_id', 'created_at', 'updated_at'])
                ->orderBy('part_id')
                ->chunk(1000, function ($rows) use ($temporaryTable): void {
                    DB::table($temporaryTable)->insertOrIgnore(
                        $rows->map(fn ($row): array => [
                            'part_id' => $row->part_id,
                            'category_id' => $row->category_id,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ])->all()
                    );
                });
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('part_category_part');
        Schema::rename($temporaryTable, 'part_category_part');
        Schema::enableForeignKeyConstraints();
    }

    private function parseCategoryIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } elseif (str_contains($value, ',')) {
                $value = explode(',', trim($value, '[]'));
            } else {
                $value = [$value];
            }
        }

        if (! is_array($value)) {
            $value = filled($value) ? [$value] : [];
        }

        return collect($value)
            ->flatten()
            ->map(fn ($id): string => trim((string) $id, " \t\n\r\0\x0B\"'"))
            ->filter(fn (string $id): bool => preg_match('/^\d+$/', $id) === 1)
            ->values()
            ->all();
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
