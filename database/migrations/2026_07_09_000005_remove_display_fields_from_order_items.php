<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(['source', 'source_sku'], 'order_items_source_sku_lookup_index');
            $table->dropColumn(['item_name', 'image_url']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('item_name')->nullable()->after('source');
            $table->longText('image_url')->nullable()->after('item_name');
            $table->dropIndex('order_items_source_sku_lookup_index');
        });
    }
};
