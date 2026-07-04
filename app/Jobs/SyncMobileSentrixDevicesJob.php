<?php

namespace App\Jobs;

use App\Models\MobileSentrixSyncLog;
use App\Services\MobileSentrix\MobileSentrixDeviceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMobileSentrixDevicesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $limit = 30,
        public readonly int $startPage = 1,
    ) {}

    public function handle(MobileSentrixDeviceSyncService $syncService): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $syncService->syncAllDevices($this->limit, $this->startPage);
    }

    public function failed(?Throwable $exception): void
    {
        MobileSentrixSyncLog::query()->create([
            'sync_type' => 'devices',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'failed_count' => 1,
            'message' => 'Queued MobileSentrix devices sync failed before completion.',
            'error_details' => [
                [
                    'message' => $exception?->getMessage() ?? 'Unknown queued job failure.',
                    'limit' => $this->limit,
                    'start_page' => $this->startPage,
                ],
            ],
        ]);
    }
}
