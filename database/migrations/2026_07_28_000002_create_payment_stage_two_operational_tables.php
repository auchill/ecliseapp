<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'receipt_number')) {
                    $table->string('receipt_number')->nullable()->after('payment_number');
                }
                if (! Schema::hasColumn('payments', 'manual_reference')) {
                    $table->string('manual_reference')->nullable()->after('gateway_reference');
                }
                if (! Schema::hasColumn('payments', 'proof_path')) {
                    $table->string('proof_path')->nullable()->after('manual_reference');
                }
                if (! Schema::hasColumn('payments', 'proof_original_name')) {
                    $table->string('proof_original_name')->nullable()->after('proof_path');
                }
                if (! Schema::hasColumn('payments', 'proof_mime_type')) {
                    $table->string('proof_mime_type')->nullable()->after('proof_original_name');
                }
                if (! Schema::hasColumn('payments', 'proof_size')) {
                    $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime_type');
                }
                if (! Schema::hasColumn('payments', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('verified_at');
                }
                if (! Schema::hasColumn('payments', 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                }
                if (! Schema::hasColumn('payments', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_at');
                }
                if (! Schema::hasColumn('payments', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('verified_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'source_ip')) {
                    $table->string('source_ip', 45)->nullable()->after('created_by');
                }
            });

            Schema::table('payments', function (Blueprint $table): void {
                if (! $this->indexExists('payments', 'payments_receipt_number_unique')) {
                    $table->unique('receipt_number', 'payments_receipt_number_unique');
                }
                if (! $this->indexExists('payments', 'payments_manual_reference_index')) {
                    $table->index('manual_reference', 'payments_manual_reference_index');
                }
            });
        }

        if (Schema::hasTable('payment_refunds')) {
            Schema::table('payment_refunds', function (Blueprint $table): void {
                if (! Schema::hasColumn('payment_refunds', 'requested_method')) {
                    $table->string('requested_method')->nullable()->after('reason');
                }
                if (! Schema::hasColumn('payment_refunds', 'processed_method')) {
                    $table->string('processed_method')->nullable()->after('requested_method');
                }
                if (! Schema::hasColumn('payment_refunds', 'manual_reference')) {
                    $table->string('manual_reference')->nullable()->after('provider_reference');
                }
                if (! Schema::hasColumn('payment_refunds', 'internal_note')) {
                    $table->text('internal_note')->nullable()->after('reason');
                }
                if (! Schema::hasColumn('payment_refunds', 'source_ip')) {
                    $table->string('source_ip', 45)->nullable()->after('processed_by');
                }
            });
        }

        if (! Schema::hasTable('payment_settings')) {
            Schema::create('payment_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->boolean('is_secret')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_audit_logs')) {
            Schema::create('payment_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('event')->index();
                $table->nullableMorphs('auditable');
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('refund_id')->nullable()->constrained('payment_refunds')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_type')->nullable();
                $table->string('source_ip', 45)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['event', 'created_at']);
            });
        }

        $this->seedPaymentPermissions();
        $this->seedDefaultPaymentSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
        Schema::dropIfExists('payment_settings');

        if (Schema::hasTable('payment_refunds')) {
            Schema::table('payment_refunds', function (Blueprint $table): void {
                $columns = collect([
                    'requested_method',
                    'processed_method',
                    'manual_reference',
                    'internal_note',
                    'source_ip',
                ])->filter(fn (string $column): bool => Schema::hasColumn('payment_refunds', $column))->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if ($this->indexExists('payments', 'payments_receipt_number_unique')) {
                    $table->dropUnique('payments_receipt_number_unique');
                }
                if ($this->indexExists('payments', 'payments_manual_reference_index')) {
                    $table->dropIndex('payments_manual_reference_index');
                }
            });

            Schema::table('payments', function (Blueprint $table): void {
                foreach (['rejected_by', 'created_by'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }

                $columns = collect([
                    'receipt_number',
                    'manual_reference',
                    'proof_path',
                    'proof_original_name',
                    'proof_mime_type',
                    'proof_size',
                    'submitted_at',
                    'rejected_at',
                    'rejection_reason',
                    'source_ip',
                ])->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    private function seedPaymentPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ([
            'payments.view',
            'payments.view.all',
            'payments.view.repair',
            'payments.view.shop',
            'payments.create',
            'payments.record.manual',
            'payments.verify.interac',
            'payments.reject.interac',
            'payments.cancel',
            'payments.refund.request',
            'payments.refund.approve',
            'payments.refund.process',
            'payments.webhooks.view',
            'payments.webhooks.retry',
            'payments.reconcile',
            'payments.export',
            'payments.settings.manage',
            'payments.gateway.payload.view',
            'invoices.view',
            'invoices.create',
            'invoices.issue',
            'invoices.cancel',
            'invoices.print',
            'receipts.view',
            'receipts.print',
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['status' => 'active', 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    private function seedDefaultPaymentSettings(): void
    {
        if (! Schema::hasTable('payment_settings')) {
            return;
        }

        foreach ([
            'default_currency' => ['CAD', 'string'],
            'stripe_enabled' => ['1', 'boolean'],
            'paypal_enabled' => ['1', 'boolean'],
            'interac_enabled' => ['1', 'boolean'],
            'cash_enabled' => ['1', 'boolean'],
            'debit_terminal_enabled' => ['1', 'boolean'],
            'credit_terminal_enabled' => ['1', 'boolean'],
            'pay_in_store_enabled' => ['1', 'boolean'],
            'repair_partial_payments_enabled' => ['1', 'boolean'],
            'shop_partial_payments_enabled' => ['0', 'boolean'],
            'repair_deposit_type' => ['full_payment', 'string'],
            'repair_deposit_fixed_amount' => ['0.00', 'decimal'],
            'repair_deposit_percentage' => ['40.00', 'decimal'],
            'repair_minimum_deposit' => ['0.00', 'decimal'],
            'require_repair_deposit_before_work' => ['1', 'boolean'],
            'require_full_payment_before_repair_pickup' => ['1', 'boolean'],
            'require_full_payment_before_repair_delivery' => ['1', 'boolean'],
            'require_full_payment_before_shop_shipping' => ['1', 'boolean'],
            'allow_pay_in_store_for_pickup' => ['1', 'boolean'],
            'refund_approval_required' => ['1', 'boolean'],
            'refund_approval_threshold' => ['0.00', 'decimal'],
            'automatic_receipt_email' => ['1', 'boolean'],
            'automatic_invoice_email' => ['0', 'boolean'],
            'interac_recipient_name' => ['', 'string'],
            'interac_recipient_email' => ['', 'string'],
            'interac_instructions' => ['Use the invoice number as the transfer message.', 'text'],
            'invoice_due_days' => ['14', 'integer'],
            'invoice_terms' => ['Payment is due by the invoice due date.', 'text'],
        ] as $key => [$value, $type]) {
            DB::table('payment_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()],
            );
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
