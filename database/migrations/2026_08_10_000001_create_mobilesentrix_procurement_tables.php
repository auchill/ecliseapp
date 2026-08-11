<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobilesentrix_buffer')) {
            Schema::create('mobilesentrix_buffer', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();

                // Eclise-side origin references. Exactly one is populated per requirement.
                $table->string('order_number')->nullable();
                $table->string('repair_number')->nullable();

                // Idempotency identity: the purchased line that created this requirement.
                // Keying on the source line (not the payment event) means a replayed webhook
                // cannot duplicate the row, while the same SKU bought again in a different
                // order produces a different source_reference_id and is correctly queued.
                $table->string('source_reference_type', 40);
                $table->unsignedBigInteger('source_reference_id');

                $table->boolean('is_device')->default(false);
                $table->boolean('is_part')->default(false);

                // MobileSentrix catalogue identity. No foreign key: devices and parts live in
                // separate tables, so one column cannot honestly reference both.
                $table->unsignedBigInteger('source_id');
                $table->string('source_sku');

                $table->unsignedInteger('quantity');
                $table->unsignedInteger('processed_quantity')->default(0);
                $table->string('status', 20)->default('Pending');

                $table->timestamps();

                $table->unique(['source_reference_type', 'source_reference_id'], 'ms_buffer_source_reference_unique');
                $table->index('customer_id', 'ms_buffer_customer_index');
                $table->index('order_number', 'ms_buffer_order_number_index');
                $table->index('repair_number', 'ms_buffer_repair_number_index');
                $table->index('source_sku', 'ms_buffer_source_sku_index');
                $table->index('source_id', 'ms_buffer_source_id_index');
                $table->index(['status', 'created_at'], 'ms_buffer_status_created_index');
                $table->index(['is_device', 'is_part'], 'ms_buffer_type_index');
            });
        }

        if (! Schema::hasTable('mobilesentrix_orders')) {
            Schema::create('mobilesentrix_orders', function (Blueprint $table): void {
                $table->id();

                // Internal Eclise procurement number. Never overwritten with the supplier's number.
                $table->string('order_number')->nullable();

                // The reference MobileSentrix returns when the admin places the order manually.
                $table->string('supplier_order_number')->nullable();

                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->decimal('payment_amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('cad');
                $table->timestamp('paid_at')->nullable();

                $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
                $table->string('shipping_method_name')->nullable();
                $table->string('shipping_delivery_days')->nullable();
                $table->decimal('shipping_discount_amount', 10, 2)->default(0);
                $table->decimal('shipping_cost', 10, 2)->default(0);

                $table->string('delivery_carrier')->nullable();
                $table->string('tracking_number')->nullable();
                $table->text('tracking_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->text('notes')->nullable();

                $table->string('order_status', 20)->default('Ordered');

                // Who created the procurement order, for the audit trail.
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->unique('order_number', 'ms_orders_order_number_unique');
                $table->index('supplier_order_number', 'ms_orders_supplier_number_index');
                $table->index('order_status', 'ms_orders_status_index');
                $table->index('paid_at', 'ms_orders_paid_at_index');
                $table->index('tracking_number', 'ms_orders_tracking_index');
            });
        }

        if (! Schema::hasTable('mobilesentrix_order_items')) {
            Schema::create('mobilesentrix_order_items', function (Blueprint $table): void {
                $table->id();

                // Mandatory parent link. Without it a procurement order cannot own its lines.
                $table->foreignId('mobilesentrix_order_id')->constrained('mobilesentrix_orders')->cascadeOnDelete();

                // Which buffer requirement this line drew from, preserving traceability back to
                // the customer transaction even after the buffer row is fully processed.
                $table->foreignId('mobilesentrix_buffer_id')->nullable()->constrained('mobilesentrix_buffer')->nullOnDelete();

                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('order_number')->nullable();
                $table->string('repair_number')->nullable();

                $table->boolean('is_device')->default(false);
                $table->boolean('is_part')->default(false);

                $table->unsignedBigInteger('source_id');
                $table->string('source_sku');

                $table->unsignedInteger('quantity');
                $table->decimal('mobilesentrix_price', 10, 2)->default(0);
                $table->decimal('mobilesentrix_tax', 10, 2)->default(0);

                $table->timestamps();

                $table->index('mobilesentrix_order_id', 'ms_order_items_order_index');
                $table->index('mobilesentrix_buffer_id', 'ms_order_items_buffer_index');
                $table->index('customer_id', 'ms_order_items_customer_index');
                $table->index('order_number', 'ms_order_items_order_number_index');
                $table->index('repair_number', 'ms_order_items_repair_number_index');
                $table->index('source_id', 'ms_order_items_source_id_index');
                $table->index('source_sku', 'ms_order_items_source_sku_index');
            });
        }

        // Mirrors order_number_sequences, which is keyed on year alone and therefore cannot
        // host a second independent series.
        if (! Schema::hasTable('mobilesentrix_order_number_sequences')) {
            Schema::create('mobilesentrix_order_number_sequences', function (Blueprint $table): void {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        $this->seedProcurementPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('mobilesentrix_order_items');
        Schema::dropIfExists('mobilesentrix_orders');
        Schema::dropIfExists('mobilesentrix_buffer');
        Schema::dropIfExists('mobilesentrix_order_number_sequences');
    }

    /**
     * Permission names are seeded into the existing permissions catalogue, matching the
     * convention established by the Stage 2 payment migration.
     */
    private function seedProcurementPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ([
            'mobilesentrix.buffer.view',
            'mobilesentrix.orders.view',
            'mobilesentrix.orders.create',
            'mobilesentrix.orders.update',
            'mobilesentrix.orders.receive',
            'mobilesentrix.orders.return',
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['status' => 'active', 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
};
