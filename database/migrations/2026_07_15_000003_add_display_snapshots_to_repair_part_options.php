<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_part_options')) {
            return;
        }

        Schema::table('repair_part_options', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_part_options', 'model_snapshot')) {
                $table->string('model_snapshot')->nullable()->after('name_snapshot');
            }

            if (! Schema::hasColumn('repair_part_options', 'image_url_snapshot')) {
                $table->longText('image_url_snapshot')->nullable()->after('model_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_part_options')) {
            return;
        }

        Schema::table('repair_part_options', function (Blueprint $table): void {
            foreach (['image_url_snapshot', 'model_snapshot'] as $column) {
                if (Schema::hasColumn('repair_part_options', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
