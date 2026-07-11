<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPAIR_NUMBER_PATTERN = '/^ECL-REP-\d{4}-\d{7}$/';

    private array $legacyColumns = [
        'user_id',
        'tracking_number',
        'customer_name',
        'email',
        'phone',
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
        if (! Schema::hasTable('repairs')) {
            return;
        }

        $this->assertReadyForCleanup();

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildRepairsForSqlite();

            return;
        }

        $this->dropForeignIfExists('repairs', 'repair_bookings_user_id_foreign');
        $this->dropIndexIfExists('repairs', 'repair_bookings_tracking_number_unique');

        $columns = $this->existingColumns('repairs', $this->legacyColumns);

        if ($columns !== []) {
            Schema::table('repairs', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        Schema::table('repairs', function (Blueprint $table): void {
            if (Schema::hasColumn('repairs', 'customer_id')) {
                $table->foreignId('customer_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('repairs', 'repair_number')) {
                $table->string('repair_number')->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repairs')) {
            return;
        }

        // Rollback recreates schema only. Restore the pre-cleanup backup for removed historical values.
        Schema::table('repairs', function (Blueprint $table): void {
            if (! Schema::hasColumn('repairs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('quote_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('repairs', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('repair_number');
            }

            if (! Schema::hasColumn('repairs', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('tracking_number');
            }

            if (! Schema::hasColumn('repairs', 'email')) {
                $table->string('email')->nullable()->after('customer_name');
            }

            if (! Schema::hasColumn('repairs', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            if (! Schema::hasColumn('repairs', 'shipping_full_name')) {
                $table->string('shipping_full_name')->nullable()->after('pickup_or_shipping_option');
            }

            if (! Schema::hasColumn('repairs', 'shipping_phone')) {
                $table->string('shipping_phone')->nullable()->after('shipping_full_name');
            }

            if (! Schema::hasColumn('repairs', 'shipping_email')) {
                $table->string('shipping_email')->nullable()->after('shipping_phone');
            }

            if (! Schema::hasColumn('repairs', 'shipping_address_line1')) {
                $table->string('shipping_address_line1')->nullable()->after('shipping_email');
            }

            if (! Schema::hasColumn('repairs', 'shipping_address_line2')) {
                $table->string('shipping_address_line2')->nullable()->after('shipping_address_line1');
            }

            if (! Schema::hasColumn('repairs', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_address_line2');
            }

            if (! Schema::hasColumn('repairs', 'shipping_province')) {
                $table->string('shipping_province')->nullable()->after('shipping_city');
            }

            if (! Schema::hasColumn('repairs', 'shipping_postal_code')) {
                $table->string('shipping_postal_code')->nullable()->after('shipping_province');
            }

            if (! Schema::hasColumn('repairs', 'shipping_country')) {
                $table->string('shipping_country')->nullable()->after('shipping_postal_code');
            }
        });

        if (! $this->indexExists('repairs', 'repair_bookings_tracking_number_unique') && Schema::hasColumn('repairs', 'tracking_number')) {
            Schema::table('repairs', function (Blueprint $table): void {
                $table->unique('tracking_number', 'repair_bookings_tracking_number_unique');
            });
        }

        Schema::table('repairs', function (Blueprint $table): void {
            if (Schema::hasColumn('repairs', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->change();
            }

            if (Schema::hasColumn('repairs', 'repair_number')) {
                $table->string('repair_number')->nullable()->change();
            }
        });
    }

    private function assertReadyForCleanup(): void
    {
        if (! Schema::hasColumn('repairs', 'customer_id')) {
            throw new RuntimeException('Cannot remove legacy repair identity columns before repairs.customer_id exists.');
        }

        if (! Schema::hasColumn('repairs', 'repair_number')) {
            throw new RuntimeException('Cannot remove legacy repair tracking column before repairs.repair_number exists.');
        }

        if (DB::table('repairs')->whereNull('customer_id')->exists()) {
            throw new RuntimeException('Cannot remove legacy repair identity columns while repairs without customer_id exist.');
        }

        if (DB::table('repairs')->where(function ($query): void {
            $query->whereNull('repair_number')->orWhere('repair_number', '');
        })->exists()) {
            throw new RuntimeException('Cannot remove legacy repair tracking column while missing repair_number values exist.');
        }

        $invalidRepairNumber = DB::table('repairs')
            ->pluck('repair_number')
            ->contains(fn (?string $number): bool => ! is_string($number) || ! preg_match(self::REPAIR_NUMBER_PATTERN, $number));

        if ($invalidRepairNumber) {
            throw new RuntimeException('Cannot remove legacy repair tracking column while invalid repair_number values exist.');
        }

        $duplicates = DB::table('repairs')
            ->select('repair_number')
            ->whereNotNull('repair_number')
            ->groupBy('repair_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Cannot remove legacy repair tracking column while duplicate repair_number values exist.');
        }
    }

    private function rebuildRepairsForSqlite(): void
    {
        $columns = [
            'id',
            'quote_id',
            'customer_id',
            'repair_number',
            'device_type',
            'device_type_id',
            'device_brand',
            'device_model',
            'issue_category',
            'issue_category_id',
            'issue_description',
            'preferred_appointment_date',
            'preferred_appointment_time',
            'device_image_path',
            'repair_items',
            'subtotal',
            'tax_amount',
            'shipping_amount',
            'total_amount',
            'amount_paid',
            'balance_due',
            'terms_accepted',
            'status',
            'payment_status',
            'repair_status',
            'payment_gateway',
            'payment_amount',
            'currency',
            'paid_at',
            'estimated_completion_date',
            'internal_notes',
            'customer_notes',
            'customer_remark',
            'fulfillment_method',
            'pickup_or_shipping_option',
            'shipping_method_id',
            'shipping_method_name',
            'shipping_delivery_days',
            'shipping_base_cost',
            'shipping_discount_amount',
            'shipping_cost',
            'repair_total',
            'delivery_carrier',
            'delivery_tracking_number',
            'tracking_notes',
            'created_at',
            'updated_at',
            'product_brand_id',
            'product_model_id',
        ];

        Schema::disableForeignKeyConstraints();
        Schema::create('repairs_stage_b2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('repair_number')->unique();
            $table->string('device_type');
            $table->foreignId('device_type_id')->nullable()->constrained('repair_device_types')->nullOnDelete();
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('issue_category');
            $table->foreignId('issue_category_id')->nullable()->constrained('issue_categories')->nullOnDelete();
            $table->text('issue_description');
            $table->date('preferred_appointment_date')->nullable();
            $table->string('preferred_appointment_time')->nullable();
            $table->string('device_image_path')->nullable();
            $table->json('repair_items')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->boolean('terms_accepted')->default(false);
            $table->string('status')->default('booking_created');
            $table->string('payment_status')->default('unpaid');
            $table->string('repair_status')->default('booking_created');
            $table->string('payment_gateway')->nullable();
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('cad');
            $table->timestamp('paid_at')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('customer_remark')->nullable();
            $table->string('fulfillment_method')->default('pickup');
            $table->string('pickup_or_shipping_option')->default('pickup');
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_method_name')->nullable();
            $table->string('shipping_delivery_days')->nullable();
            $table->decimal('shipping_base_cost', 10, 2)->default(0);
            $table->decimal('shipping_discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('repair_total', 10, 2)->default(0);
            $table->string('delivery_carrier')->nullable();
            $table->string('delivery_tracking_number')->nullable();
            $table->text('tracking_notes')->nullable();
            $table->timestamps();
            $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();
        });

        DB::statement('INSERT INTO repairs_stage_b2 ('.implode(', ', $columns).') SELECT '.implode(', ', $columns).' FROM repairs');
        Schema::drop('repairs');
        Schema::rename('repairs_stage_b2', 'repairs');
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

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
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

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $details): bool => ($details['name'] ?? null) === $index);
        } catch (Throwable) {
            return false;
        }
    }
};
