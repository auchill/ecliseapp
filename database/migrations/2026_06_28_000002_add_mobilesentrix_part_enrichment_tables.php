<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parts')) {
            if (! Schema::hasColumn('parts', 'tags_raw_payload')) {
                Schema::table('parts', function (Blueprint $table): void {
                    $table->json('tags_raw_payload')->nullable();
                });
            }

            if (! Schema::hasColumn('parts', 'last_enriched_at')) {
                Schema::table('parts', function (Blueprint $table): void {
                    $table->timestamp('last_enriched_at')->nullable()->index();
                });
            }
        }

        if (! Schema::hasTable('part_badges')) {
            Schema::create('part_badges', function (Blueprint $table): void {
                $table->id();
                $table->string('external_badge_id')->nullable()->index();
                $table->string('name')->index();
                $table->string('slug')->unique();
                $table->string('color')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('part_part_badge')) {
            Schema::create('part_part_badge', function (Blueprint $table): void {
                $table->unsignedBigInteger('part_id');
                $table->foreignId('part_badge_id')->constrained('part_badges')->cascadeOnDelete();
                $table->timestamps();
                $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
                $table->primary(['part_id', 'part_badge_id']);
                $table->index('part_badge_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('part_part_badge');
        Schema::dropIfExists('part_badges');

        if (Schema::hasTable('parts')) {
            if (Schema::hasColumn('parts', 'last_enriched_at')) {
                Schema::table('parts', function (Blueprint $table): void {
                    $table->dropColumn('last_enriched_at');
                });
            }

            if (Schema::hasColumn('parts', 'tags_raw_payload')) {
                Schema::table('parts', function (Blueprint $table): void {
                    $table->dropColumn('tags_raw_payload');
                });
            }
        }
    }
};
