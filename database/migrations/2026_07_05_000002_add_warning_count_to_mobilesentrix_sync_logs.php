<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobilesentrix_sync_logs') && ! Schema::hasColumn('mobilesentrix_sync_logs', 'warning_count')) {
            Schema::table('mobilesentrix_sync_logs', function (Blueprint $table): void {
                $table->unsignedInteger('warning_count')->default(0)->after('failed_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobilesentrix_sync_logs') && Schema::hasColumn('mobilesentrix_sync_logs', 'warning_count')) {
            Schema::table('mobilesentrix_sync_logs', function (Blueprint $table): void {
                $table->dropColumn('warning_count');
            });
        }
    }
};
