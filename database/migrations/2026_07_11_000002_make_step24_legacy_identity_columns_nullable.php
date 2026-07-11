<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table): void {
                foreach (['quote_number', 'customer_name', 'email', 'phone_number'] as $column) {
                    if (Schema::hasColumn('quotes', $column)) {
                        $table->string($column)->nullable()->change();
                    }
                }

                if (Schema::hasColumn('quotes', 'converted_to_booking')) {
                    $table->boolean('converted_to_booking')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('repairs')) {
            Schema::table('repairs', function (Blueprint $table): void {
                foreach (['tracking_number', 'customer_name', 'email', 'phone'] as $column) {
                    if (Schema::hasColumn('repairs', $column)) {
                        $table->string($column)->nullable()->change();
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                foreach (['customer_name', 'email', 'phone'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->string($column)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally not restoring NOT NULL constraints on legacy columns.
        // New Step 24 code no longer writes these retained compatibility fields.
    }
};
