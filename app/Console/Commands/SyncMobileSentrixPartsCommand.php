<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixPartsCommand extends Command
{
    protected $signature = 'mobilesentrix:sync-parts
        {--category= : Optional MobileSentrix category ID}
        {--debug-category= : Optional category ID to report end-to-end sync/debug counts for}
        {--limit= : Maximum number of products to process}
        {--dry-run : Fetch and analyze products without saving changes}
        {--force : Force updates even when local data appears current}';

    protected $description = 'Sync MobileSentrix products into the local parts catalog.';

    public function handle(MobileSentrixSyncService $syncService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;

        $result = $syncService->syncParts($this->option('category'), [], [
            'limit' => $limit,
            'dry_run' => (bool) $this->option('dry-run'),
            'force' => (bool) $this->option('force'),
            'debug_category' => $this->option('debug-category'),
        ]);

        $this->info($result['message'] ?? 'MobileSentrix parts sync finished.');
        if ($this->option('dry-run')) {
            $this->warn('Dry run only. No parts or category assignments were saved.');
        }
        $this->line(sprintf(
            'Processed: %d, Created: %d, Updated: %d, Skipped: %d, Warnings: %d, Failed: %d, Price changes: %d, Stock changes: %d',
            $result['processed_count'] ?? 0,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $result['warning_count'] ?? 0,
            $result['failed_count'] ?? 0,
            $result['price_changed_count'] ?? 0,
            $result['stock_changed_count'] ?? 0,
        ));
        $this->line(sprintf(
            'Detail lookups: %d, records enriched: %d, descriptions updated: %d, category IDs updated: %d, detail failures: %d, category IDs still missing: %d',
            $result['detail_lookup_count'] ?? 0,
            $result['detail_updated_count'] ?? 0,
            $result['description_updated_count'] ?? 0,
            $result['category_ids_updated_count'] ?? 0,
            $result['detail_lookup_failed_count'] ?? 0,
            $result['category_ids_remained_missing_count'] ?? 0,
        ));
        $this->line(sprintf(
            'Category IDs: with %d, missing %d. Pivot rows: created %d, existing %d, skipped empty category IDs %d, failed %d',
            $result['with_category_ids_count'] ?? 0,
            $result['missing_category_ids_count'] ?? 0,
            $result['pivot_rows_created'] ?? 0,
            $result['pivot_rows_existing'] ?? 0,
            $result['pivot_rows_skipped_empty_category_ids'] ?? 0,
            $result['pivot_rows_failed'] ?? 0,
        ));

        if ($this->option('debug-category')) {
            $this->line(sprintf(
                'Debug category %s: API records containing category %d, parts table records containing category %d, pivot rows %d, listing query count %d',
                $this->option('debug-category'),
                $result['debug_api_records_containing_category'] ?? 0,
                $result['debug_parts_table_records_containing_category'] ?? 0,
                $result['debug_pivot_rows_for_category'] ?? 0,
                $result['debug_listing_query_count'] ?? 0,
            ));
        }

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
