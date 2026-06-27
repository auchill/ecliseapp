<?php

namespace App\Jobs\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMobileSentrixCategoriesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly ?string $categoryId = null,
        public readonly ?int $depth = null,
    ) {}

    public function handle(MobileSentrixSyncService $syncService): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $syncService->syncCategories($this->categoryId, $this->depth);
    }

    public function failed(?Throwable $exception): void
    {
        MobileSentrixSyncLog::query()->create([
            'sync_type' => 'categories',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'failed_count' => 1,
            'message' => 'Queued MobileSentrix category sync failed before completion.',
            'error_details' => [
                [
                    'message' => $exception?->getMessage() ?? 'Unknown queued job failure.',
                    'category_id' => $this->categoryId,
                ],
            ],
        ]);
    }
}
