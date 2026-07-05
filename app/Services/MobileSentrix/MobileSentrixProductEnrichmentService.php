<?php

namespace App\Services\MobileSentrix;

use App\Models\Part;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileSentrixProductEnrichmentService
{
    public function __construct(
        private readonly MobileSentrixClient $client,
        private readonly MobileSentrixSyncService $syncService,
    ) {}

    public function enrichPart(Part $part, bool $force = false): Part
    {
        if (! $force && ! $this->shouldEnrich($part)) {
            return $part;
        }

        $summary = $this->emptySummary();
        $productRecord = $this->fetchProductRecord($part);
        $remoteFetchSucceeded = $productRecord !== [];

        if ($productRecord !== []) {
            $syncedPart = $this->syncService->upsertPart($productRecord, $summary);
            $part = $syncedPart ?: $part->fresh() ?: $part;
            $this->syncRelatedProducts($part, $productRecord, $summary);
        }

        if ($this->syncTagsAndCompatibility($part)) {
            $remoteFetchSucceeded = true;
        }

        if ($remoteFetchSucceeded) {
            $part->forceFill(['last_enriched_at' => now()])->save();
        }

        return $part->fresh() ?: $part;
    }

    public function enrichPartBySku(string $sku, bool $force = false): ?Part
    {
        $part = Part::query()
            ->where('sku', $sku)
            ->orWhere('new_sku', $sku)
            ->first();

        if (! $part) {
            $summary = $this->emptySummary();
            $records = $this->records($this->client->lookupBySku($sku));
            $record = $records->first();

            if (! is_array($record)) {
                return null;
            }

            $part = $this->syncService->upsertPart($record, $summary);
        }

        return $part ? $this->enrichPart($part, $force) : null;
    }

    public function shouldEnrich(Part $part): bool
    {
        if (! $part->is_api_item) {
            return false;
        }

        if (! $part->last_enriched_at) {
            return true;
        }

        $ttlHours = max(1, (int) config('mobilesentrix.product_enrichment_ttl_hours', 12));

        return $part->last_enriched_at->lte(now()->subHours($ttlHours));
    }

    private function fetchProductRecord(Part $part): array
    {
        $record = [];

        foreach ([
            'product_detail' => [],
            'product_gallery' => ['load' => 'image_gallery'],
            'product_related' => ['load' => 'related_product'],
            'product_gallery_related' => ['load' => 'image_gallery,related_product'],
        ] as $stage => $query) {
            try {
                $loadedRecord = $this->records($this->client->product($part->getKey(), $query))->first();

                if (is_array($loadedRecord)) {
                    $record = $this->mergeProductRecords($record, $loadedRecord);
                }
            } catch (\Throwable $exception) {
                $this->logEnrichmentFailure($exception, $part, $stage);
            }
        }

        return $record;
    }

    private function mergeProductRecords(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value) && $value === [] && ! empty($base[$key])) {
                continue;
            }

            if (($value === null || $value === '') && filled($base[$key] ?? null)) {
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function syncTagsAndCompatibility(Part $part): bool
    {
        $skus = $part->sourceSkus();

        if ($skus === []) {
            return false;
        }

        try {
            $payload = $this->client->tagsForSkus($skus);
        } catch (\Throwable $exception) {
            $this->logEnrichmentFailure($exception, $part, 'tags');

            return false;
        }

        $compatibility = $this->records($payload)
            ->flatMap(fn (array $record): array => $this->compatibilityRows($record['compatibility'] ?? null)->all())
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $part->forceFill([
            'tags_raw_payload' => $payload,
            'compatibility' => $compatibility,
        ])->save();

        return true;
    }

    private function syncRelatedProducts(Part $part, array $record, array &$summary): void
    {
        $related = $record['related_product'] ?? null;

        foreach ($this->relatedRecords($related) as $relatedRecord) {
            $this->syncService->upsertPart($relatedRecord, $summary);
        }

        $relatedIds = collect($this->relatedIds($related))
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $part->id)
            ->unique()
            ->take(12)
            ->values();

        foreach ($relatedIds as $relatedId) {
            if (Part::query()->whereKey($relatedId)->exists()) {
                continue;
            }

            try {
                $relatedRecord = $this->records($this->client->product($relatedId, ['load' => 'image_gallery']))->first();

                if (is_array($relatedRecord)) {
                    $this->syncService->upsertPart($relatedRecord, $summary);
                }
            } catch (\Throwable $exception) {
                $this->logEnrichmentFailure($exception, $part, 'related_product', ['related_part_id' => $relatedId]);
            }
        }

    }

    private function records(array $payload): Collection
    {
        if (isset($payload['data']['items']) && is_array($payload['data']['items'])) {
            return collect($payload['data']['items']);
        }

        if (isset($payload['data']) && is_array($payload['data']) && ! $this->looksLikeRecord($payload['data'])) {
            return collect($payload['data'])
                ->filter(fn ($value, $key): bool => $key !== 'page_info' && is_array($value))
                ->values();
        }

        if (isset($payload['data']) && is_array($payload['data']) && $this->looksLikeRecord($payload['data'])) {
            return collect([$payload['data']]);
        }

        if ($this->looksLikeRecord($payload)) {
            return collect([$payload]);
        }

        return collect($payload)
            ->filter(fn ($value, $key): bool => $key !== 'page_info' && is_array($value))
            ->values();
    }

    private function looksLikeRecord(array $payload): bool
    {
        return isset($payload['entity_id'])
            || isset($payload['product_id'])
            || isset($payload['sku'])
            || isset($payload['new_sku']);
    }

    private function compatibilityRows(mixed $value, ?string $label = null): Collection
    {
        if (! is_array($value)) {
            return collect(filled($value) ? [[
                'name' => trim(($label ? $label.': ' : '').(string) $value),
                'raw_payload' => $value,
            ]] : []);
        }

        return collect($value)
            ->flatMap(function ($item, $key) use ($label): Collection {
                $nextLabel = is_string($key) ? trim(implode(' ', array_filter([$label, $key]))) : $label;

                if (is_array($item)) {
                    return $this->compatibilityRows($item, $nextLabel);
                }

                if (! filled($item)) {
                    return collect();
                }

                return collect([[
                    'name' => trim(($nextLabel ? $nextLabel.': ' : '').(string) $item),
                    'raw_payload' => is_string($key) ? [$key => $item] : $item,
                ]]);
            })
            ->filter(fn (array $row): bool => filled($row['name'] ?? null))
            ->values();
    }

    private function relatedIds(mixed $value): array
    {
        if (! is_array($value)) {
            return is_numeric($value) ? [(int) $value] : [];
        }

        if ($this->looksLikeRecord($value)) {
            $ids = [];

            foreach (['entity_id', 'product_id', 'id'] as $key) {
                if (is_numeric($value[$key] ?? null)) {
                    $ids[] = (int) $value[$key];
                }
            }

            if (isset($value['related_product'])) {
                $ids = array_merge($ids, $this->relatedIds($value['related_product']));
            }

            return array_values(array_unique($ids));
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $ids = array_merge($ids, $this->relatedIds($item));

                continue;
            }

            if (is_numeric($item)) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    private function relatedRecords(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $records = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->looksLikeRecord($item)) {
                $records[] = $item;
            }

            $records = array_merge($records, $this->relatedRecords($item));
        }

        return $records;
    }

    private function logEnrichmentFailure(\Throwable $exception, Part $part, string $stage, array $context = []): void
    {
        Log::warning('MobileSentrix product enrichment failed; using local part data.', array_merge([
            'part_id' => $part->id,
            'sku' => $part->sku,
            'stage' => $stage,
            'message' => Str::limit($exception->getMessage(), 500, '...'),
        ], $context));
    }

    private function emptySummary(): array
    {
        return [
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'processed_count' => 0,
            'price_changed_count' => 0,
            'stock_changed_count' => 0,
        ];
    }
}
