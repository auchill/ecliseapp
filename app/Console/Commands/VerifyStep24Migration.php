<?php

namespace App\Console\Commands;

use App\Services\AddressSnapshotFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class VerifyStep24Migration extends Command
{
    protected $signature = 'eclise:verify-step24-migration
        {--repair : Apply deterministic non-destructive fixes where supported}
        {--dry-run : Show repair actions without writing changes}
        {--json : Output the full report as JSON}';

    protected $description = 'Verify Step 24 quote, repair, order, and shipping data before Stage B cleanup.';

    private const REPAIR_NUMBER_PATTERN = '/^ECL-REP-(\d{4})-(\d{7})$/';

    private const LEGACY_TERMS = [
        'quote_number',
        'converted_to_booking',
        'repair_bookings',
        'RepairBooking',
        'repair_booking_id',
        'tracking_number',
        'customer_name',
        'phone_number',
        'shipping_full_name',
        'shipping_phone',
        'shipping_email',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
        'cart_id',
        'repair_order_id',
    ];

    private const STAGE_B_LEGACY_COLUMNS = [
        'quotes' => [
            'quote_number',
            'customer_name',
            'email',
            'phone_number',
            'converted_to_booking',
        ],
        'repairs' => [
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
        ],
        'orders' => [
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
        ],
        'payments' => [
            'repair_order_id',
        ],
    ];

    private array $report = [];

    private array $blockers = [];

    private array $warnings = [];

    private array $repairActions = [];

    private bool $repair = false;

    private bool $dryRun = false;

    public function handle(AddressSnapshotFormatter $addressFormatter): int
    {
        $this->repair = (bool) $this->option('repair');
        $this->dryRun = (bool) $this->option('dry-run');

        $this->checkSchema();
        $this->checkQuotes();
        $this->checkRepairs();
        $this->checkRepairShipping($addressFormatter);
        $this->checkOrders();
        $this->checkOrderShipping($addressFormatter);
        $this->checkPayments();
        $this->checkActiveLegacyReferences();

        $cleanupStarted = $this->stageBCleanupStarted();
        $cleanupComplete = $this->stageBCleanupComplete();
        $status = match (true) {
            $this->blockers === [] && $cleanupComplete => 'STAGE B CLEANUP VERIFIED',
            $this->blockers === [] && ! $cleanupStarted => 'READY FOR STAGE B CLEANUP',
            $cleanupStarted => 'STAGE B CLEANUP INVALID',
            default => 'NOT READY FOR STAGE B CLEANUP',
        };

        $this->addMetric('summary', 'cleanup_status', $status);
        $this->addMetric('summary', 'blocking_cleanup', count($this->blockers));
        $this->addMetric('summary', 'safe_for_cleanup', $this->blockers === [] ? 'yes' : 'no');
        $this->addMetric('summary', 'final_schema_verified', $this->blockers === [] && $cleanupComplete ? 'yes' : 'no');

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'status' => $status,
                'report' => $this->report,
                'blockers' => $this->blockers,
                'warnings' => $this->warnings,
                'repair_actions' => $this->repairActions,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->blockers === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderReport($status);

        return $this->blockers === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkSchema(): void
    {
        $cleanupStarted = $this->stageBCleanupStarted();
        $remainingLegacyColumns = $this->remainingStageBLegacyColumns();
        $repairBookingAliasExists = file_exists(app_path('Models/RepairBooking.php'));

        $this->addMetric('schema', 'repair_bookings_table_exists', $this->hasTable('repair_bookings') ? 'yes' : 'no', $this->hasTable('repair_bookings'));
        $this->addMetric('schema', 'repairs_table_exists', $this->hasTable('repairs') ? 'yes' : 'no', ! $this->hasTable('repairs'));
        $this->addMetric('schema', 'quotes_table_exists', $this->hasTable('quotes') ? 'yes' : 'no', ! $this->hasTable('quotes'));
        $this->addMetric('schema', 'orders_table_exists', $this->hasTable('orders') ? 'yes' : 'no', ! $this->hasTable('orders'));
        $this->addMetric('schema', 'repair_shipping_table_exists', $this->hasTable('repair_shipping') ? 'yes' : 'no', ! $this->hasTable('repair_shipping'));
        $this->addMetric('schema', 'order_shipping_table_exists', $this->hasTable('order_shipping') ? 'yes' : 'no', ! $this->hasTable('order_shipping'));
        $this->addMetric('schema', 'stage_b_cleanup_started', $cleanupStarted ? 'yes' : 'no');
        $this->addMetric('schema', 'stage_b_cleanup_complete', $this->stageBCleanupComplete() ? 'yes' : 'no');
        $this->addMetric('schema', 'remaining_stage_b_legacy_schema_columns', implode(', ', $remainingLegacyColumns) ?: 'none', $cleanupStarted && $remainingLegacyColumns !== []);
        $this->addMetric('schema', 'repair_booking_alias_exists', $repairBookingAliasExists ? 'yes' : 'no', $cleanupStarted && $repairBookingAliasExists);

        $repairBookingColumns = $this->tablesWithColumn('repair_booking_id');
        $this->addMetric('schema', 'tables_with_repair_booking_id', count($repairBookingColumns), $repairBookingColumns !== []);
        $this->addMetric('schema', 'repair_booking_id_tables', implode(', ', $repairBookingColumns) ?: 'none');

        if ($this->hasTable('quotes')) {
            $this->addMetric('schema', 'quotes_customer_id_required', $this->columnIsNullable('quotes', 'customer_id') ? 'no' : 'yes', $cleanupStarted && $this->columnIsNullable('quotes', 'customer_id'));
            $this->addMetric('schema', 'quotes_converted_to_repair_required', $this->columnIsNullable('quotes', 'converted_to_repair') ? 'no' : 'yes', $cleanupStarted && $this->columnIsNullable('quotes', 'converted_to_repair'));
        }

        if ($this->hasTable('repairs')) {
            $this->addMetric('schema', 'repairs_customer_id_required', $this->columnIsNullable('repairs', 'customer_id') ? 'no' : 'yes', $cleanupStarted && $this->columnIsNullable('repairs', 'customer_id'));
            $this->addMetric('schema', 'repairs_repair_number_required', $this->columnIsNullable('repairs', 'repair_number') ? 'no' : 'yes', $cleanupStarted && $this->columnIsNullable('repairs', 'repair_number'));
            $this->addMetric('schema', 'repairs_repair_number_unique_index', $this->hasUniqueIndex('repairs', ['repair_number']) ? 'yes' : 'no', ! $this->hasUniqueIndex('repairs', ['repair_number']));
        }

        if ($this->hasTable('orders')) {
            $this->addMetric('schema', 'orders_customer_id_required', $this->columnIsNullable('orders', 'customer_id') ? 'no' : 'yes', $cleanupStarted && $this->columnIsNullable('orders', 'customer_id'));
        }

        if ($this->hasTable('repair_status_updates')) {
            $this->addMetric('schema', 'repair_status_updates_repair_id_exists', $this->hasColumn('repair_status_updates', 'repair_id') ? 'yes' : 'no', ! $this->hasColumn('repair_status_updates', 'repair_id'));
        }

        if ($this->hasTable('repair_shipping')) {
            $this->addMetric('schema', 'repair_shipping_repair_id_unique_index', $this->hasUniqueIndex('repair_shipping', ['repair_id']) ? 'yes' : 'no', ! $this->hasUniqueIndex('repair_shipping', ['repair_id']));
        }

        if ($this->hasTable('order_shipping')) {
            $this->addMetric('schema', 'order_shipping_order_id_unique_index', $this->hasUniqueIndex('order_shipping', ['order_id']) ? 'yes' : 'no', ! $this->hasUniqueIndex('order_shipping', ['order_id']));
        }
    }

    private function checkQuotes(): void
    {
        if (! $this->hasTable('quotes')) {
            return;
        }

        $total = $this->tableCount('quotes');
        $missingCustomer = $this->hasColumn('quotes', 'customer_id')
            ? DB::table('quotes')->whereNull('customer_id')->count()
            : $total;
        $invalidCustomer = $this->invalidForeignKeyCount('quotes', 'customer_id', 'customers');
        $validCustomer = max(0, $total - $missingCustomer - $invalidCustomer);

        $convertedWithoutRepair = 0;
        $repairsWithUnconvertedQuote = 0;
        $duplicateRepairsPerQuote = 0;

        if ($this->hasTable('repairs') && $this->hasColumn('repairs', 'quote_id') && $this->hasColumn('quotes', 'converted_to_repair')) {
            $convertedWithoutRepair = DB::table('quotes')
                ->where('converted_to_repair', true)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('repairs')
                        ->whereColumn('repairs.quote_id', 'quotes.id');
                })
                ->count();

            $repairsWithUnconvertedQuote = DB::table('repairs')
                ->join('quotes', 'repairs.quote_id', '=', 'quotes.id')
                ->where(function ($query): void {
                    $query->whereNull('quotes.converted_to_repair')
                        ->orWhere('quotes.converted_to_repair', false);
                })
                ->count();

            $duplicateRepairsPerQuote = DB::table('repairs')
                ->select('quote_id')
                ->whereNotNull('quote_id')
                ->groupBy('quote_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            $this->repairQuoteConversionFlags();
        }

        $legacyConvertedStatus = $this->hasColumn('quotes', 'status')
            ? DB::table('quotes')->where('status', 'converted_to_booking')->count()
            : 0;
        $legacyConvertedFlag = $this->hasColumn('quotes', 'converted_to_booking')
            ? DB::table('quotes')->where('converted_to_booking', true)->where('converted_to_repair', false)->count()
            : 0;

        $this->addMetric('quotes', 'total_quotes', $total);
        $this->addMetric('quotes', 'quotes_with_valid_customers', $validCustomer);
        $this->addMetric('quotes', 'quotes_without_customers', $missingCustomer, $missingCustomer > 0);
        $this->addMetric('quotes', 'quotes_with_invalid_customers', $invalidCustomer, $invalidCustomer > 0);
        $this->addMetric('quotes', 'converted_quotes_without_repair', $convertedWithoutRepair, $convertedWithoutRepair > 0);
        $this->addMetric('quotes', 'repairs_with_unconverted_quote', $repairsWithUnconvertedQuote, $repairsWithUnconvertedQuote > 0);
        $this->addMetric('quotes', 'duplicate_repairs_per_quote', $duplicateRepairsPerQuote, $duplicateRepairsPerQuote > 0);
        $this->addMetric('quotes', 'legacy_converted_to_booking_status_rows', $legacyConvertedStatus, $legacyConvertedStatus > 0);
        $this->addMetric('quotes', 'legacy_converted_flag_without_new_flag', $legacyConvertedFlag, $legacyConvertedFlag > 0);
    }

    private function checkRepairs(): void
    {
        if (! $this->hasTable('repairs')) {
            return;
        }

        $total = $this->tableCount('repairs');
        $missingCustomer = $this->hasColumn('repairs', 'customer_id')
            ? DB::table('repairs')->whereNull('customer_id')->count()
            : $total;
        $invalidCustomer = $this->invalidForeignKeyCount('repairs', 'customer_id', 'customers');
        $validCustomer = max(0, $total - $missingCustomer - $invalidCustomer);

        $missingRepairNumbers = $this->hasColumn('repairs', 'repair_number')
            ? DB::table('repairs')->where(function ($query): void {
                $query->whereNull('repair_number')->orWhere('repair_number', '');
            })->count()
            : $total;

        [$invalidFormat, $yearMismatches, $maxSequences] = $this->repairNumberIntegrity();
        $duplicateRepairNumbers = $this->duplicateValueCount('repairs', 'repair_number');
        $sequenceInconsistencies = $this->repairNumberSequenceInconsistencies($maxSequences);
        $legacyTrackingMismatches = $this->repairTrackingNumberMismatches();
        $userCustomerMismatches = $this->repairUserCustomerMismatches();

        $this->addMetric('repairs', 'total_repairs', $total);
        $this->addMetric('repairs', 'repairs_with_valid_customers', $validCustomer);
        $this->addMetric('repairs', 'repairs_without_customers', $missingCustomer, $missingCustomer > 0);
        $this->addMetric('repairs', 'repairs_with_invalid_customers', $invalidCustomer, $invalidCustomer > 0);
        $this->addMetric('repairs', 'missing_repair_numbers', $missingRepairNumbers, $missingRepairNumbers > 0);
        $this->addMetric('repairs', 'invalid_repair_number_formats', $invalidFormat, $invalidFormat > 0);
        $this->addMetric('repairs', 'duplicate_repair_numbers', $duplicateRepairNumbers, $duplicateRepairNumbers > 0);
        $this->addMetric('repairs', 'repair_number_year_mismatches', $yearMismatches, $yearMismatches > 0);
        $this->addMetric('repairs', 'sequence_inconsistencies', $sequenceInconsistencies, $sequenceInconsistencies > 0);
        $this->addMetric('repairs', 'legacy_tracking_number_mismatches_informational', $legacyTrackingMismatches);
        $this->addMetric('repairs', 'user_customer_relationship_mismatches', $userCustomerMismatches, $userCustomerMismatches > 0);
    }

    private function checkRepairShipping(AddressSnapshotFormatter $formatter): void
    {
        if (! $this->hasTable('repairs') || ! $this->hasTable('repair_shipping')) {
            return;
        }

        $totalSnapshots = $this->tableCount('repair_shipping');
        $required = $this->requiredRepairShippingQuery()->count();
        $missing = $this->requiredRepairShippingQuery()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('repair_shipping')
                    ->whereColumn('repair_shipping.repair_id', 'repairs.id');
            })
            ->count();
        $validSnapshots = DB::table('repair_shipping')
            ->join('repairs', 'repair_shipping.repair_id', '=', 'repairs.id')
            ->whereNotNull('repair_shipping.shipping_address')
            ->where('repair_shipping.shipping_address', '!=', '')
            ->count();
        $duplicates = $this->duplicateValueCount('repair_shipping', 'repair_id');
        $orphans = DB::table('repair_shipping')
            ->leftJoin('repairs', 'repair_shipping.repair_id', '=', 'repairs.id')
            ->whereNull('repairs.id')
            ->count();
        $empty = DB::table('repair_shipping')
            ->where(function ($query): void {
                $query->whereNull('shipping_address')->orWhere('shipping_address', '');
            })
            ->count();
        $mismatches = $this->shippingSnapshotMismatches('repairs', 'repair_shipping', 'repair_id', $formatter);

        $this->repairMissingShippingSnapshots('repairs', 'repair_shipping', 'repair_id', $formatter);

        $this->addMetric('repair_shipping', 'total_snapshots', $totalSnapshots);
        $this->addMetric('repair_shipping', 'required_snapshots', $required);
        $this->addMetric('repair_shipping', 'existing_valid_snapshots', $validSnapshots);
        $this->addMetric('repair_shipping', 'missing_required_snapshots', $missing, $missing > 0);
        $this->addMetric('repair_shipping', 'duplicate_snapshots', $duplicates, $duplicates > 0);
        $this->addMetric('repair_shipping', 'orphaned_snapshots', $orphans, $orphans > 0);
        $this->addMetric('repair_shipping', 'empty_snapshots', $empty, $empty > 0);
        $this->addMetric('repair_shipping', 'legacy_comparison_mismatches', $mismatches, $mismatches > 0);
    }

    private function checkOrders(): void
    {
        if (! $this->hasTable('orders')) {
            return;
        }

        $total = $this->tableCount('orders');
        $missingCustomer = $this->hasColumn('orders', 'customer_id')
            ? DB::table('orders')->whereNull('customer_id')->count()
            : $total;
        $invalidCustomer = $this->invalidForeignKeyCount('orders', 'customer_id', 'customers');
        $validCustomer = max(0, $total - $missingCustomer - $invalidCustomer);
        $completedWithoutItems = $this->completedOrdersWithoutItems();
        $ordersDependentOnCarts = $this->ordersDependentOnCarts();
        $duplicateOrderPaymentReferences = $this->duplicateOrderPaymentReferences();

        $this->addMetric('orders', 'total_orders', $total);
        $this->addMetric('orders', 'orders_with_valid_customers', $validCustomer);
        $this->addMetric('orders', 'orders_without_customers', $missingCustomer, $missingCustomer > 0);
        $this->addMetric('orders', 'orders_with_invalid_customers', $invalidCustomer, $invalidCustomer > 0);
        $this->addMetric('orders', 'completed_orders_without_items', $completedWithoutItems, $completedWithoutItems > 0);
        $this->addMetric('orders', 'orders_dependent_on_carts', $ordersDependentOnCarts, $ordersDependentOnCarts > 0);
        $this->addMetric('orders', 'duplicate_payment_references', $duplicateOrderPaymentReferences, $duplicateOrderPaymentReferences > 0);
    }

    private function checkOrderShipping(AddressSnapshotFormatter $formatter): void
    {
        if (! $this->hasTable('orders') || ! $this->hasTable('order_shipping')) {
            return;
        }

        $totalSnapshots = $this->tableCount('order_shipping');
        $required = $this->requiredOrderShippingQuery()->count();
        $missing = $this->requiredOrderShippingQuery()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('order_shipping')
                    ->whereColumn('order_shipping.order_id', 'orders.id');
            })
            ->count();
        $validSnapshots = DB::table('order_shipping')
            ->join('orders', 'order_shipping.order_id', '=', 'orders.id')
            ->whereNotNull('order_shipping.shipping_address')
            ->where('order_shipping.shipping_address', '!=', '')
            ->count();
        $duplicates = $this->duplicateValueCount('order_shipping', 'order_id');
        $orphans = DB::table('order_shipping')
            ->leftJoin('orders', 'order_shipping.order_id', '=', 'orders.id')
            ->whereNull('orders.id')
            ->count();
        $empty = DB::table('order_shipping')
            ->where(function ($query): void {
                $query->whereNull('shipping_address')->orWhere('shipping_address', '');
            })
            ->count();
        $mismatches = $this->shippingSnapshotMismatches('orders', 'order_shipping', 'order_id', $formatter);

        $this->repairMissingShippingSnapshots('orders', 'order_shipping', 'order_id', $formatter);

        $this->addMetric('order_shipping', 'total_snapshots', $totalSnapshots);
        $this->addMetric('order_shipping', 'required_snapshots', $required);
        $this->addMetric('order_shipping', 'existing_valid_snapshots', $validSnapshots);
        $this->addMetric('order_shipping', 'missing_required_snapshots', $missing, $missing > 0);
        $this->addMetric('order_shipping', 'duplicate_snapshots', $duplicates, $duplicates > 0);
        $this->addMetric('order_shipping', 'orphaned_snapshots', $orphans, $orphans > 0);
        $this->addMetric('order_shipping', 'empty_snapshots', $empty, $empty > 0);
        $this->addMetric('order_shipping', 'legacy_comparison_mismatches', $mismatches, $mismatches > 0);
    }

    private function checkPayments(): void
    {
        if (! $this->hasTable('payments')) {
            return;
        }

        $total = $this->tableCount('payments');
        $invalidSources = $this->hasColumn('payments', 'source')
            ? DB::table('payments')
                ->whereNotNull('source')
                ->whereNotIn('source', ['repair', 'shop'])
                ->count()
            : $this->tableCount('payments');
        $legacyRepairPayableTypes = $this->hasColumn('payments', 'payable_type')
            ? DB::table('payments')->where('payable_type', 'App\\Models\\RepairBooking')->count()
            : 0;
        $repairPaymentsWithoutRepair = $this->hasColumn('payments', 'repair_id')
            ? DB::table('payments')->where('source', 'repair')->whereNull('repair_id')->count()
            : DB::table('payments')->where('source', 'repair')->count();
        $repairOrderIdMismatches = $this->repairOrderIdMismatches();
        $repairOrderIdColumnPresent = $this->hasColumn('payments', 'repair_order_id');
        $duplicatePaidProviderReferences = $this->duplicatePaidProviderReferences();

        $this->addMetric('payments', 'total_payments', $total);
        $this->addMetric('payments', 'invalid_source_values', $invalidSources, $invalidSources > 0);
        $this->addMetric('payments', 'legacy_repair_payable_types', $legacyRepairPayableTypes, $legacyRepairPayableTypes > 0);
        $this->addMetric('payments', 'repair_payments_without_repair_id', $repairPaymentsWithoutRepair, $repairPaymentsWithoutRepair > 0);
        $this->addMetric('payments', 'repair_order_id_mismatches', $repairOrderIdMismatches, $repairOrderIdMismatches > 0);
        $this->addMetric('payments', 'repair_order_id_column_absent', $repairOrderIdColumnPresent ? 'no' : 'yes', $this->stageBCleanupStarted() && $repairOrderIdColumnPresent);
        $this->addMetric('payments', 'duplicate_paid_provider_references', $duplicatePaidProviderReferences, $duplicatePaidProviderReferences > 0);
    }

    private function checkActiveLegacyReferences(): void
    {
        $hits = $this->scanLegacyReferences();
        $activeCount = count($hits['active']);
        $compatibilityCount = count($hits['compatibility']);
        $migrationCount = count($hits['migration_history']);
        $testCount = count($hits['test_only']);
        $documentationCount = count($hits['documentation']);
        $unrelatedCount = count($hits['unrelated']);

        $this->addMetric('legacy_references', 'active_runtime_blockers', $activeCount, $activeCount > 0);
        $this->addMetric('legacy_references', 'temporary_compatibility_references', $compatibilityCount, $this->stageBCleanupStarted() && $compatibilityCount > 0);
        $this->addMetric('legacy_references', 'migration_history_references', $migrationCount);
        $this->addMetric('legacy_references', 'test_only_legacy_references', $testCount);
        $this->addMetric('legacy_references', 'documentation_references', $documentationCount);
        $this->addMetric('legacy_references', 'unrelated_references', $unrelatedCount);
        $this->addMetric('legacy_references', 'active_sample', $this->formatReferenceSamples($hits['active']));
        $this->addMetric('legacy_references', 'compatibility_sample', $this->formatReferenceSamples($hits['compatibility']));
        $this->addMetric('legacy_references', 'unrelated_sample', $this->formatReferenceSamples($hits['unrelated']));
    }

    private function repairQuoteConversionFlags(): void
    {
        if (! $this->repair) {
            return;
        }

        $query = DB::table('quotes')
            ->where('converted_to_repair', false)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('repairs')
                    ->whereColumn('repairs.quote_id', 'quotes.id');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('repairs as duplicate_repairs')
                    ->whereColumn('duplicate_repairs.quote_id', 'quotes.id')
                    ->groupBy('duplicate_repairs.quote_id')
                    ->havingRaw('COUNT(*) > 1');
            });

        $ids = $query->pluck('id');

        foreach ($ids as $id) {
            $this->repairAction("Set converted_to_repair for quote {$id} because exactly one repair exists.");

            if (! $this->dryRun) {
                DB::table('quotes')->where('id', $id)->update([
                    'converted_to_repair' => true,
                    'status' => 'converted_to_repair',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function repairNumberIntegrity(): array
    {
        if (! $this->hasColumn('repairs', 'repair_number')) {
            return [$this->tableCount('repairs'), 0, []];
        }

        $invalidFormat = 0;
        $yearMismatches = 0;
        $maxSequences = [];

        DB::table('repairs')
            ->select(['id', 'repair_number', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function ($repair) use (&$invalidFormat, &$yearMismatches, &$maxSequences): void {
                $repairNumber = (string) ($repair->repair_number ?? '');

                if (! preg_match(self::REPAIR_NUMBER_PATTERN, $repairNumber, $matches)) {
                    $invalidFormat++;

                    return;
                }

                $year = (int) $matches[1];
                $sequence = (int) $matches[2];
                $createdYear = $repair->created_at ? (int) date('Y', strtotime((string) $repair->created_at)) : null;

                if ($createdYear && $createdYear !== $year) {
                    $yearMismatches++;
                }

                $maxSequences[$year] = max($maxSequences[$year] ?? 0, $sequence);
            });

        return [$invalidFormat, $yearMismatches, $maxSequences];
    }

    private function repairNumberSequenceInconsistencies(array $maxSequences): int
    {
        if (! $this->hasTable('repair_number_sequences')) {
            return count($maxSequences);
        }

        $inconsistencies = 0;

        foreach ($maxSequences as $year => $maxSequence) {
            $current = (int) (DB::table('repair_number_sequences')->where('year', $year)->value('last_sequence') ?? 0);

            if ($current >= $maxSequence) {
                continue;
            }

            $inconsistencies++;

            if ($this->repair) {
                $this->repairAction("Raise repair_number_sequences {$year} from {$current} to {$maxSequence}.");

                if (! $this->dryRun) {
                    if (DB::table('repair_number_sequences')->where('year', $year)->exists()) {
                        DB::table('repair_number_sequences')->where('year', $year)->update([
                            'last_sequence' => $maxSequence,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('repair_number_sequences')->insert([
                            'year' => $year,
                            'last_sequence' => $maxSequence,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        return $inconsistencies;
    }

    private function repairTrackingNumberMismatches(): int
    {
        if (! $this->hasColumn('repairs', 'tracking_number') || ! $this->hasColumn('repairs', 'repair_number')) {
            return 0;
        }

        return DB::table('repairs')
            ->whereNotNull('tracking_number')
            ->whereNotNull('repair_number')
            ->whereColumn('tracking_number', '!=', 'repair_number')
            ->count();
    }

    private function repairUserCustomerMismatches(): int
    {
        if (! $this->hasColumn('repairs', 'user_id') || ! $this->hasColumn('repairs', 'customer_id') || ! $this->hasTable('customers')) {
            return 0;
        }

        return DB::table('repairs')
            ->join('customers', 'repairs.user_id', '=', 'customers.user_id')
            ->whereNotNull('repairs.user_id')
            ->whereNotNull('repairs.customer_id')
            ->whereColumn('repairs.customer_id', '!=', 'customers.id')
            ->count();
    }

    private function completedOrdersWithoutItems(): int
    {
        if (! $this->hasTable('order_items')) {
            return DB::table('orders')->whereNotIn('status', ['Pending', 'Cancelled', 'Refunded'])->count();
        }

        return DB::table('orders')
            ->whereIn('status', ['Paid', 'Processing', 'Ready for Pickup', 'Shipped', 'Delivered', 'Completed'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id');
            })
            ->count();
    }

    private function ordersDependentOnCarts(): int
    {
        if (! $this->hasColumn('orders', 'cart_id')) {
            return 0;
        }

        if (! $this->hasTable('order_items')) {
            return DB::table('orders')->whereNotNull('cart_id')->count();
        }

        return DB::table('orders')
            ->whereNotNull('cart_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id');
            })
            ->count();
    }

    private function duplicateOrderPaymentReferences(): int
    {
        if (! $this->hasColumn('orders', 'payment_reference')) {
            return 0;
        }

        return DB::table('orders')
            ->select('payment_reference')
            ->whereNotNull('payment_reference')
            ->where('payment_reference', '!=', '')
            ->groupBy('payment_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function repairOrderIdMismatches(): int
    {
        if (! $this->hasColumn('payments', 'repair_order_id') || ! $this->hasColumn('payments', 'repair_id')) {
            return 0;
        }

        return DB::table('payments')
            ->whereNotNull('repair_order_id')
            ->whereNotNull('repair_id')
            ->whereColumn('repair_order_id', '!=', 'repair_id')
            ->count();
    }

    private function duplicatePaidProviderReferences(): int
    {
        if (! $this->hasColumn('payments', 'gateway_reference_id')) {
            return 0;
        }

        return DB::table('payments')
            ->select(['gateway', 'gateway_reference_id'])
            ->where('status', 'paid')
            ->whereNotNull('gateway_reference_id')
            ->where('gateway_reference_id', '!=', '')
            ->groupBy('gateway', 'gateway_reference_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function requiredRepairShippingQuery()
    {
        return DB::table('repairs')
            ->where('fulfillment_method', 'shipping')
            ->where(function ($query): void {
                $query->whereIn('payment_status', ['paid', 'partially_paid'])
                    ->orWhereIn('repair_status', ['shipped', 'completed'])
                    ->orWhereIn('status', ['shipped', 'completed']);
            });
    }

    private function requiredOrderShippingQuery()
    {
        return DB::table('orders')
            ->where('fulfillment_method', 'shipping')
            ->whereIn('status', ['Paid', 'Processing', 'Ready for Pickup', 'Shipped', 'Delivered', 'Completed']);
    }

    private function shippingSnapshotMismatches(string $ownerTable, string $snapshotTable, string $foreignKey, AddressSnapshotFormatter $formatter): int
    {
        $mismatches = 0;

        DB::table($ownerTable)
            ->join($snapshotTable, "{$snapshotTable}.{$foreignKey}", '=', "{$ownerTable}.id")
            ->select("{$ownerTable}.*", "{$snapshotTable}.shipping_address")
            ->where("{$ownerTable}.fulfillment_method", 'shipping')
            ->orderBy("{$ownerTable}.id")
            ->get()
            ->each(function ($row) use (&$mismatches, $formatter): void {
                $legacy = $this->legacyAddressForRow($row, $formatter);

                if ($legacy === '') {
                    return;
                }

                if ($this->normalizeAddress($legacy) !== $this->normalizeAddress((string) $row->shipping_address)) {
                    $mismatches++;
                }
            });

        return $mismatches;
    }

    private function repairMissingShippingSnapshots(string $ownerTable, string $snapshotTable, string $foreignKey, AddressSnapshotFormatter $formatter): void
    {
        if (! $this->repair) {
            return;
        }

        $requiredQuery = $ownerTable === 'repairs'
            ? $this->requiredRepairShippingQuery()
            : $this->requiredOrderShippingQuery();

        $requiredQuery
            ->whereNotExists(function ($query) use ($snapshotTable, $foreignKey, $ownerTable): void {
                $query->selectRaw('1')
                    ->from($snapshotTable)
                    ->whereColumn("{$snapshotTable}.{$foreignKey}", "{$ownerTable}.id");
            })
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($snapshotTable, $foreignKey, $formatter): void {
                $address = $this->legacyAddressForRow($row, $formatter);

                if ($address === '') {
                    $this->warning("Cannot create {$snapshotTable} snapshot for row {$row->id}: legacy address is incomplete.");

                    return;
                }

                $this->repairAction("Create {$snapshotTable} snapshot for {$foreignKey} {$row->id}.");

                if (! $this->dryRun) {
                    DB::table($snapshotTable)->insert([
                        $foreignKey => $row->id,
                        'shipping_address' => $address,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function legacyAddressForRow(object $row, AddressSnapshotFormatter $formatter): string
    {
        return $formatter->format([
            'shipping_full_name' => $row->shipping_full_name ?? $row->customer_name ?? null,
            'shipping_phone' => $row->shipping_phone ?? $row->phone ?? null,
            'shipping_email' => $row->shipping_email ?? $row->email ?? null,
            'shipping_address_line1' => $row->shipping_address_line1 ?? null,
            'shipping_address_line2' => $row->shipping_address_line2 ?? null,
            'shipping_city' => $row->shipping_city ?? null,
            'shipping_province' => $row->shipping_province ?? null,
            'shipping_postal_code' => $row->shipping_postal_code ?? null,
            'shipping_country' => $row->shipping_country ?? null,
        ]);
    }

    private function normalizeAddress(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower($value)));
    }

    private function invalidForeignKeyCount(string $table, string $column, string $foreignTable): int
    {
        if (! $this->hasColumn($table, $column) || ! $this->hasTable($foreignTable)) {
            return 0;
        }

        return DB::table($table)
            ->leftJoin($foreignTable, "{$table}.{$column}", '=', "{$foreignTable}.id")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("{$foreignTable}.id")
            ->count();
    }

    private function duplicateValueCount(string $table, string $column): int
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
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

    private function tableCount(string $table): int
    {
        return $this->hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function tablesWithColumn(string $column): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn (string $table): bool => $this->hasColumn($table, $column))
            ->values()
            ->all();
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function hasUniqueIndex(string $table, array $columns): bool
    {
        try {
            return Schema::hasIndex($table, $columns, 'unique');
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        if (! $this->hasColumn($table, $column)) {
            return true;
        }

        try {
            $details = collect(Schema::getColumns($table))
                ->first(fn (array $details): bool => ($details['name'] ?? null) === $column);

            return (bool) ($details['nullable'] ?? true);
        } catch (\Throwable) {
            return true;
        }
    }

    private function stageBCleanupStarted(): bool
    {
        return $this->remainingStageBLegacyColumns() !== $this->allStageBLegacyColumns()
            || ! file_exists(app_path('Models/RepairBooking.php'));
    }

    private function stageBCleanupComplete(): bool
    {
        return $this->remainingStageBLegacyColumns() === []
            && ! file_exists(app_path('Models/RepairBooking.php'));
    }

    private function remainingStageBLegacyColumns(): array
    {
        return collect(self::STAGE_B_LEGACY_COLUMNS)
            ->flatMap(function (array $columns, string $table): array {
                if (! $this->hasTable($table)) {
                    return [];
                }

                return collect($columns)
                    ->filter(fn (string $column): bool => $this->hasColumn($table, $column))
                    ->map(fn (string $column): string => "{$table}.{$column}")
                    ->all();
            })
            ->values()
            ->all();
    }

    private function allStageBLegacyColumns(): array
    {
        return collect(self::STAGE_B_LEGACY_COLUMNS)
            ->flatMap(fn (array $columns, string $table): array => collect($columns)
                ->map(fn (string $column): string => "{$table}.{$column}")
                ->all())
            ->values()
            ->all();
    }

    private function scanLegacyReferences(): array
    {
        $hits = [
            'active' => [],
            'compatibility' => [],
            'migration_history' => [],
            'test_only' => [],
            'documentation' => [],
            'unrelated' => [],
        ];

        foreach (['app', 'routes', 'resources', 'database', 'tests'] as $path) {
            $fullPath = base_path($path);

            if (! is_dir($fullPath)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $relativePath = str_replace(base_path(DIRECTORY_SEPARATOR), '', $file->getPathname());

                if ($relativePath === 'app/Console/Commands/VerifyStep24Migration.php') {
                    continue;
                }

                if (! preg_match('/\.(php|blade\.php)$/', $relativePath)) {
                    continue;
                }

                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];

                foreach ($lines as $lineNumber => $line) {
                    foreach (self::LEGACY_TERMS as $term) {
                        if (! str_contains($line, $term)) {
                            continue;
                        }

                        $bucket = $this->referenceBucket($relativePath, $line, $term);
                        $hits[$bucket][] = [
                            'file' => $relativePath,
                            'line' => $lineNumber + 1,
                            'term' => $term,
                        ];
                    }
                }
            }
        }

        return $hits;
    }

    private function referenceBucket(string $relativePath, string $line, string $term): string
    {
        if ($this->isDocumentationLine($line)) {
            return 'documentation';
        }

        if (str_starts_with($relativePath, 'database/migrations/')) {
            return 'migration_history';
        }

        if (str_starts_with($relativePath, 'tests/')) {
            return 'test_only';
        }

        if (str_starts_with($relativePath, 'database/factories/')
            || str_starts_with($relativePath, 'database/seeders/')) {
            return 'test_only';
        }

        if ($this->isUnrelatedReference($relativePath, $line, $term)) {
            return 'unrelated';
        }

        if (in_array($relativePath, [
            'app/Models/RepairBooking.php',
        ], true)) {
            return 'compatibility';
        }

        return 'active';
    }

    private function isDocumentationLine(string $line): bool
    {
        $trimmed = trim($line);

        return str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '#');
    }

    private function isUnrelatedReference(string $relativePath, string $line, string $term): bool
    {
        if ($term === 'cart_id' && $relativePath === 'app/Models/CartItem.php') {
            return true;
        }

        if ($term !== 'tracking_number') {
            return false;
        }

        if (str_contains($line, 'carrier_tracking_number')
            || str_contains($line, 'delivery_tracking_number')) {
            return true;
        }

        if (in_array($relativePath, [
            'app/Models/Order.php',
            'app/Models/OrderStatusUpdate.php',
            'app/Models/RepairStatusUpdate.php',
        ], true)) {
            return true;
        }

        if (str_contains($relativePath, 'resources/views/')
            && str_contains($line, 'Carrier tracking')) {
            return true;
        }

        if (str_contains($relativePath, 'resources/views/')
            && str_contains($line, '$update->tracking_number')) {
            return true;
        }

        if (str_contains($relativePath, 'resources/views/')
            && str_contains($line, '$order->tracking_number')) {
            return true;
        }

        if (in_array($relativePath, [
            'app/Http/Controllers/Admin/OrderController.php',
            'app/Http/Controllers/Admin/RepairController.php',
            'app/Services/PaymentFinalizer.php',
        ], true)) {
            return true;
        }

        return false;
    }

    private function formatReferenceSamples(array $hits): string
    {
        if ($hits === []) {
            return 'none';
        }

        return collect($hits)
            ->take(8)
            ->map(fn (array $hit): string => "{$hit['file']}:{$hit['line']} ({$hit['term']})")
            ->implode('; ');
    }

    private function addMetric(string $section, string $name, mixed $value, bool $blocksCleanup = false): void
    {
        $this->report[$section][$name] = $value;

        if ($blocksCleanup) {
            $this->blockers[] = "{$section}.{$name}: {$value}";
        }
    }

    private function repairAction(string $message): void
    {
        $prefix = $this->dryRun ? '[dry-run] ' : '';
        $this->repairActions[] = $prefix.$message;
    }

    private function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function renderReport(string $status): void
    {
        $rows = [];

        foreach ($this->report as $section => $checks) {
            foreach ($checks as $name => $value) {
                $rows[] = [$section, $name, $this->stringify($value)];
            }
        }

        $this->newLine();
        $this->line($status);
        $this->newLine();
        $this->table(['Section', 'Check', 'Value'], $rows);

        if ($this->repairActions !== []) {
            $this->newLine();
            $this->line('Repair actions');
            collect($this->repairActions)->each(fn (string $action) => $this->line('- '.$action));
        }

        if ($this->warnings !== []) {
            $this->newLine();
            $this->warn('Warnings');
            collect($this->warnings)->each(fn (string $warning) => $this->line('- '.$warning));
        }

        if ($this->blockers !== []) {
            $this->newLine();
            $this->error('Cleanup blockers');
            collect($this->blockers)->each(fn (string $blocker) => $this->line('- '.$blocker));
        }
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }
}
