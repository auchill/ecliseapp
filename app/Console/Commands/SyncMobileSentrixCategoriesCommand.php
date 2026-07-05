<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixCategoriesCommand extends Command
{
    protected $aliases = ['mobilesentrix:sync-parts-categories'];

    protected $signature = 'mobilesentrix:sync-categories
        {--category= : Optional MobileSentrix category ID}
        {--depth= : Maximum recursive category depth from 1 to 25}';

    protected $description = 'Sync MobileSentrix parts categories into the local parts catalog.';

    public function handle(MobileSentrixSyncService $syncService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $depth = $this->option('depth') !== null
            ? max(1, min((int) $this->option('depth'), 25))
            : null;

        $result = $syncService->syncCategories($this->option('category'), $depth);

        $this->info($result['message'] ?? 'MobileSentrix category sync finished.');
        $this->line(sprintf(
            'Created: %d, Updated: %d, Skipped: %d, Warnings: %d, Failed: %d',
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $result['warning_count'] ?? 0,
            $result['failed_count'] ?? 0,
        ));

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
