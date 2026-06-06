<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_status_updates', function (Blueprint $table): void {
            $table->string('delivery_carrier')->nullable()->after('is_customer_visible');
            $table->string('tracking_number')->nullable()->after('delivery_carrier');
            $table->foreignId('created_by')->nullable()->after('tracking_number')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_status_updates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['delivery_carrier', 'tracking_number']);
        });
    }
};
