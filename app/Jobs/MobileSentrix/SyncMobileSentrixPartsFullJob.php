<?php

namespace App\Jobs\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Services\MobileSentrix\MobileSentrixPartsFullSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMobileSentrixPartsFullJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?int $categoryDepth = null,
        public readonly bool $force = false,
    ) {}

    public function handle(MobileSentrixPartsFullSyncService $fullSyncService): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $fullSyncService->sync($this->limit, $this->categoryDepth, $this->force);
    }

    public function failed(?Throwable $exception): void
    {
        MobileSentrixSyncLog::query()->create([
            'sync_type' => 'parts_full_process',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'failed_count' => 1,
            'message' => 'Queued complete MobileSentrix parts sync failed before completion.',
            'error_details' => [[
                'message' => $exception?->getMessage() ?? 'Unknown queued job failure.',
            ]],
        ]);
    }
}
