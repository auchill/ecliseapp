<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('part_badges')) {
            Schema::table('part_badges', function (Blueprint $table): void {
                if (! Schema::hasColumn('part_badges', 'icon_url')) {
                    $table->longText('icon_url')->nullable();
                }

                if (! Schema::hasColumn('part_badges', 'photo_url')) {
                    $table->longText('photo_url')->nullable();
                }

                if (! Schema::hasColumn('part_badges', 'image_url')) {
                    $table->longText('image_url')->nullable();
                }

                if (! Schema::hasColumn('part_badges', 'raw_value')) {
                    $table->text('raw_value')->nullable();
                }
            });
        }

        if (! Schema::hasTable('part_warranties')) {
            Schema::create('part_warranties', function (Blueprint $table): void {
                $table->id();
                $table->string('external_warranty_id')->nullable()->index();
                $table->string('name')->nullable()->index();
                $table->string('duration_label')->nullable();
                $table->longText('icon_url')->nullable();
                $table->longText('photo_url')->nullable();
                $table->longText('image_url')->nullable();
                $table->text('raw_value')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('parts') && ! Schema::hasColumn('parts', 'part_warranty_id')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->foreignId('part_warranty_id')->nullable()->after('part_model_id')->constrained('part_warranties')->nullOnDelete();
            });
        }

        if (Schema::hasTable('part_images')) {
            Schema::table('part_images', function (Blueprint $table): void {
                if (! Schema::hasColumn('part_images', 'thumbnail_url')) {
                    $table->longText('thumbnail_url')->nullable()->after('image_url');
                }

                if (! Schema::hasColumn('part_images', 'large_image_url')) {
                    $table->longText('large_image_url')->nullable()->after('thumbnail_url');
                }

                if (! Schema::hasColumn('part_images', 'alt_text')) {
                    $table->string('alt_text')->nullable()->after('label');
                }
            });

            $this->deduplicatePartImages();

            if (! $this->indexExists('part_images', 'part_images_part_id_image_url_unique')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('CREATE UNIQUE INDEX part_images_part_id_image_url_unique ON part_images (part_id, image_url(191))');
                } else {
                    Schema::table('part_images', function (Blueprint $table): void {
                        $table->unique(['part_id', 'image_url'], 'part_images_part_id_image_url_unique');
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('part_images') && $this->indexExists('part_images', 'part_images_part_id_image_url_unique')) {
            Schema::table('part_images', function (Blueprint $table): void {
                $table->dropUnique('part_images_part_id_image_url_unique');
            });
        }

        if (Schema::hasTable('part_images')) {
            Schema::table('part_images', function (Blueprint $table): void {
                foreach (['alt_text', 'large_image_url', 'thumbnail_url'] as $column) {
                    if (Schema::hasColumn('part_images', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('parts') && Schema::hasColumn('parts', 'part_warranty_id')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('part_warranty_id');
            });
        }

        Schema::dropIfExists('part_warranties');

        if (Schema::hasTable('part_badges')) {
            Schema::table('part_badges', function (Blueprint $table): void {
                foreach (['raw_value', 'image_url', 'photo_url', 'icon_url'] as $column) {
                    if (Schema::hasColumn('part_badges', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function deduplicatePartImages(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('DELETE pi1 FROM part_images pi1 INNER JOIN part_images pi2 ON pi1.part_id = pi2.part_id AND pi1.image_url = pi2.image_url AND pi1.id > pi2.id');

            return;
        }

        DB::statement('DELETE FROM part_images WHERE id NOT IN (SELECT MIN(id) FROM part_images GROUP BY part_id, image_url)');
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row): bool => ($row->name ?? null) === $index);
        }

        return false;
    }
};
