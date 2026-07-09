<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->string('street_address')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->timestamp('customer_since');
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('customers')
                ->restrictOnDelete();
        });

        $this->backfillCustomersAndOrders();
        $this->backfillOrderSequences();

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
            $table->dropConstrainedForeignId('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('shop')->index();
        });

        DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->update(['orders.user_id' => DB::raw('customers.user_id')]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('order_number_sequences');
        Schema::dropIfExists('customers');
    }

    private function backfillCustomersAndOrders(): void
    {
        $customerPermissionId = Schema::hasTable('permissions')
            ? DB::table('permissions')->where('name', 'customer')->value('id')
            : null;

        DB::table('users')
            ->when($customerPermissionId, function ($query) use ($customerPermissionId): void {
                $query->where(function ($query) use ($customerPermissionId): void {
                    $query->where('permission_id', $customerPermissionId)
                        ->orWhereIn('id', DB::table('orders')->whereNotNull('user_id')->select('user_id'));
                });
            }, fn ($query) => $query->whereIn('id', DB::table('orders')->whereNotNull('user_id')->select('user_id')))
            ->orderBy('id')
            ->get()
            ->each(function ($user): void {
                $latestOrder = DB::table('orders')
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->first();

                $customerId = DB::table('customers')->insertGetId([
                    'user_id' => $user->id,
                    'full_name' => $latestOrder?->customer_name ?: $user->name,
                    'email' => $latestOrder?->email ?: $user->email,
                    'phone' => $latestOrder?->phone,
                    'street_address' => $latestOrder?->shipping_address_line1,
                    'address_line_2' => $latestOrder?->shipping_address_line2,
                    'city' => $latestOrder?->shipping_city,
                    'province' => $latestOrder?->shipping_province,
                    'postal_code' => $latestOrder?->shipping_postal_code,
                    'country' => $latestOrder?->shipping_country,
                    'customer_since' => $user->created_at ?: now(),
                    'status' => ($user->status ?? 'active') === 'active' ? 'active' : 'inactive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('orders')
                    ->where('user_id', $user->id)
                    ->update(['customer_id' => $customerId]);
            });
    }

    private function backfillOrderSequences(): void
    {
        $sequences = [];

        DB::table('orders')
            ->select(['order_number', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function ($order) use (&$sequences): void {
                $year = (int) date('Y', strtotime((string) $order->created_at));
                $sequence = 0;

                if (preg_match('/^ECL-ORD-(\d{4})-(\d+)$/', (string) $order->order_number, $matches)) {
                    $year = (int) $matches[1];
                    $sequence = (int) $matches[2];
                }

                $sequences[$year] = max($sequences[$year] ?? 0, $sequence);
            });

        foreach ($sequences as $year => $sequence) {
            DB::table('order_number_sequences')->insert([
                'year' => $year,
                'last_sequence' => $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
