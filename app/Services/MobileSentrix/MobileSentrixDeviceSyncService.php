<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixDevice;
use App\Models\MobileSentrixSyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileSentrixDeviceSyncService
{
    public function __construct(private readonly MobileSentrixClient $client) {}

    public function syncAllDevices(int $limit = 30, int $startPage = 1, ?callable $progress = null): array
    {
        $pageSize = max(1, min($limit, 500));
        $page = max(1, $startPage);
        $log = $this->startLog("Syncing MobileSentrix certified pre-owned devices from page {$page}.");
        $summary = $this->emptySummary();
        $seenPageSignatures = [];

        try {
            do {
                $this->report($log, $summary, "Fetching devices page {$page} with limit {$pageSize}.", $progress);
                $pageResult = $this->syncPage($pageSize, $page);

                $summary['created'] += $pageResult['created'];
                $summary['updated'] += $pageResult['updated'];
                $summary['total'] += $pageResult['total'];
                $summary['pages']++;
                $summary['errors'] = array_merge($summary['errors'], $pageResult['errors']);
                $this->limitErrors($summary);

                if ($pageResult['total'] === 0) {
                    $this->report($log, $summary, 'No more device records found.', $progress);
                    break;
                }

                if (isset($seenPageSignatures[$pageResult['signature']])) {
                    $summary['errors'][] = [
                        'message' => "Stopped MobileSentrix device sync because page {$page} repeated a previous page.",
                        'page' => $page,
                    ];
                    $this->report($log, $summary, "Stopped MobileSentrix device sync because page {$page} repeated a previous page.", $progress);
                    break;
                }

                $seenPageSignatures[$pageResult['signature']] = true;
                $this->report($log, $summary, "Synced {$pageResult['total']} devices from page {$page}.", $progress);
                $hasNextPage = $pageResult['has_next_page'];
                $page++;
            } while ($hasNextPage);

            return $this->finishLog($log, $summary, 'MobileSentrix devices sync completed.');
        } catch (\Throwable $exception) {
            $summary['errors'][] = $exception->getMessage();

            return $this->finishLog($log, $summary, 'MobileSentrix devices sync failed.', true);
        }
    }

    public function syncPage(int $limit = 30, int $page = 1): array
    {
        $limit = max(1, min($limit, 500));
        $page = max(1, $page);
        $payload = $this->client->products([
            'limit' => $limit,
            'page' => $page,
            'pageinfo' => 1,
            'product_type' => 'devicesystem',
        ]);

        $records = $this->records($payload);
        $summary = [
            'created' => 0,
            'updated' => 0,
            'total' => 0,
            'pages' => 1,
            'errors' => [],
            'has_next_page' => $this->hasNextPage($payload, $page, $records->count(), $limit),
            'signature' => $this->pageSignature($records),
        ];

        foreach ($records as $record) {
            try {
                $result = $this->upsertDevice($record);

                if (! $result) {
                    $summary['errors'][] = [
                        'message' => 'Skipped malformed MobileSentrix device record.',
                        'entity_id' => $record['entity_id'] ?? null,
                        'sku' => $record['sku'] ?? null,
                    ];

                    continue;
                }

                $summary[$result['created'] ? 'created' : 'updated']++;
                $summary['total']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = $this->safeError($exception, $record);
            }
        }

        return $summary;
    }

    private function upsertDevice(array $record): ?array
    {
        $entityId = $this->intValue($record, 'entity_id')
            ?? $this->intValue($record, 'product_id')
            ?? $this->intValue($record, 'id');
        $sku = $this->limitString($this->textValue($record, 'sku') ?: $this->textValue($record, 'product_code'));

        if (! $entityId && ! $sku) {
            return null;
        }

        return DB::transaction(function () use ($record, $entityId, $sku): array {
            $device = $entityId
                ? MobileSentrixDevice::query()->where('entity_id', $entityId)->first()
                : null;

            if (! $device && $sku) {
                $device = MobileSentrixDevice::query()->where('sku', $sku)->first();
            }

            $exists = (bool) $device;
            $device ??= new MobileSentrixDevice;

            $manufacturerText = $this->limitString($this->textValue($record, 'manufacturer_text') ?: $this->textValue($record, 'device_manufacturer_text') ?: $this->textValue($record, 'brand_text'));
            $modelText = $this->modelTextFromRecord($record);
            $colorText = $this->limitString($this->textValue($record, 'device_color_text') ?: $this->textValue($record, 'color_text'));
            $conditionText = $this->limitString($this->textValue($record, 'condition_text') ?: $this->textValue($record, 'device_grade_text') ?: $this->textValue($record, 'condition'));
            $carrierText = $this->limitString($this->textValue($record, 'device_carrier_text') ?: $this->textValue($record, 'carrier_text'));
            $sizeText = $this->limitString($this->textValue($record, 'device_size_text') ?: $this->textValue($record, 'size_text'));
            $gradeText = $this->limitString($this->textValue($record, 'device_grade_text'));

            $device->fill([
                'entity_id' => $entityId,
                'sku' => $sku,
                'name' => $this->limitString($this->textValue($record, 'name') ?: $this->textValue($record, 'title'), 512),
                'url' => $this->textValue($record, 'url') ?: $this->textValue($record, 'link') ?: $this->textValue($record, 'buy_now_url'),
                'url_key' => $this->limitString($this->textValue($record, 'url_key')),
                'manufacturer_text' => $manufacturerText,
                'device_model_text' => $modelText,
                'device_color_text' => $colorText,
                'condition_text' => $conditionText,
                'device_carrier_text' => $carrierText,
                'device_size_text' => $sizeText,
                'device_grade_text' => $gradeText,
                'available_qty' => $this->intValue($record, 'available_qty') ?? $this->intValue($record, 'in_stock_qty'),
                'qty' => $this->intValue($record, 'qty') ?? $this->intValue($record, 'quantity') ?? $this->intValue($record, 'in_stock_qty'),
                'price' => $this->decimalValue($record, 'price') ?? $this->decimalValue($record, 'customer_price'),
                'regular_price' => $this->decimalValue($record, 'regular_price') ?? $this->decimalValue($record, 'regular_price_without_tax') ?? $this->decimalValue($record, 'regular_price_with_tax'),
                'final_price' => $this->decimalValue($record, 'final_price') ?? $this->decimalValue($record, 'final_price_without_tax') ?? $this->decimalValue($record, 'final_price_with_tax') ?? $this->decimalValue($record, 'customer_price'),
                'cost' => $this->decimalValue($record, 'cost') ?? $this->decimalValue($record, 'price'),
                'status' => $this->apiStatus($record),
                'product_type' => $this->limitString($this->textValue($record, 'product_type') ?: 'devicesystem'),
                'image_url' => $this->textValue($record, 'image_url') ?: $this->textValue($record, 'default_image') ?: $this->textValue($record, 'image_link'),
                'raw_payload' => $record,
                'synced_at' => now(),
            ]);
            $device->save();

            return ['device' => $device, 'created' => ! $exists];
        });
    }

    private function records(array $payload): Collection
    {
        foreach ([
            data_get($payload, 'data.items'),
            data_get($payload, 'items'),
            data_get($payload, 'data.products'),
            data_get($payload, 'products'),
        ] as $candidate) {
            if (is_array($candidate) && ! $this->looksLikeRecord($candidate)) {
                return collect($candidate)->filter(fn ($value): bool => is_array($value))->values();
            }
        }

        if (isset($payload['data']) && is_array($payload['data']) && ! $this->looksLikeRecord($payload['data'])) {
            return collect($payload['data'])
                ->filter(fn ($value, $key): bool => ! in_array($key, ['page_info', 'pagination'], true) && is_array($value))
                ->values();
        }

        if ($this->looksLikeRecord($payload)) {
            return collect([$payload]);
        }

        return collect($payload)
            ->filter(fn ($value, $key): bool => ! in_array($key, ['page_info', 'pagination', 'meta'], true) && is_array($value))
            ->values();
    }

    private function looksLikeRecord(array $payload): bool
    {
        return isset($payload['entity_id'])
            || isset($payload['product_id'])
            || isset($payload['sku'])
            || isset($payload['new_sku'])
            || isset($payload['name']);
    }

    private function hasNextPage(array $payload, int $page, int $recordCount, int $pageSize): bool
    {
        $pageInfo = $this->pageInfo($payload);

        if ($pageInfo) {
            $currentPage = (int) ($pageInfo['current_page'] ?? $pageInfo['page'] ?? $page);
            $totalPages = (int) ($pageInfo['total_pages'] ?? $pageInfo['last_page'] ?? 0);
            $totalCount = (int) ($pageInfo['total_count'] ?? $pageInfo['total'] ?? 0);
            $reportedPageSize = (int) ($pageInfo['page_size'] ?? $pageInfo['per_page'] ?? $pageSize);

            if ($totalPages > 0) {
                return $currentPage < $totalPages;
            }

            if ($totalCount > 0 && $reportedPageSize > 0) {
                return $currentPage * $reportedPageSize < $totalCount;
            }
        }

        return $recordCount === $pageSize;
    }

    private function pageInfo(array $payload): ?array
    {
        foreach ([
            data_get($payload, 'data.page_info'),
            data_get($payload, 'page_info'),
            data_get($payload, 'data.pagination'),
            data_get($payload, 'pagination'),
            data_get($payload, 'meta.pagination'),
            data_get($payload, 'meta'),
        ] as $candidate) {
            if (is_array($candidate) && count(array_intersect(array_keys($candidate), ['current_page', 'page', 'total_pages', 'last_page', 'total_count', 'total'])) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function pageSignature(Collection $records): string
    {
        return $records
            ->take(5)
            ->map(fn (array $record): string => implode(':', array_filter([
                $record['entity_id'] ?? $record['product_id'] ?? $record['id'] ?? null,
                $record['sku'] ?? null,
            ])))
            ->filter()
            ->implode('|') ?: sha1(json_encode($records->take(5)->values()->all()) ?: '');
    }

    private function startLog(string $message): MobileSentrixSyncLog
    {
        return MobileSentrixSyncLog::query()->create([
            'sync_type' => 'devices',
            'status' => 'started',
            'started_at' => now(),
            'message' => $message,
        ]);
    }

    private function report(MobileSentrixSyncLog $log, array $summary, string $message, ?callable $progress = null): void
    {
        $progress?->__invoke($message);

        $log->update([
            'created_count' => $summary['created'] ?? 0,
            'updated_count' => $summary['updated'] ?? 0,
            'skipped_count' => 0,
            'failed_count' => count($summary['errors'] ?? []),
            'message' => $message,
            'error_details' => $this->logErrors($summary),
        ]);

        Log::info('MobileSentrix device sync progress.', [
            'sync_log_id' => $log->id,
            'message' => $message,
            'created' => $summary['created'] ?? 0,
            'updated' => $summary['updated'] ?? 0,
            'total' => $summary['total'] ?? 0,
            'errors' => count($summary['errors'] ?? []),
        ]);
    }

    private function finishLog(MobileSentrixSyncLog $log, array $summary, string $message, bool $failed = false): array
    {
        $status = $failed
            ? 'failed'
            : (count($summary['errors'] ?? []) > 0 ? 'partial' : 'success');

        $log->update([
            'status' => $status,
            'finished_at' => now(),
            'created_count' => $summary['created'] ?? 0,
            'updated_count' => $summary['updated'] ?? 0,
            'skipped_count' => 0,
            'failed_count' => count($summary['errors'] ?? []),
            'message' => $message,
            'error_details' => $this->logErrors($summary),
        ]);

        return array_merge($summary, [
            'created_count' => $summary['created'] ?? 0,
            'updated_count' => $summary['updated'] ?? 0,
            'processed_count' => $summary['total'] ?? 0,
            'page_count' => $summary['pages'] ?? 0,
            'failed_count' => count($summary['errors'] ?? []),
            'status' => $status,
            'success' => $status === 'success',
            'message' => $message,
            'log_id' => $log->id,
        ]);
    }

    private function emptySummary(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'total' => 0,
            'pages' => 0,
            'errors' => [],
            'omitted_error_count' => 0,
        ];
    }

    private function limitErrors(array &$summary): void
    {
        if (count($summary['errors']) <= 100) {
            return;
        }

        $summary['omitted_error_count'] += count($summary['errors']) - 100;
        $summary['errors'] = array_slice($summary['errors'], 0, 100);
    }

    private function logErrors(array $summary): array
    {
        $errors = $summary['errors'] ?? [];

        if (($summary['omitted_error_count'] ?? 0) > 0) {
            $errors[] = ['message' => $summary['omitted_error_count'].' additional sync errors were omitted from this log.'];
        }

        return $errors;
    }

    private function safeError(\Throwable $exception, array $record): array
    {
        return [
            'exception' => $exception::class,
            'message' => Str::limit($exception->getMessage(), 500, '...'),
            'entity_id' => $record['entity_id'] ?? $record['product_id'] ?? $record['id'] ?? null,
            'product_id' => $record['product_id'] ?? $record['id'] ?? null,
            'sku' => $record['sku'] ?? $record['product_code'] ?? $record['new_sku'] ?? null,
            'name' => Str::limit((string) ($record['name'] ?? $record['title'] ?? ''), 160, ''),
        ];
    }

    private function modelTextFromRecord(array $record): ?string
    {
        foreach ([
            'device_model_text',
            'model_text',
            'device_name_text',
            'device_model',
            'model',
            'model_name',
            'product_model',
            'product_model_text',
        ] as $key) {
            $value = $this->limitString($this->textValue($record, $key));

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function textValue(array $record, string $key): ?string
    {
        if (! array_key_exists($key, $record)) {
            return null;
        }

        $value = $record[$key];

        if (is_bool($value)) {
            return $value ? '1' : null;
        }

        if (is_array($value)) {
            $value = collect($value)
                ->flatten()
                ->filter(fn ($item): bool => is_scalar($item) && filled($item))
                ->implode(', ');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function limitString(?string $value, int $maxLength = 255): ?string
    {
        return filled($value) ? Str::limit($value, $maxLength, '') : null;
    }

    private function decimalValue(array $record, string $key): ?float
    {
        $value = $record[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function intValue(array $record, string $key): ?int
    {
        $value = $record[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function apiStatus(array $record): string
    {
        $status = $record['status'] ?? null;

        if ($status === null || $status === '') {
            return 'active';
        }

        if (in_array($status, [true, 1, '1', 'Enabled', 'enabled', 'active', 'Active'], true)) {
            return 'active';
        }

        return 'inactive';
    }
}
