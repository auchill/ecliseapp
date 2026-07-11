<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyColumns = [
        'cart_id',
        'customer_name',
        'email',
        'phone',
        'address',
        'shipping_full_name',
        'shipping_phone',
        'shipping_email',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $this->assertNoMissingCustomerIds();

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildOrdersForSqlite();

            return;
        }

        $this->dropForeignIfExists('orders', 'orders_cart_id_foreign');

        $columns = $this->existingColumns('orders', $this->legacyColumns);

        if ($columns !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        // Rollback recreates schema only. Restore the pre-cleanup backup for removed historical values.
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'cart_id')) {
                $table->foreignId('cart_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('order_number');
            }

            if (! Schema::hasColumn('orders', 'email')) {
                $table->string('email')->nullable()->after('customer_name');
            }

            if (! Schema::hasColumn('orders', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            if (! Schema::hasColumn('orders', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('orders', 'shipping_full_name')) {
                $table->string('shipping_full_name')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'shipping_phone')) {
                $table->string('shipping_phone')->nullable()->after('shipping_full_name');
            }

            if (! Schema::hasColumn('orders', 'shipping_email')) {
                $table->string('shipping_email')->nullable()->after('shipping_phone');
            }

            if (! Schema::hasColumn('orders', 'shipping_address_line1')) {
                $table->string('shipping_address_line1')->nullable()->after('shipping_email');
            }

            if (! Schema::hasColumn('orders', 'shipping_address_line2')) {
                $table->string('shipping_address_line2')->nullable()->after('shipping_address_line1');
            }

            if (! Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_address_line2');
            }

            if (! Schema::hasColumn('orders', 'shipping_province')) {
                $table->string('shipping_province')->nullable()->after('shipping_city');
            }

            if (! Schema::hasColumn('orders', 'shipping_postal_code')) {
                $table->string('shipping_postal_code')->nullable()->after('shipping_province');
            }

            if (! Schema::hasColumn('orders', 'shipping_country')) {
                $table->string('shipping_country')->nullable()->after('shipping_postal_code');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->change();
            }
        });
    }

    private function assertNoMissingCustomerIds(): void
    {
        if (! Schema::hasColumn('orders', 'customer_id')) {
            throw new RuntimeException('Cannot remove legacy order identity columns before orders.customer_id exists.');
        }

        if (DB::table('orders')->whereNull('customer_id')->exists()) {
            throw new RuntimeException('Cannot remove legacy order identity columns while orders without customer_id exist.');
        }
    }

    private function rebuildOrdersForSqlite(): void
    {
        $columns = [
            'id',
            'customer_id',
            'order_number',
            'subtotal',
            'tax',
            'total',
            'status',
            'payment_provider',
            'payment_gateway',
            'payment_reference',
            'fulfillment_method',
            'payment_status',
            'payment_amount',
            'currency',
            'paid_at',
            'inventory_committed_at',
            'shipping_method_id',
            'shipping_method_name',
            'shipping_delivery_days',
            'shipping_base_cost',
            'shipping_discount_amount',
            'shipping_cost',
            'delivery_carrier',
            'tracking_number',
            'tracking_notes',
            'admin_notes',
            'customer_notes',
            'notes',
            'created_at',
            'updated_at',
        ];

        Schema::disableForeignKeyConstraints();
        Schema::create('orders_stage_b2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status')->default('Pending');
            $table->string('payment_provider')->default('square');
            $table->string('payment_gateway')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_method')->default('pickup');
            $table->string('payment_status')->default('Pending');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('cad');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_method_name')->nullable();
            $table->string('shipping_delivery_days')->nullable();
            $table->decimal('shipping_base_cost', 10, 2)->default(0);
            $table->decimal('shipping_discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('delivery_carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('tracking_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO orders_stage_b2 ('.implode(', ', $columns).') SELECT '.implode(', ', $columns).' FROM orders');
        Schema::drop('orders');
        Schema::rename('orders_stage_b2', 'orders');
        Schema::enableForeignKeyConstraints();
    }

    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
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
