<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobilesentrix_api_settings')) {
            return;
        }

        Schema::table('mobilesentrix_api_settings', function (Blueprint $table): void {
            foreach (['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'] as $column) {
                if (Schema::hasColumn('mobilesentrix_api_settings', $column)) {
                    $table->text($column)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: encrypted OAuth values can exceed string column limits.
    }
};
