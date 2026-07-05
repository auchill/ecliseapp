<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixPartsCommand extends Command
{
    protected $signature = 'mobilesentrix:sync-parts
        {--category= : Optional MobileSentrix category ID}
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

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
