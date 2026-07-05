<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Models\PartCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartCategoryPivotService
{
    public function generate(int $chunkSize = 500): array
    {
        $chunkSize = max(1, min($chunkSize, 2000));
        $log = MobileSentrixSyncLog::query()->create([
            'sync_type' => 'part_category_pivot',
            'status' => 'started',
            'started_at' => now(),
            'message' => 'Generating part category assignments from saved category_ids.',
        ]);
        $summary = [
            'processed_parts_count' => 0,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'invalid_id_count' => 0,
            'missing_category_count' => 0,
            'no_category_ids_count' => 0,
            'warning_count' => 0,
            'failed_count' => 0,
            'warnings' => [],
            'omitted_warning_count' => 0,
        ];

        try {
            $categoryIds = PartCategory::query()
                ->pluck('id')
                ->mapWithKeys(fn ($id): array => [(string) $id => true])
                ->all();

            DB::table('parts')
                ->select(['id', 'category_ids'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($parts) use (&$summary, $categoryIds, $log): void {
                    $partIds = $parts->pluck('id')->all();
                    $existing = DB::table('part_category_part')
                        ->whereIn('part_id', $partIds)
                        ->get(['part_id', 'category_id'])
                        ->groupBy(fn ($row): string => (string) $row->part_id)
                        ->map(fn ($rows): array => $rows->mapWithKeys(
                            fn ($row): array => [(string) $row->category_id => true]
                        )->all());
                    $inserts = [];
                    $now = now();

                    foreach ($parts as $part) {
                        $summary['processed_parts_count']++;

                        try {
                            [$ids, $invalidCount] = $this->parseCategoryIds($part->category_ids);
                            $summary['invalid_id_count'] += $invalidCount;

                            if ($invalidCount > 0) {
                                $summary['warning_count'] += $invalidCount;
                                $this->addWarning($summary, [
                                    'message' => "Part {$part->id} has {$invalidCount} invalid category ID value(s).",
                                    'part_id' => $part->id,
                                ]);
                            }

                            if ($ids === []) {
                                $summary['no_category_ids_count']++;

                                continue;
                            }

                            $existingForPart = $existing->get((string) $part->id, []);

                            foreach ($ids as $categoryId) {
                                if (! isset($categoryIds[$categoryId])) {
                                    $summary['missing_category_count']++;
                                    $summary['warning_count']++;
                                    $this->addWarning($summary, [
                                        'message' => "Part {$part->id} references missing category {$categoryId}.",
                                        'part_id' => $part->id,
                                        'missing_category_id' => $categoryId,
                                    ]);

                                    continue;
                                }

                                if (isset($existingForPart[$categoryId])) {
                                    $summary['existing_count']++;

                                    continue;
                                }

                                $inserts[] = [
                                    'part_id' => $part->id,
                                    'category_id' => (int) $categoryId,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                                $existingForPart[$categoryId] = true;
                            }
                        } catch (\Throwable $exception) {
                            $summary['failed_count']++;
                            $this->addWarning($summary, [
                                'message' => Str::limit($exception->getMessage(), 500, '...'),
                                'part_id' => $part->id,
                            ]);
                        }
                    }

                    foreach (array_chunk($inserts, 1000) as $insertChunk) {
                        $summary['created_count'] += DB::table('part_category_part')->insertOrIgnore($insertChunk);
                    }

                    $summary['skipped_count'] = $summary['existing_count']
                        + $summary['invalid_id_count']
                        + $summary['missing_category_count']
                        + $summary['no_category_ids_count'];
                    $this->updateLog($log, $summary, 'Generating part category assignments.');
                }, 'id');

            return $this->finishLog($log, $summary);
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $this->addWarning($summary, $exception->getMessage());

            return $this->finishLog($log, $summary, true);
        }
    }

    public function parseCategoryIds(mixed $value): array
    {
        $invalidCount = 0;
        $ids = $this->parseValue($value, $invalidCount);

        return [array_values(array_unique($ids)), $invalidCount];
    }

    private function parseValue(mixed $value, int &$invalidCount, int $depth = 0): array
    {
        if ($depth > 5 || $value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            $ids = [];

            foreach ($value as $item) {
                array_push($ids, ...$this->parseValue($item, $invalidCount, $depth + 1));
            }

            return $ids;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $id = ltrim(trim((string) $value), '0');

            if ($id !== '') {
                return [$id];
            }

            $invalidCount++;

            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && $decoded !== $value) {
                return $this->parseValue($decoded, $invalidCount, $depth + 1);
            }

            if (str_contains($value, ',')) {
                $ids = [];

                foreach (explode(',', trim($value, "[] \t\n\r\0\x0B")) as $item) {
                    array_push($ids, ...$this->parseValue(trim($item, " \"'"), $invalidCount, $depth + 1));
                }

                return $ids;
            }
        }

        $invalidCount++;

        return [];
    }

    private function updateLog(MobileSentrixSyncLog $log, array $summary, string $message): void
    {
        $log->update([
            'created_count' => $summary['created_count'],
            'updated_count' => $summary['existing_count'],
            'skipped_count' => $summary['skipped_count'],
            'failed_count' => $summary['failed_count'],
            'warning_count' => $summary['warning_count'],
            'message' => $message,
            'error_details' => $this->warningDetails($summary),
        ]);
    }

    private function finishLog(MobileSentrixSyncLog $log, array $summary, bool $failed = false): array
    {
        $status = $failed ? 'failed' : ($summary['failed_count'] > 0 ? 'partial' : 'success');
        $message = sprintf(
            'Part category assignment generation %s. Created: %d, existing: %d, invalid IDs: %d, missing categories: %d, no category IDs: %d, warnings: %d, failures: %d.',
            $status === 'failed' ? 'failed' : 'completed',
            $summary['created_count'],
            $summary['existing_count'],
            $summary['invalid_id_count'],
            $summary['missing_category_count'],
            $summary['no_category_ids_count'],
            $summary['warning_count'],
            $summary['failed_count'],
        );

        $log->update([
            'status' => $status,
            'finished_at' => now(),
            'created_count' => $summary['created_count'],
            'updated_count' => $summary['existing_count'],
            'skipped_count' => $summary['skipped_count'],
            'failed_count' => $summary['failed_count'],
            'warning_count' => $summary['warning_count'],
            'message' => $message,
            'error_details' => $this->warningDetails($summary),
        ]);

        return array_merge($summary, [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'log_id' => $log->id,
        ]);
    }

    private function addWarning(array &$summary, mixed $warning): void
    {
        if (count($summary['warnings']) >= 100) {
            $summary['omitted_warning_count']++;

            return;
        }

        $summary['warnings'][] = is_string($warning)
            ? ['message' => Str::limit($warning, 500, '...')]
            : $warning;
    }

    private function warningDetails(array $summary): array
    {
        $details = [[
            'summary' => [
                'processed_parts_count' => $summary['processed_parts_count'],
                'existing_count' => $summary['existing_count'],
                'invalid_id_count' => $summary['invalid_id_count'],
                'missing_category_count' => $summary['missing_category_count'],
                'no_category_ids_count' => $summary['no_category_ids_count'],
                'warning_count' => $summary['warning_count'],
            ],
        ], ...$summary['warnings']];

        if ($summary['omitted_warning_count'] > 0) {
            $details[] = [
                'message' => "{$summary['omitted_warning_count']} additional warnings were omitted.",
            ];
        }

        return $details;
    }
}
