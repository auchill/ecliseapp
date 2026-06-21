<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        $now = now();

        foreach (['admin', 'customer'] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['status' => 'active', 'updated_at' => $now, 'created_at' => $now],
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'permission_id')) {
                $table->foreignId('permission_id')->nullable()->after('role')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('permission_id')->index();
            }
        });

        $adminPermissionId = DB::table('permissions')->where('name', 'admin')->value('id');
        $customerPermissionId = DB::table('permissions')->where('name', 'customer')->value('id');

        if ($adminPermissionId && $customerPermissionId) {
            DB::table('users')
                ->whereNull('permission_id')
                ->where('role', 'admin')
                ->update(['permission_id' => $adminPermissionId, 'status' => 'active']);

            DB::table('users')
                ->whereNull('permission_id')
                ->update(['permission_id' => $customerPermissionId, 'status' => 'active']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'permission_id')) {
                $table->dropConstrainedForeignId('permission_id');
            }

            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('permissions');
    }
};
