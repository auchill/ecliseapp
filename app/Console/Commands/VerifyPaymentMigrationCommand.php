<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyPaymentMigrationCommand extends Command
{
    protected $signature = 'eclise:verify-payment-migration
        {--repair : Reserved for future deterministic repair actions}
        {--dry-run : Show verification only without writes}
        {--json : Output the full report as JSON}';

    protected $description = 'Verify unified payment module schema and data integrity.';

    private array $report = [];

    private array $blockers = [];

    private array $warnings = [];

    public function handle(): int
    {
        $this->checkSchema();
        $this->checkPaymentRecords();
        $this->checkTransactions();
        $this->checkRefunds();
        $this->checkInvoices();
        $this->checkWebhookEvents();
        $this->checkAggregateConsistency();

        $status = $this->blockers === []
            ? 'PAYMENT MIGRATION VERIFIED'
            : 'PAYMENT MIGRATION NEEDS ATTENTION';

        if ((bool) $this->option('repair')) {
            $this->warning('The --repair option is reserved; no payment data was changed.');
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'status' => $status,
                'report' => $this->report,
                'blockers' => $this->blockers,
                'warnings' => $this->warnings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->blockers === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderReport($status);

        return $this->blockers === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkSchema(): void
    {
        foreach ([
            'payments',
            'payment_transactions',
            'payment_refunds',
            'payment_webhook_events',
            'invoices',
            'invoice_items',
        ] as $table) {
            $this->addMetric('schema', "{$table}_table_exists", $this->hasTable($table) ? 'yes' : 'no', ! $this->hasTable($table));
        }

        $paymentColumns = [
            'payment_number',
            'invoice_id',
            'customer_id',
            'purpose',
            'method',
            'provider',
            'refunded_amount',
            'gateway_payment_id',
            'gateway_reference',
            'received_by',
            'verified_by',
            'metadata',
        ];

        foreach ($paymentColumns as $column) {
            $this->addMetric('schema', "payments_{$column}_exists", $this->hasColumn('payments', $column) ? 'yes' : 'no', ! $this->hasColumn('payments', $column));
        }
    }

    private function checkPaymentRecords(): void
    {
        if (! $this->hasTable('payments')) {
            return;
        }

        $allowedStatuses = array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases());
        $allowedMethods = array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases());
        $allowedProviders = array_map(fn (PaymentProvider $provider): string => $provider->value, PaymentProvider::cases());
        $allowedPurposes = array_map(fn (PaymentPurpose $purpose): string => $purpose->value, PaymentPurpose::cases());
        $successful = PaymentStatus::successfulValues();

        $total = DB::table('payments')->count();
        $invalidStatuses = DB::table('payments')->whereNotIn('status', $allowedStatuses)->count();
        $successfulWithoutPaidAt = DB::table('payments')->whereIn('status', $successful)->whereNull('paid_at')->count();
        $refundedOverAmount = $this->hasColumn('payments', 'refunded_amount')
            ? DB::table('payments')->whereRaw('refunded_amount > amount')->count()
            : 0;
        $missingPaymentNumbers = $this->hasColumn('payments', 'payment_number')
            ? DB::table('payments')->where(function ($query): void {
                $query->whereNull('payment_number')->orWhere('payment_number', '');
            })->count()
            : $total;
        $duplicatePaymentNumbers = $this->duplicateValueCount('payments', 'payment_number');
        $missingCustomers = $this->hasColumn('payments', 'customer_id')
            ? DB::table('payments')->whereNull('customer_id')->count()
            : $total;
        $invalidCustomers = $this->invalidForeignKeyCount('payments', 'customer_id', 'customers');
        $invalidMethods = $this->hasColumn('payments', 'method')
            ? DB::table('payments')->whereNotNull('method')->whereNotIn('method', $allowedMethods)->count()
            : 0;
        $invalidProviders = $this->hasColumn('payments', 'provider')
            ? DB::table('payments')->whereNotNull('provider')->whereNotIn('provider', $allowedProviders)->count()
            : 0;
        $invalidPurposes = $this->hasColumn('payments', 'purpose')
            ? DB::table('payments')->whereNotNull('purpose')->whereNotIn('purpose', $allowedPurposes)->count()
            : 0;
        $orphanedPayables = $this->orphanedPayableCount();
        $manualSuccessfulWithoutReceiver = $this->manualSuccessfulWithoutReceiverCount();

        $this->addMetric('payments', 'total_payments', $total);
        $this->addMetric('payments', 'missing_payment_numbers', $missingPaymentNumbers, $missingPaymentNumbers > 0);
        $this->addMetric('payments', 'duplicate_payment_numbers', $duplicatePaymentNumbers, $duplicatePaymentNumbers > 0);
        $this->addMetric('payments', 'invalid_statuses', $invalidStatuses, $invalidStatuses > 0);
        $this->addMetric('payments', 'successful_without_paid_at', $successfulWithoutPaidAt, $successfulWithoutPaidAt > 0);
        $this->addMetric('payments', 'refunded_amount_over_payment_amount', $refundedOverAmount, $refundedOverAmount > 0);
        $this->addMetric('payments', 'missing_customer_id', $missingCustomers, false, $missingCustomers > 0);
        $this->addMetric('payments', 'invalid_customer_id', $invalidCustomers, $invalidCustomers > 0);
        $this->addMetric('payments', 'invalid_methods', $invalidMethods, $invalidMethods > 0);
        $this->addMetric('payments', 'invalid_providers', $invalidProviders, $invalidProviders > 0);
        $this->addMetric('payments', 'invalid_purposes', $invalidPurposes, $invalidPurposes > 0);
        $this->addMetric('payments', 'orphaned_payable_records', $orphanedPayables, $orphanedPayables > 0);
        $this->addMetric('payments', 'manual_successful_without_receiver', $manualSuccessfulWithoutReceiver, false, $manualSuccessfulWithoutReceiver > 0);
        $this->addMetric('payments', 'duplicate_gateway_payment_ids', $this->duplicateValueCount('payments', 'gateway_payment_id'), $this->duplicateValueCount('payments', 'gateway_payment_id') > 0);
    }

    private function checkTransactions(): void
    {
        if (! $this->hasTable('payment_transactions')) {
            return;
        }

        $orphans = $this->invalidForeignKeyCount('payment_transactions', 'payment_id', 'payments');
        $successfulPaymentsWithoutTransaction = $this->hasTable('payments')
            ? DB::table('payments')
                ->whereIn('status', PaymentStatus::successfulValues())
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('payment_transactions')
                        ->whereColumn('payment_transactions.payment_id', 'payments.id');
                })
                ->count()
            : 0;

        $this->addMetric('transactions', 'total_transactions', DB::table('payment_transactions')->count());
        $this->addMetric('transactions', 'orphaned_transactions', $orphans, $orphans > 0);
        $this->addMetric('transactions', 'successful_payments_without_transactions', $successfulPaymentsWithoutTransaction, false, $successfulPaymentsWithoutTransaction > 0);
    }

    private function checkRefunds(): void
    {
        if (! $this->hasTable('payment_refunds')) {
            return;
        }

        $orphans = $this->invalidForeignKeyCount('payment_refunds', 'payment_id', 'payments');
        $succeededWithoutApprovedBy = DB::table('payment_refunds')
            ->where('status', RefundStatus::Succeeded->value)
            ->whereNull('approved_by')
            ->count();
        $refundsOverPayment = DB::table('payment_refunds')
            ->join('payments', 'payment_refunds.payment_id', '=', 'payments.id')
            ->select('payment_refunds.payment_id')
            ->groupBy('payment_refunds.payment_id', 'payments.amount')
            ->havingRaw('SUM(payment_refunds.amount) > payments.amount')
            ->get()
            ->count();

        $this->addMetric('refunds', 'total_refunds', DB::table('payment_refunds')->count());
        $this->addMetric('refunds', 'orphaned_refunds', $orphans, $orphans > 0);
        $this->addMetric('refunds', 'succeeded_refunds_without_approval', $succeededWithoutApprovedBy, false, $succeededWithoutApprovedBy > 0);
        $this->addMetric('refunds', 'refund_totals_over_payment_amount', $refundsOverPayment, $refundsOverPayment > 0);
    }

    private function checkInvoices(): void
    {
        if (! $this->hasTable('invoices')) {
            return;
        }

        $allowedStatuses = array_map(fn (InvoiceStatus $status): string => $status->value, InvoiceStatus::cases());
        $invalidStatuses = DB::table('invoices')->whereNotIn('status', $allowedStatuses)->count();
        $invalidCustomers = $this->invalidForeignKeyCount('invoices', 'customer_id', 'customers');
        $negativeBalances = DB::table('invoices')->where('balance_due', '<', 0)->count();
        $missingNumbers = DB::table('invoices')->where(function ($query): void {
            $query->whereNull('invoice_number')->orWhere('invoice_number', '');
        })->count();
        $itemOrphans = $this->hasTable('invoice_items')
            ? $this->invalidForeignKeyCount('invoice_items', 'invoice_id', 'invoices')
            : 0;

        $this->addMetric('invoices', 'total_invoices', DB::table('invoices')->count());
        $this->addMetric('invoices', 'missing_invoice_numbers', $missingNumbers, $missingNumbers > 0);
        $this->addMetric('invoices', 'invalid_invoice_statuses', $invalidStatuses, $invalidStatuses > 0);
        $this->addMetric('invoices', 'invalid_customer_id', $invalidCustomers, $invalidCustomers > 0);
        $this->addMetric('invoices', 'negative_balances', $negativeBalances, $negativeBalances > 0);
        $this->addMetric('invoices', 'orphaned_invoice_items', $itemOrphans, $itemOrphans > 0);
    }

    private function checkWebhookEvents(): void
    {
        if (! $this->hasTable('payment_webhook_events')) {
            return;
        }

        $duplicates = DB::table('payment_webhook_events')
            ->select('provider', 'provider_event_id')
            ->groupBy('provider', 'provider_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $missingProviderEventId = DB::table('payment_webhook_events')
            ->where(function ($query): void {
                $query->whereNull('provider_event_id')->orWhere('provider_event_id', '');
            })
            ->count();
        $failedEvents = DB::table('payment_webhook_events')->where('status', 'failed')->count();

        $this->addMetric('webhooks', 'total_webhook_events', DB::table('payment_webhook_events')->count());
        $this->addMetric('webhooks', 'duplicate_provider_event_ids', $duplicates, $duplicates > 0);
        $this->addMetric('webhooks', 'missing_provider_event_ids', $missingProviderEventId, $missingProviderEventId > 0);
        $this->addMetric('webhooks', 'failed_webhook_events', $failedEvents, false, $failedEvents > 0);
    }

    private function checkAggregateConsistency(): void
    {
        if ($this->hasTable('orders') && $this->hasTable('payments')) {
            $paidOrdersWithoutPayments = DB::table('orders')
                ->where('payment_status', 'paid')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('payments')
                        ->whereColumn('payments.order_id', 'orders.id')
                        ->whereIn('payments.status', PaymentStatus::successfulValues());
                })
                ->count();

            $this->addMetric('orders', 'paid_orders_without_successful_payment', $paidOrdersWithoutPayments, false, $paidOrdersWithoutPayments > 0);
        }

        if ($this->hasTable('repairs') && $this->hasTable('payments')) {
            $paidRepairsWithoutEnoughPayments = DB::table('repairs')
                ->where('payment_status', 'paid')
                ->get(['id', 'total_amount', 'repair_total'])
                ->filter(function ($repair): bool {
                    $total = (float) ($repair->total_amount ?: $repair->repair_total);
                    $paid = (float) DB::table('payments')
                        ->where('repair_id', $repair->id)
                        ->whereIn('status', PaymentStatus::successfulValues())
                        ->sum('amount');

                    return $total > 0 && round($paid, 2) + 0.01 < round($total, 2);
                })
                ->count();

            $this->addMetric('repairs', 'paid_repairs_without_sufficient_successful_payments', $paidRepairsWithoutEnoughPayments, false, $paidRepairsWithoutEnoughPayments > 0);
        }
    }

    private function orphanedPayableCount(): int
    {
        return Payment::query()
            ->whereNotNull('payable_type')
            ->whereNotNull('payable_id')
            ->get(['id', 'payable_type', 'payable_id'])
            ->filter(function (Payment $payment): bool {
                if (! class_exists($payment->payable_type) || ! is_subclass_of($payment->payable_type, Model::class)) {
                    return true;
                }

                return ! $payment->payable_type::query()->whereKey($payment->payable_id)->exists();
            })
            ->count();
    }

    private function manualSuccessfulWithoutReceiverCount(): int
    {
        if (! $this->hasColumn('payments', 'received_by')) {
            return 0;
        }

        return DB::table('payments')
            ->whereIn('status', PaymentStatus::successfulValues())
            ->whereNull('received_by')
            ->where(function ($query): void {
                $query->whereIn('provider', ['manual', 'terminal'])
                    ->orWhereIn('method', ['cash', 'interac', 'debit_terminal', 'credit_terminal', 'pay_in_store']);
            })
            ->count();
    }

    private function duplicateValueCount(string $table, string $column): int
    {
        if (! $this->hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function invalidForeignKeyCount(string $table, string $column, string $targetTable): int
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, $column) || ! $this->hasTable($targetTable)) {
            return 0;
        }

        return DB::table($table)
            ->whereNotNull("{$table}.{$column}")
            ->whereNotExists(function ($query) use ($table, $column, $targetTable): void {
                $query->selectRaw('1')
                    ->from($targetTable)
                    ->whereColumn("{$targetTable}.id", "{$table}.{$column}");
            })
            ->count();
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function addMetric(
        string $section,
        string $key,
        mixed $value,
        bool $blocker = false,
        bool $warning = false,
    ): void {
        $this->report[$section][$key] = $value;

        $message = "{$section}.{$key}: {$value}";

        if ($blocker) {
            $this->blockers[] = $message;
        } elseif ($warning) {
            $this->warnings[] = $message;
        }
    }

    private function renderReport(string $status): void
    {
        $this->line($status);

        foreach ($this->report as $section => $metrics) {
            $this->newLine();
            $this->line(strtoupper($section));

            foreach ($metrics as $key => $value) {
                $this->line("  {$key}: {$value}");
            }
        }

        if ($this->blockers !== []) {
            $this->newLine();
            $this->error('Blockers');
            foreach ($this->blockers as $blocker) {
                $this->line("  - {$blocker}");
            }
        }

        if ($this->warnings !== []) {
            $this->newLine();
            $this->warn('Warnings');
            foreach ($this->warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }
    }
}
