<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_bookings', function (Blueprint $table): void {
            $table->string('fulfillment_method')->default('pickup')->after('customer_notes');
            $table->string('shipping_full_name')->nullable()->after('fulfillment_method');
            $table->string('shipping_phone')->nullable()->after('shipping_full_name');
            $table->string('shipping_email')->nullable()->after('shipping_phone');
            $table->string('shipping_address_line1')->nullable()->after('shipping_email');
            $table->string('shipping_address_line2')->nullable()->after('shipping_address_line1');
            $table->string('shipping_city')->nullable()->after('shipping_address_line2');
            $table->string('shipping_province')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code')->nullable()->after('shipping_province');
            $table->string('shipping_country')->nullable()->after('shipping_postal_code');
            $table->decimal('shipping_cost', 10, 2)->default(0)->after('shipping_country');
            $table->decimal('repair_total', 10, 2)->default(0)->after('shipping_cost');
            $table->string('delivery_carrier')->nullable()->after('repair_total');
            $table->string('delivery_tracking_number')->nullable()->after('delivery_carrier');
            $table->text('tracking_notes')->nullable()->after('delivery_tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('repair_bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'fulfillment_method',
                'shipping_full_name',
                'shipping_phone',
                'shipping_email',
                'shipping_address_line1',
                'shipping_address_line2',
                'shipping_city',
                'shipping_province',
                'shipping_postal_code',
                'shipping_country',
                'shipping_cost',
                'repair_total',
                'delivery_carrier',
                'delivery_tracking_number',
                'tracking_notes',
            ]);
        });
    }
};
