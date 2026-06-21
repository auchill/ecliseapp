<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixCategoriesCommand extends Command
{
    protected $signature = 'mobilesentrix:sync-categories {--category= : Optional MobileSentrix category ID}';

    protected $description = 'Sync MobileSentrix parts categories into the local parts catalog.';

    public function handle(MobileSentrixSyncService $syncService): int
    {
        $result = $syncService->syncCategories($this->option('category'));

        $this->info($result['message'] ?? 'MobileSentrix category sync finished.');
        $this->line(sprintf(
            'Created: %d, Updated: %d, Skipped: %d, Failed: %d',
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $result['failed_count'] ?? 0,
        ));

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
