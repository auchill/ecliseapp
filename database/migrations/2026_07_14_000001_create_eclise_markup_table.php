<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eclise_markup')) {
            return;
        }

        Schema::create('eclise_markup', function (Blueprint $table): void {
            $table->id();
            $table->string('item_type', 50);
            $table->string('scope_type', 50);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('markup_type', 30);
            $table->decimal('markup_value', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index('item_type');
            $table->index('scope_type');
            $table->index('category_id');
            $table->index('is_active');
            $table->index('priority');
            $table->index(['item_type', 'scope_type', 'category_id', 'is_active'], 'eclise_markup_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eclise_markup');
    }
};
