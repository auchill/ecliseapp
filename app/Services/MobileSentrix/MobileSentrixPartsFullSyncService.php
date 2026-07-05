<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixSyncLog;

class MobileSentrixPartsFullSyncService
{
    public function __construct(
        private readonly MobileSentrixSyncService $syncService,
        private readonly PartCategoryPivotService $pivotService,
    ) {}

    public function sync(?int $limit = null, ?int $categoryDepth = null, bool $force = false): array
    {
        $log = MobileSentrixSyncLog::query()->create([
            'sync_type' => 'parts_full_process',
            'status' => 'started',
            'started_at' => now(),
            'message' => 'Starting complete MobileSentrix parts sync.',
        ]);
        $results = [];

        try {
            $results['categories'] = $this->syncService->syncCategories(null, $categoryDepth);
            $log->update(['message' => 'Category sync completed. Syncing parts without category lookups.']);

            $results['parts'] = $this->syncService->syncParts(null, [], [
                'limit' => $limit,
                'force' => $force,
            ]);
            $log->update(['message' => 'Parts sync completed. Generating category assignments.']);

            $results['pivot'] = $this->pivotService->generate();

            return $this->finish($log, $results);
        } catch (\Throwable $exception) {
            return $this->finish($log, $results, $exception);
        }
    }

    private function finish(
        MobileSentrixSyncLog $log,
        array $results,
        ?\Throwable $exception = null,
    ): array {
        $statuses = collect($results)->pluck('status');
        $status = $exception
            ? 'failed'
            : ($statuses->contains('failed') || $statuses->contains('partial') ? 'partial' : 'success');
        $message = $status === 'success'
            ? 'Complete MobileSentrix parts sync finished successfully.'
            : 'Complete MobileSentrix parts sync finished with warnings or failures. Review the stage logs.';
        $details = collect($results)
            ->map(fn (array $result, string $stage): array => array_merge([
                'stage' => $stage,
                'status' => $result['status'] ?? 'unknown',
                'message' => $result['message'] ?? null,
                'log_id' => $result['log_id'] ?? null,
                'created_count' => $result['created_count'] ?? 0,
                'updated_count' => $result['updated_count'] ?? 0,
                'skipped_count' => $result['skipped_count'] ?? 0,
                'warning_count' => $result['warning_count'] ?? 0,
                'failed_count' => $result['failed_count'] ?? 0,
            ], collect($result)->only([
                'detail_lookup_count',
                'detail_updated_count',
                'description_updated_count',
                'category_ids_updated_count',
                'detail_lookup_failed_count',
                'empty_category_ids_count',
                'category_ids_remained_missing_count',
                'category_pivot_created_count',
                'existing_count',
                'null_placeholder_created_count',
                'null_placeholder_existing_count',
                'invalid_id_count',
                'no_category_ids_count',
            ])->all()))
            ->values()
            ->all();

        if ($exception) {
            $details[] = [
                'stage' => 'orchestration',
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        $log->update([
            'status' => $status,
            'finished_at' => now(),
            'created_count' => collect($results)->sum('created_count'),
            'updated_count' => collect($results)->sum('updated_count'),
            'skipped_count' => collect($results)->sum('skipped_count'),
            'failed_count' => collect($results)->sum('failed_count') + ($exception ? 1 : 0),
            'warning_count' => collect($results)->sum('warning_count'),
            'message' => $message,
            'error_details' => $details,
        ]);

        return [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'log_id' => $log->id,
            'stages' => $results,
        ];
    }
}
