<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixPartsFullSyncService;
use Illuminate\Console\Command;

class SyncMobileSentrixPartsFullCommand extends Command
{
    protected $signature = 'mobilesentrix:sync-parts-full
        {--limit= : Maximum number of products to process}
        {--depth= : Maximum recursive category depth from 1 to 25}
        {--force : Force part updates even when local data appears current}';

    protected $description = 'Run category sync, parts-only sync, and category assignment generation in sequence.';

    public function handle(MobileSentrixPartsFullSyncService $fullSyncService): int
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;
        $depth = $this->option('depth') !== null
            ? max(1, min((int) $this->option('depth'), 25))
            : null;
        $result = $fullSyncService->sync($limit, $depth, (bool) $this->option('force'));

        $this->info($result['message']);

        foreach ($result['stages'] ?? [] as $stage => $stageResult) {
            $this->line(sprintf(
                '%s: %s (created %d, updated %d, skipped %d, warnings %d, failed %d)',
                ucfirst($stage),
                $stageResult['status'] ?? 'unknown',
                $stageResult['created_count'] ?? 0,
                $stageResult['updated_count'] ?? 0,
                $stageResult['skipped_count'] ?? 0,
                $stageResult['warning_count'] ?? 0,
                $stageResult['failed_count'] ?? 0,
            ));
        }

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
