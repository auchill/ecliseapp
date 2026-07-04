<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixDeviceSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixDevices extends Command
{
    protected $signature = 'mobilesentrix:sync-devices
        {--limit=30 : MobileSentrix page size}
        {--page=1 : MobileSentrix start page}';

    protected $description = 'Sync MobileSentrix certified pre-owned device products.';

    public function handle(MobileSentrixDeviceSyncService $syncService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $limit = max(1, (int) $this->option('limit'));
        $page = max(1, (int) $this->option('page'));

        $this->info('Starting MobileSentrix devices sync...');

        try {
            $result = $syncService->syncAllDevices($limit, $page, fn (string $message) => $this->line($message));
        } catch (\Throwable $exception) {
            $this->error('MobileSentrix device sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Device sync completed. Total synced: '.($result['total'] ?? $result['processed_count'] ?? 0).'.');
        $this->line('Created: '.($result['created'] ?? $result['created_count'] ?? 0));
        $this->line('Updated: '.($result['updated'] ?? $result['updated_count'] ?? 0));

        if (($result['status'] ?? null) === 'failed') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
