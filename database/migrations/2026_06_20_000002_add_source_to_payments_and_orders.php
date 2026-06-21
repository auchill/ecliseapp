<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'source')) {
                $table->string('source')->default('shop')->after('repair_order_id')->index();
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source')->default('shop')->after('order_number')->index();
            }
        });

        DB::table('payments')
            ->where(function ($query): void {
                $query->whereNotNull('repair_order_id')
                    ->orWhere('payable_type', 'App\\Models\\RepairBooking');
            })
            ->update(['source' => 'repair']);

        DB::table('payments')
            ->where(function ($query): void {
                $query->whereNotNull('order_id')
                    ->orWhere('payable_type', 'App\\Models\\Order')
                    ->orWhereNull('source')
                    ->orWhere('source', '');
            })
            ->where('source', '!=', 'repair')
            ->update(['source' => 'shop']);

        DB::table('orders')
            ->where(function ($query): void {
                $query->whereNull('source')->orWhere('source', '');
            })
            ->update(['source' => 'shop']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
