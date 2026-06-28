<?php

namespace App\Services\MobileSentrix;

use App\Models\Part;
use App\Models\PartBadge;
use App\Models\PartCompatibility;
use App\Models\PartTag;
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
        $productRecord = null;
        $remoteFetchSucceeded = false;

        try {
            $baseRecord = $this->records($this->client->product($part->getKey()))->first();

            if (is_array($baseRecord)) {
                $productRecord = $baseRecord;
                $remoteFetchSucceeded = true;
            }
        } catch (\Throwable $exception) {
            $this->logEnrichmentFailure($exception, $part, 'product_detail');
        }

        try {
            $loadedRecord = $this->records($this->client->product($part->getKey(), [
                'load' => 'image_gallery,related_product',
            ]))->first();

            if (is_array($loadedRecord)) {
                $productRecord = array_merge($productRecord ?? [], $loadedRecord);
                $remoteFetchSucceeded = true;
            }
        } catch (\Throwable $exception) {
            $this->logEnrichmentFailure($exception, $part, 'product_gallery');
        }

        if ($productRecord) {
            $syncedPart = $this->syncService->upsertPart($productRecord, $summary);
            $part = $syncedPart ?: $part->fresh() ?: $part;

            $this->syncBadges($part, $productRecord);
            $this->syncRelatedParts($part, $productRecord, $summary);
        }

        if ($this->syncTagsAndCompatibility($part)) {
            $remoteFetchSucceeded = true;
        }

        if ($remoteFetchSucceeded) {
            $part->forceFill(['last_enriched_at' => now()])->save();
        }

        return $part->fresh() ?: $part;
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

        $records = $this->records($payload);
        $part->forceFill(['tags_raw_payload' => $payload])->save();

        $tagIds = $records
            ->flatMap(fn (array $record): array => $this->namesFromMixed($record['tag'] ?? $record['tags'] ?? null))
            ->map(fn (string $name): string => Str::limit($name, 255, ''))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => PartTag::query()->firstOrCreate(['name' => $name])->id)
            ->all();

        $part->tags()->sync($tagIds);

        PartCompatibility::query()->where('part_id', $part->id)->delete();

        $records
            ->flatMap(fn (array $record): array => $this->compatibilityRows($record['compatibility'] ?? null)->all())
            ->unique('name')
            ->each(function (array $row) use ($part): void {
                $part->compatibilities()->create([
                    'name' => Str::limit($row['name'], 255, ''),
                    'raw_payload' => $row['raw_payload'],
                ]);
            });

        return true;
    }

    private function syncBadges(Part $part, array $record): void
    {
        $badgeIds = $this->valuesFromMixed($record['product_badges'] ?? null);
        $badgeNames = $this->valuesFromMixed($record['product_badges_text'] ?? null);
        $badgeColors = $this->valuesFromMixed($record['product_badges_bg'] ?? null);
        $max = max(count($badgeIds), count($badgeNames), count($badgeColors));
        $syncIds = [];

        for ($index = 0; $index < $max; $index++) {
            $name = $badgeNames[$index] ?? $badgeIds[$index] ?? null;

            if (! filled($name)) {
                continue;
            }

            $externalId = isset($badgeIds[$index]) ? (string) $badgeIds[$index] : null;
            $badge = $this->firstOrNewBadge((string) $name, $externalId);
            $badge->fill([
                'external_badge_id' => $externalId,
                'name' => Str::limit((string) $name, 255, ''),
                'color' => isset($badgeColors[$index]) ? Str::limit((string) $badgeColors[$index], 255, '') : null,
                'raw_payload' => [
                    'product_badges' => $record['product_badges'] ?? null,
                    'product_badges_text' => $record['product_badges_text'] ?? null,
                    'product_badges_bg' => $record['product_badges_bg'] ?? null,
                ],
            ]);
            $badge->save();

            $syncIds[] = $badge->id;
        }

        $part->badges()->sync(array_values(array_unique($syncIds)));
    }

    private function syncRelatedParts(Part $part, array $record, array &$summary): void
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
                $relatedRecord = $this->records($this->client->product($relatedId))->first();

                if (is_array($relatedRecord)) {
                    $this->syncService->upsertPart($relatedRecord, $summary);
                }
            } catch (\Throwable $exception) {
                $this->logEnrichmentFailure($exception, $part, 'related_product', ['related_part_id' => $relatedId]);
            }
        }

        $existingIds = Part::query()
            ->whereKey($relatedIds->all())
            ->pluck('id')
            ->all();

        $part->relatedParts()->sync($existingIds);
    }

    private function firstOrNewBadge(string $name, ?string $externalId): PartBadge
    {
        if (filled($externalId)) {
            $badge = PartBadge::query()->where('external_badge_id', $externalId)->first();

            if ($badge) {
                return $badge;
            }
        }

        $slug = Str::slug($name) ?: 'badge-'.sha1($name);
        $badge = PartBadge::query()->where('slug', $slug)->first();

        if ($badge) {
            return $badge;
        }

        return new PartBadge(['slug' => $this->uniqueBadgeSlug($slug)]);
    }

    private function uniqueBadgeSlug(string $slug): string
    {
        $candidate = $slug;
        $counter = 2;

        while (PartBadge::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
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

    private function namesFromMixed(mixed $value): array
    {
        if (! is_array($value)) {
            return filled($value) ? [(string) $value] : [];
        }

        $names = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $knownName = $item['name'] ?? $item['label'] ?? $item['title'] ?? $item['value'] ?? null;

                if (filled($knownName)) {
                    $names[] = (string) $knownName;

                    continue;
                }

                $names = array_merge($names, $this->namesFromMixed($item));

                continue;
            }

            if (is_string($key) && filled($key) && blank($item)) {
                $names[] = $key;

                continue;
            }

            if (filled($item)) {
                $names[] = (string) $item;
            }
        }

        return array_values(array_unique(array_filter($names)));
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

    private function valuesFromMixed(mixed $value): array
    {
        if (! is_array($value)) {
            if (is_string($value) && str_contains($value, ',')) {
                return collect(explode(',', $value))->map(fn (string $item): string => trim($item))->filter()->values()->all();
            }

            return filled($value) ? [(string) $value] : [];
        }

        return collect($value)
            ->flatMap(fn ($item): array => $this->valuesFromMixed($item))
            ->filter()
            ->values()
            ->all();
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
