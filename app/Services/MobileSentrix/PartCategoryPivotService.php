<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Models\Part;
use App\Support\MobileSentrixCategoryIds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartCategoryPivotService
{
    private const NULL_KEY = '__null__';

    public function generate(int $chunkSize = 500): array
    {
        $chunkSize = max(1, min($chunkSize, 2000));
        $log = MobileSentrixSyncLog::query()->create([
            'sync_type' => 'part_category_pivot',
            'status' => 'started',
            'started_at' => now(),
            'message' => 'Generating part category assignments from saved category_ids.',
        ]);
        $summary = $this->emptySummary();

        try {
            DB::table('parts')
                ->select(['id', 'category_ids'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($parts) use (&$summary, $log): void {
                    $existing = DB::table('part_category_part')
                        ->whereIn('part_id', $parts->pluck('id')->all())
                        ->get(['part_id', 'category_id'])
                        ->groupBy(fn ($row): string => (string) $row->part_id)
                        ->map(fn ($rows): array => $rows->mapWithKeys(
                            fn ($row): array => [$this->categoryKey($row->category_id) => true]
                        )->all());
                    $categoryInserts = [];
                    $placeholderInserts = [];
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

                            $existingForPart = $existing->get((string) $part->id, []);

                            if ($ids === []) {
                                $summary['no_category_ids_count']++;
                                $summary['warning_count']++;
                                $this->addWarning($summary, [
                                    'message' => "Part {$part->id} has no usable category_ids; a null pivot placeholder was used.",
                                    'part_id' => $part->id,
                                ]);

                                if (isset($existingForPart[self::NULL_KEY])) {
                                    $summary['null_placeholder_existing_count']++;
                                } else {
                                    $placeholderInserts[] = $this->pivotRow($part->id, null, $now);
                                    $existingForPart[self::NULL_KEY] = true;
                                }

                                continue;
                            }

                            if (isset($existingForPart[self::NULL_KEY])) {
                                DB::table('part_category_part')
                                    ->where('part_id', $part->id)
                                    ->whereNull('category_id')
                                    ->delete();
                                unset($existingForPart[self::NULL_KEY]);
                            }

                            foreach ($ids as $categoryId) {
                                if (isset($existingForPart[$categoryId])) {
                                    $summary['existing_count']++;

                                    continue;
                                }

                                $categoryInserts[] = $this->pivotRow($part->id, (int) $categoryId, $now);
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

                    foreach (array_chunk($categoryInserts, 1000) as $insertChunk) {
                        $created = DB::table('part_category_part')->insertOrIgnore($insertChunk);
                        $summary['created_count'] += $created;
                        $summary['category_pivot_created_count'] += $created;
                    }

                    foreach (array_chunk($placeholderInserts, 1000) as $insertChunk) {
                        $created = DB::table('part_category_part')->insertOrIgnore($insertChunk);
                        $summary['created_count'] += $created;
                        $summary['null_placeholder_created_count'] += $created;
                    }

                    $summary['skipped_count'] = $summary['existing_count']
                        + $summary['null_placeholder_existing_count']
                        + $summary['invalid_id_count'];
                    $this->updateLog($log, $summary, 'Generating part category assignments.');
                }, 'id');

            return $this->finishLog($log, $summary);
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $this->addWarning($summary, $exception->getMessage());

            return $this->finishLog($log, $summary, true);
        }
    }

    public function syncPart(Part $part): array
    {
        [$ids, $invalidCount] = $this->parseCategoryIds($part->category_ids);
        $now = now();
        $summary = [
            'created_count' => 0,
            'existing_count' => 0,
            'invalid_id_count' => $invalidCount,
            'no_category_ids_count' => 0,
            'failed_count' => 0,
        ];

        if ($ids === []) {
            $summary['no_category_ids_count']++;

            if (! DB::table('part_category_part')->where('part_id', $part->id)->whereNull('category_id')->exists()) {
                DB::table('part_category_part')->insertOrIgnore($this->pivotRow($part->id, null, $now));
                $summary['created_count']++;
            } else {
                $summary['existing_count']++;
            }

            return $summary;
        }

        DB::table('part_category_part')->where('part_id', $part->id)->whereNull('category_id')->delete();

        $existing = DB::table('part_category_part')
            ->where('part_id', $part->id)
            ->whereIn('category_id', array_map('intval', $ids))
            ->pluck('category_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $existing = array_fill_keys($existing, true);

        $rows = collect($ids)
            ->reject(fn (string $categoryId): bool => isset($existing[$categoryId]))
            ->map(fn (string $categoryId): array => $this->pivotRow($part->id, (int) $categoryId, $now))
            ->all();

        foreach (array_chunk($rows, 1000) as $chunk) {
            $summary['created_count'] += DB::table('part_category_part')->insertOrIgnore($chunk);
        }

        $summary['existing_count'] += count($ids) - $summary['created_count'];

        return $summary;
    }

    public function parseCategoryIds(mixed $value): array
    {
        return MobileSentrixCategoryIds::parse($value);
    }

    private function categoryKey(mixed $categoryId): string
    {
        return $categoryId === null ? self::NULL_KEY : (string) $categoryId;
    }

    private function pivotRow(int|string $partId, ?int $categoryId, mixed $now): array
    {
        return [
            'part_id' => $partId,
            'category_id' => $categoryId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'processed_parts_count' => 0,
            'created_count' => 0,
            'category_pivot_created_count' => 0,
            'existing_count' => 0,
            'null_placeholder_created_count' => 0,
            'null_placeholder_existing_count' => 0,
            'skipped_count' => 0,
            'invalid_id_count' => 0,
            'no_category_ids_count' => 0,
            'warning_count' => 0,
            'failed_count' => 0,
            'warnings' => [],
            'omitted_warning_count' => 0,
        ];
    }

    private function updateLog(MobileSentrixSyncLog $log, array $summary, string $message): void
    {
        $log->update([
            'created_count' => $summary['created_count'],
            'updated_count' => $summary['existing_count'] + $summary['null_placeholder_existing_count'],
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
            'Part category assignment generation %s. Category pivots created: %d, existing: %d, null placeholders created: %d, null placeholders existing: %d, invalid IDs: %d, no category IDs: %d, warnings: %d, failures: %d.',
            $status === 'failed' ? 'failed' : 'completed',
            $summary['category_pivot_created_count'],
            $summary['existing_count'],
            $summary['null_placeholder_created_count'],
            $summary['null_placeholder_existing_count'],
            $summary['invalid_id_count'],
            $summary['no_category_ids_count'],
            $summary['warning_count'],
            $summary['failed_count'],
        );

        $this->updateLog($log, $summary, $message);
        $log->update([
            'status' => $status,
            'finished_at' => now(),
        ]);

        return array_merge($summary, [
            'updated_count' => $summary['existing_count'] + $summary['null_placeholder_existing_count'],
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
            'summary' => collect($summary)->except(['warnings', 'omitted_warning_count'])->all(),
        ], ...$summary['warnings']];

        if ($summary['omitted_warning_count'] > 0) {
            $details[] = [
                'message' => "{$summary['omitted_warning_count']} additional warnings were omitted.",
            ];
        }

        return $details;
    }
}
