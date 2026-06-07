<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_method_id')->nullable()->after('shipping_country')->constrained()->nullOnDelete();
            $table->string('shipping_method_name')->nullable()->after('shipping_method_id');
            $table->string('shipping_delivery_days')->nullable()->after('shipping_method_name');
            $table->decimal('shipping_base_cost', 10, 2)->default(0)->after('shipping_delivery_days');
            $table->decimal('shipping_discount_amount', 10, 2)->default(0)->after('shipping_base_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn([
                'shipping_method_name',
                'shipping_delivery_days',
                'shipping_base_cost',
                'shipping_discount_amount',
            ]);
        });
    }
};
