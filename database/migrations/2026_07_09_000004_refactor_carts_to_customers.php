<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('customers')
                ->restrictOnDelete();
        });

        $this->backfillCustomersAndCarts();

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn('user_id');
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('carts')
            ->join('customers', 'customers.id', '=', 'carts.customer_id')
            ->update(['carts.user_id' => DB::raw('customers.user_id')]);

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropColumn('customer_id');
            $table->foreignId('user_id')->nullable(false)->change();
            $table->index(['user_id', 'status']);
        });
    }

    private function backfillCustomersAndCarts(): void
    {
        DB::table('carts')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function (int $userId): void {
                $customerId = DB::table('customers')->where('user_id', $userId)->value('id');

                if (! $customerId) {
                    $user = DB::table('users')->find($userId);

                    if (! $user) {
                        return;
                    }

                    $customerId = DB::table('customers')->insertGetId([
                        'user_id' => $user->id,
                        'full_name' => $user->name,
                        'email' => $user->email,
                        'phone' => null,
                        'street_address' => null,
                        'address_line_2' => null,
                        'city' => null,
                        'province' => null,
                        'postal_code' => null,
                        'country' => null,
                        'customer_since' => $user->created_at ?: now(),
                        'status' => ($user->status ?? 'active') === 'active' ? 'active' : 'inactive',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('carts')
                    ->where('user_id', $userId)
                    ->update(['customer_id' => $customerId]);
            });

        if (DB::table('carts')->whereNull('customer_id')->exists()) {
            throw new RuntimeException('Unable to resolve a customer for every existing cart.');
        }
    }
};
