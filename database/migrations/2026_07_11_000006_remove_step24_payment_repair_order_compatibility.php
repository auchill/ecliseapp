<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'repair_order_id')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'repair_id')) {
            throw new RuntimeException('Cannot remove payments.repair_order_id before payments.repair_id exists.');
        }

        DB::table('payments')
            ->whereNull('repair_id')
            ->whereNotNull('repair_order_id')
            ->update(['repair_id' => DB::raw('repair_order_id')]);

        $mismatches = DB::table('payments')
            ->whereNotNull('repair_order_id')
            ->whereNotNull('repair_id')
            ->whereColumn('repair_order_id', '!=', 'repair_id')
            ->exists();

        if ($mismatches) {
            throw new RuntimeException('Cannot remove payments.repair_order_id while it disagrees with payments.repair_id.');
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildPaymentsForSqlite();

            return;
        }

        $this->dropForeignIfExists('payments', 'payments_repair_order_id_foreign');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('repair_order_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'repair_order_id')) {
            return;
        }

        // Rollback recreates schema only. Restore the pre-cleanup backup for removed historical values.
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('repair_order_id')->nullable()->after('repair_id')->constrained('repairs')->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (! $this->foreignExists($table, $foreign)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreign): void {
            $table->dropForeign($foreign);
        });
    }

    private function rebuildPaymentsForSqlite(): void
    {
        $columns = [
            'id',
            'payable_type',
            'payable_id',
            'order_id',
            'repair_id',
            'source',
            'checkout_data',
            'gateway',
            'gateway_reference_id',
            'stripe_checkout_session_id',
            'stripe_payment_intent_id',
            'paypal_order_id',
            'paypal_capture_id',
            'amount',
            'currency',
            'status',
            'raw_response',
            'paid_at',
            'created_at',
            'updated_at',
        ];

        Schema::disableForeignKeyConstraints();
        Schema::create('payments_stage_b2', function (Blueprint $table): void {
            $table->id();
            $table->morphs('payable');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('repair_id')->nullable()->constrained('repairs')->nullOnDelete();
            $table->string('source')->default('shop')->index();
            $table->json('checkout_data')->nullable();
            $table->string('gateway');
            $table->string('gateway_reference_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('paypal_order_id')->nullable()->index();
            $table->string('paypal_capture_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('cad');
            $table->string('status')->default('pending')->index();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_reference_id']);
        });

        DB::statement('INSERT INTO payments_stage_b2 ('.implode(', ', $columns).') SELECT '.implode(', ', $columns).' FROM payments');
        Schema::drop('payments');
        Schema::rename('payments_stage_b2', 'payments');
        Schema::enableForeignKeyConstraints();
    }

    private function foreignExists(string $table, string $foreign): bool
    {
        try {
            return collect(Schema::getForeignKeys($table))
                ->contains(fn (array $details): bool => ($details['name'] ?? null) === $foreign);
        } catch (Throwable) {
            return false;
        }
    }
};
