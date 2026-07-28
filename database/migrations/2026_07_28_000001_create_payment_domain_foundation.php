<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table): void {
                $table->id();
                $table->string('invoice_number')->nullable()->unique();
                $table->nullableMorphs('invoiceable');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
                $table->string('type')->default('shop_order')->index();
                $table->string('status')->default('draft')->index();
                $table->string('currency', 3)->default('cad');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('shipping_amount', 12, 2)->default(0);
                $table->decimal('fee_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->decimal('balance_due', 12, 2)->default(0);
                $table->timestamp('issued_at')->nullable()->index();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('billing_snapshot')->nullable();
                $table->json('shipping_snapshot')->nullable();
                $table->json('terms_snapshot')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('item_type')->nullable()->index();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['invoice_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'payment_number')) {
                    $table->string('payment_number')->nullable()->after('id');
                }
                if (! Schema::hasColumn('payments', 'invoice_id')) {
                    $table->foreignId('invoice_id')->nullable()->after('payable_id')->constrained('invoices')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained('customers')->restrictOnDelete();
                }
                if (! Schema::hasColumn('payments', 'purpose')) {
                    $table->string('purpose')->nullable()->after('customer_id')->index();
                }
                if (! Schema::hasColumn('payments', 'method')) {
                    $table->string('method')->nullable()->after('purpose')->index();
                }
                if (! Schema::hasColumn('payments', 'provider')) {
                    $table->string('provider')->nullable()->after('method')->index();
                }
                if (! Schema::hasColumn('payments', 'subtotal')) {
                    $table->decimal('subtotal', 12, 2)->default(0)->after('currency');
                }
                if (! Schema::hasColumn('payments', 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal');
                }
                if (! Schema::hasColumn('payments', 'fee_amount')) {
                    $table->decimal('fee_amount', 12, 2)->default(0)->after('tax_amount');
                }
                if (! Schema::hasColumn('payments', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('fee_amount');
                }
                if (! Schema::hasColumn('payments', 'refunded_amount')) {
                    $table->decimal('refunded_amount', 12, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('payments', 'gateway_payment_id')) {
                    $table->string('gateway_payment_id')->nullable()->after('gateway_reference_id')->index();
                }
                if (! Schema::hasColumn('payments', 'gateway_reference')) {
                    $table->string('gateway_reference')->nullable()->after('gateway_payment_id')->index();
                }
                if (! Schema::hasColumn('payments', 'gateway_customer_id')) {
                    $table->string('gateway_customer_id')->nullable()->after('gateway_reference')->index();
                }
                if (! Schema::hasColumn('payments', 'gateway_payment_method_id')) {
                    $table->string('gateway_payment_method_id')->nullable()->after('gateway_customer_id')->index();
                }
                if (! Schema::hasColumn('payments', 'idempotency_key')) {
                    $table->string('idempotency_key')->nullable()->after('gateway_payment_method_id')->unique();
                }
                if (! Schema::hasColumn('payments', 'authorized_at')) {
                    $table->timestamp('authorized_at')->nullable()->after('paid_at');
                }
                if (! Schema::hasColumn('payments', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable()->after('authorized_at');
                }
                if (! Schema::hasColumn('payments', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('failed_at');
                }
                if (! Schema::hasColumn('payments', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('payments', 'received_by')) {
                    $table->foreignId('received_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'verified_by')) {
                    $table->foreignId('verified_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
                if (! Schema::hasColumn('payments', 'failure_code')) {
                    $table->string('failure_code')->nullable()->after('verified_at');
                }
                if (! Schema::hasColumn('payments', 'failure_message')) {
                    $table->text('failure_message')->nullable()->after('failure_code');
                }
                if (! Schema::hasColumn('payments', 'admin_note')) {
                    $table->text('admin_note')->nullable()->after('failure_message');
                }
                if (! Schema::hasColumn('payments', 'customer_note')) {
                    $table->text('customer_note')->nullable()->after('admin_note');
                }
                if (! Schema::hasColumn('payments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('customer_note');
                }
            });

            $this->backfillPayments();

            Schema::table('payments', function (Blueprint $table): void {
                if (! $this->indexExists('payments', 'payments_payment_number_unique')) {
                    $table->unique('payment_number', 'payments_payment_number_unique');
                }
                if (! $this->indexExists('payments', 'payments_payable_payment_status_index')) {
                    $table->index(['payable_type', 'payable_id', 'status'], 'payments_payable_payment_status_index');
                }
            });
        }

        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('transaction_type')->index();
                $table->string('status')->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('cad');
                $table->string('provider_transaction_id')->nullable()->index();
                $table->string('provider_reference')->nullable()->index();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->string('failure_code')->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();

                $table->index(['payment_id', 'transaction_type']);
            });
        }

        if (! Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
                $table->string('refund_number')->nullable()->unique();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('cad');
                $table->string('status')->default('pending')->index();
                $table->string('provider_refund_id')->nullable()->index();
                $table->string('provider_reference')->nullable()->index();
                $table->string('reason_code')->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->text('failure_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider')->index();
                $table->string('provider_event_id');
                $table->string('event_type')->index();
                $table->string('status')->default('received')->index();
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_event_id'], 'payment_webhook_events_provider_event_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                foreach ([
                    'payments_payment_number_unique',
                    'payments_payable_payment_status_index',
                ] as $index) {
                    if ($this->indexExists('payments', $index)) {
                        $index === 'payments_payment_number_unique'
                            ? $table->dropUnique($index)
                            : $table->dropIndex($index);
                    }
                }
            });

            Schema::table('payments', function (Blueprint $table): void {
                foreach ([
                    'invoice_id',
                    'customer_id',
                    'received_by',
                    'verified_by',
                ] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }

                $columns = collect([
                    'payment_number',
                    'purpose',
                    'method',
                    'provider',
                    'subtotal',
                    'tax_amount',
                    'fee_amount',
                    'discount_amount',
                    'refunded_amount',
                    'gateway_payment_id',
                    'gateway_reference',
                    'gateway_customer_id',
                    'gateway_payment_method_id',
                    'idempotency_key',
                    'authorized_at',
                    'failed_at',
                    'cancelled_at',
                    'refunded_at',
                    'verified_at',
                    'failure_code',
                    'failure_message',
                    'admin_note',
                    'customer_note',
                    'metadata',
                ])->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }

    private function backfillPayments(): void
    {
        DB::table('payments')
            ->select(['id', 'created_at', 'source', 'gateway', 'gateway_reference_id', 'stripe_payment_intent_id', 'paypal_capture_id', 'paypal_order_id'])
            ->orderBy('id')
            ->get()
            ->each(function ($payment): void {
                $year = $payment->created_at ? date('Y', strtotime((string) $payment->created_at)) : date('Y');
                $gatewayPaymentId = $payment->stripe_payment_intent_id
                    ?: $payment->paypal_capture_id
                    ?: $payment->paypal_order_id
                    ?: $payment->gateway_reference_id;

                DB::table('payments')->where('id', $payment->id)->update([
                    'payment_number' => sprintf('PAY-%s-%07d', $year, $payment->id),
                    'method' => $payment->gateway,
                    'provider' => in_array($payment->gateway, ['stripe', 'paypal'], true) ? $payment->gateway : 'manual',
                    'purpose' => $payment->source === 'repair' ? 'balance' : 'shop_order',
                    'gateway_payment_id' => $gatewayPaymentId,
                    'gateway_reference' => $payment->gateway_reference_id,
                    'updated_at' => now(),
                ]);
            });

        $this->backfillPaymentCustomers();
    }

    private function backfillPaymentCustomers(): void
    {
        DB::table('payments')
            ->select(['id', 'payable_type', 'payable_id', 'order_id', 'repair_id', 'checkout_data'])
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get()
            ->each(function ($payment): void {
                $customerId = null;

                if ($payment->repair_id && Schema::hasTable('repairs')) {
                    $customerId = DB::table('repairs')->where('id', $payment->repair_id)->value('customer_id');
                }

                if (! $customerId && $payment->order_id && Schema::hasTable('orders')) {
                    $customerId = DB::table('orders')->where('id', $payment->order_id)->value('customer_id');
                }

                if (! $customerId && $payment->payable_type === 'App\\Models\\Repair' && Schema::hasTable('repairs')) {
                    $customerId = DB::table('repairs')->where('id', $payment->payable_id)->value('customer_id');
                }

                if (! $customerId && $payment->payable_type === 'App\\Models\\Order' && Schema::hasTable('orders')) {
                    $customerId = DB::table('orders')->where('id', $payment->payable_id)->value('customer_id');
                }

                if (! $customerId && $payment->payable_type === 'App\\Models\\Cart' && Schema::hasTable('carts')) {
                    $customerId = DB::table('carts')->where('id', $payment->payable_id)->value('customer_id');
                }

                if (! $customerId && $payment->checkout_data) {
                    $snapshot = json_decode((string) $payment->checkout_data, true);
                    $customerId = is_array($snapshot) ? (int) ($snapshot['customer_id'] ?? 0) : null;
                }

                if ($customerId) {
                    DB::table('payments')->where('id', $payment->id)->update([
                        'customer_id' => $customerId,
                        'updated_at' => now(),
                    ]);
                }
            });
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
