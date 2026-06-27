<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixPartsCommand extends Command
{
    protected $signature = 'mobilesentrix:sync-parts {--category= : Optional MobileSentrix category ID}';

    protected $description = 'Sync MobileSentrix products into the local parts catalog.';

    public function handle(MobileSentrixSyncService $syncService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $result = $syncService->syncParts($this->option('category'));

        $this->info($result['message'] ?? 'MobileSentrix parts sync finished.');
        $this->line(sprintf(
            'Created: %d, Updated: %d, Skipped: %d, Failed: %d, Price changes: %d, Stock changes: %d',
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $result['failed_count'] ?? 0,
            $result['price_changed_count'] ?? 0,
            $result['stock_changed_count'] ?? 0,
        ));

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
