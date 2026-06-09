<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotes')) {
            Schema::create('quotes', function (Blueprint $table): void {
                $table->id();
                $table->string('quote_number')->unique();
                $table->string('customer_name');
                $table->string('email');
                $table->string('phone_number');
                $table->foreignId('device_type_id')->nullable()->constrained('device_types')->nullOnDelete();
                $table->foreignId('device_brand_id')->nullable()->constrained('device_brands')->nullOnDelete();
                $table->foreignId('device_model_id')->nullable()->constrained('device_models')->nullOnDelete();
                $table->string('device_model')->nullable();
                $table->foreignId('issue_category_id')->nullable()->constrained('issue_categories')->nullOnDelete();
                $table->date('preferred_date')->nullable();
                $table->string('preferred_time')->nullable();
                $table->string('device_image')->nullable();
                $table->text('issue_description');
                $table->string('status')->default('pending');
                $table->text('admin_note')->nullable();
                $table->boolean('converted_to_booking')->default(false);
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        Schema::table('repair_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_bookings', 'quote_id')) {
                $table->foreignId('quote_id')->nullable()->after('id')->constrained('quotes')->nullOnDelete();
            }

            if (! Schema::hasColumn('repair_bookings', 'device_type_id')) {
                $table->foreignId('device_type_id')->nullable()->after('device_type')->constrained('device_types')->nullOnDelete();
            }

            if (! Schema::hasColumn('repair_bookings', 'device_brand_id')) {
                $table->foreignId('device_brand_id')->nullable()->after('device_brand')->constrained('device_brands')->nullOnDelete();
            }

            if (! Schema::hasColumn('repair_bookings', 'device_model_id')) {
                $table->foreignId('device_model_id')->nullable()->after('device_model')->constrained('device_models')->nullOnDelete();
            }

            if (! Schema::hasColumn('repair_bookings', 'issue_category_id')) {
                $table->foreignId('issue_category_id')->nullable()->after('issue_category')->constrained('issue_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('repair_bookings', 'repair_items')) {
                $table->json('repair_items')->nullable()->after('device_image_path');
            }

            if (! Schema::hasColumn('repair_bookings', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0)->after('repair_items');
            }

            if (! Schema::hasColumn('repair_bookings', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('repair_bookings', 'shipping_amount')) {
                $table->decimal('shipping_amount', 10, 2)->default(0)->after('tax_amount');
            }

            if (! Schema::hasColumn('repair_bookings', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0)->after('shipping_amount');
            }

            if (! Schema::hasColumn('repair_bookings', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('total_amount');
            }

            if (! Schema::hasColumn('repair_bookings', 'balance_due')) {
                $table->decimal('balance_due', 10, 2)->default(0)->after('amount_paid');
            }

            if (! Schema::hasColumn('repair_bookings', 'repair_status')) {
                $table->string('repair_status')->default('booking_created')->after('payment_status');
            }

            if (! Schema::hasColumn('repair_bookings', 'customer_remark')) {
                $table->text('customer_remark')->nullable()->after('customer_notes');
            }

            if (! Schema::hasColumn('repair_bookings', 'pickup_or_shipping_option')) {
                $table->string('pickup_or_shipping_option')->default('pickup')->after('fulfillment_method');
            }
        });

        $this->backfillRepairBookings();
    }

    public function down(): void
    {
        Schema::table('repair_bookings', function (Blueprint $table): void {
            foreach ([
                'quote_id',
                'device_type_id',
                'device_brand_id',
                'device_model_id',
                'issue_category_id',
            ] as $column) {
                if (Schema::hasColumn('repair_bookings', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            $columns = [
                'repair_items',
                'subtotal',
                'tax_amount',
                'shipping_amount',
                'total_amount',
                'amount_paid',
                'balance_due',
                'repair_status',
                'customer_remark',
                'pickup_or_shipping_option',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('repair_bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('quotes');
    }

    private function backfillRepairBookings(): void
    {
        DB::table('repair_bookings')
            ->orderBy('id')
            ->get()
            ->each(function ($booking): void {
                $deviceTypeId = $this->referenceId('device_types', $booking->device_type ?? null);
                $deviceBrandId = $this->referenceId('device_brands', $booking->device_brand ?? null);
                $deviceModelId = $this->referenceId('device_models', $booking->device_model ?? null);
                $issueCategoryId = $this->referenceId('issue_categories', $booking->issue_category ?? null);
                $repairTotal = (float) ($booking->repair_total ?? 0);
                $shippingCost = (float) ($booking->shipping_cost ?? 0);
                $amountPaid = ($booking->payment_status ?? null) === 'paid' ? $repairTotal : 0;
                $paymentStatus = ($booking->payment_status ?? null) === 'paid' ? 'paid' : 'unpaid';

                DB::table('repair_bookings')->where('id', $booking->id)->update([
                    'device_type_id' => $deviceTypeId,
                    'device_brand_id' => $deviceBrandId,
                    'device_model_id' => $deviceModelId,
                    'issue_category_id' => $issueCategoryId,
                    'subtotal' => max(0, $repairTotal - $shippingCost),
                    'shipping_amount' => $shippingCost,
                    'total_amount' => $repairTotal,
                    'amount_paid' => $amountPaid,
                    'balance_due' => max(0, $repairTotal - $amountPaid),
                    'payment_status' => $paymentStatus,
                    'repair_status' => $this->normalizeRepairStatus($booking->status ?? null),
                    'pickup_or_shipping_option' => $booking->fulfillment_method ?? 'pickup',
                ]);
            });
    }

    private function referenceId(string $table, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        $now = now();

        DB::table($table)->updateOrInsert(
            ['slug' => $slug],
            [
                'name' => $name,
                'status' => 'active',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return DB::table($table)->where('slug', $slug)->value('id');
    }

    private function normalizeRepairStatus(?string $status): string
    {
        return match ($status) {
            'Device Received' => 'device_received',
            'Diagnosis in Progress' => 'diagnosis_in_progress',
            'Waiting for Parts' => 'waiting_for_parts',
            'Repair in Progress' => 'repair_in_progress',
            'Ready for Pickup' => 'ready_for_pickup',
            'Shipped' => 'shipped',
            'Delivered', 'Completed' => 'completed',
            'Cancelled' => 'cancelled',
            'Appointment Confirmed' => 'awaiting_device',
            default => 'booking_created',
        };
    }
};
