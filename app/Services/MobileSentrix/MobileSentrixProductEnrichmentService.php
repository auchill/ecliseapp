<?php

namespace App\Services\MobileSentrix;

use App\Models\Part;
use App\Models\PartBadge;
use App\Models\PartCompatibility;
use App\Models\PartImage;
use App\Models\PartTag;
use App\Models\PartWarranty;
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

            $this->syncImages($part, $productRecord);
            $this->syncWarranty($part, $productRecord);
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

    private function syncImages(Part $part, array $record): void
    {
        $rows = $this->imageRows($record);

        if ($rows->isEmpty()) {
            return;
        }

        PartImage::query()->where('part_id', $part->id)->delete();

        $rows->each(fn (array $row): PartImage => $part->images()->create($row));
    }

    private function imageRows(array $record): Collection
    {
        $rows = collect();
        $defaultImage = $this->stringFromRecord($record, 'default_image')
            ?: $this->stringFromRecord($record, 'image_url')
            ?: $this->stringFromRecord($record, 'image_link');

        if (filled($defaultImage)) {
            $rows->push([
                'image_url' => $defaultImage,
                'thumbnail_url' => $defaultImage,
                'large_image_url' => $defaultImage,
                'position' => 0,
                'label' => 'Default',
                'alt_text' => $this->stringFromRecord($record, 'name'),
                'is_default' => true,
                'raw_payload' => ['image_url' => $defaultImage],
            ]);
        }

        foreach ($this->valuesFromMixed($record['image_gallery'] ?? null, preserveArrays: true) as $index => $image) {
            $imageUrl = is_array($image)
                ? $this->firstStringFromKeys($image, ['image_url', 'url', 'file', 'large_image_url', 'thumbnail_url', 'image'])
                : (string) $image;

            if (! filled($imageUrl)) {
                continue;
            }

            $rows->push([
                'image_url' => $imageUrl,
                'thumbnail_url' => is_array($image) ? $this->firstStringFromKeys($image, ['thumbnail_url', 'small_image_url', 'thumb_url', 'url', 'image_url']) : $imageUrl,
                'large_image_url' => is_array($image) ? $this->firstStringFromKeys($image, ['large_image_url', 'full_image_url', 'url', 'image_url']) : $imageUrl,
                'position' => $index + 1,
                'label' => is_array($image) ? $this->firstStringFromKeys($image, ['label', 'name', 'title']) : null,
                'alt_text' => is_array($image) ? $this->firstStringFromKeys($image, ['alt_text', 'alt', 'label', 'name']) : null,
                'is_default' => false,
                'raw_payload' => is_array($image) ? $image : ['image_url' => $imageUrl],
            ]);
        }

        return $rows
            ->filter(fn (array $row): bool => filled($row['image_url'] ?? null))
            ->unique('image_url')
            ->values();
    }

    private function syncWarranty(Part $part, array $record): void
    {
        $externalId = $this->stringFromRecord($record, 'warranty_period');
        $label = $this->stringFromRecord($record, 'warranty_period_text')
            ?: ($externalId ? (PartWarranty::WARRANTY_LABELS[$externalId] ?? null) : null);

        if (! filled($externalId) && ! filled($label)) {
            return;
        }

        $warranty = $externalId
            ? PartWarranty::query()->firstOrNew(['external_warranty_id' => $externalId])
            : PartWarranty::query()->firstOrNew(['name' => $label]);

        $warranty->fill([
            'external_warranty_id' => $externalId,
            'name' => $label,
            'duration_label' => $label,
            'icon_url' => $this->firstStringFromKeys($record, ['warranty_icon_url', 'warranty_period_icon_url', 'warranty_icon']),
            'photo_url' => $this->firstStringFromKeys($record, ['warranty_photo_url', 'warranty_period_photo_url', 'warranty_photo']),
            'image_url' => $this->firstStringFromKeys($record, ['warranty_image_url', 'warranty_period_image_url', 'warranty_image']),
            'raw_value' => $externalId ?: $label,
            'raw_payload' => [
                'warranty_period' => $record['warranty_period'] ?? null,
                'warranty_period_text' => $record['warranty_period_text'] ?? null,
            ],
        ]);
        $warranty->save();

        $part->forceFill([
            'part_warranty_id' => $warranty->id,
            'warranty_period_text' => $label ?: $part->warranty_period_text,
        ])->save();
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
        $badgeIcons = $this->valuesFromMixed($record['product_badges_icon_url'] ?? $record['product_badges_icon'] ?? $record['badge_icon_url'] ?? null);
        $badgePhotos = $this->valuesFromMixed($record['product_badges_photo_url'] ?? $record['product_badges_photo'] ?? $record['badge_photo_url'] ?? null);
        $badgeImages = $this->valuesFromMixed($record['product_badges_image_url'] ?? $record['product_badges_image'] ?? $record['badge_image_url'] ?? null);
        $max = max(count($badgeIds), count($badgeNames), count($badgeColors), count($badgeIcons), count($badgePhotos), count($badgeImages));
        $syncIds = [];

        for ($index = 0; $index < $max; $index++) {
            $rawName = $badgeNames[$index] ?? $badgeIds[$index] ?? null;

            if (! filled($rawName)) {
                continue;
            }

            $externalId = isset($badgeIds[$index]) ? (string) $badgeIds[$index] : null;
            $badge = $this->firstOrNewBadge((string) $rawName, $externalId);
            $badge->fill([
                'external_badge_id' => $externalId,
                'name' => Str::limit($this->badgeDisplayName((string) $rawName), 255, ''),
                'color' => isset($badgeColors[$index]) ? Str::limit((string) $badgeColors[$index], 255, '') : null,
                'icon_url' => $badgeIcons[$index] ?? null,
                'photo_url' => $badgePhotos[$index] ?? null,
                'image_url' => $badgeImages[$index] ?? null,
                'raw_value' => (string) $rawName,
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
                $relatedRecord = $this->records($this->client->product($relatedId, ['load' => 'image_gallery']))->first();

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

        $slug = Str::slug($this->badgeDisplayName($name)) ?: 'badge-'.sha1($name);
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

    private function badgeDisplayName(string $value): string
    {
        $value = trim($value);

        foreach (['Basic', 'Pro', 'Core', 'Genuine', 'AmpSentrix', 'Refurb', 'Pull'] as $name) {
            if (Str::contains(Str::lower($value), Str::lower($name))) {
                return $name;
            }
        }

        if (str_contains($value, '-')) {
            return trim(Str::afterLast($value, '-'));
        }

        return $value;
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

    private function valuesFromMixed(mixed $value, bool $preserveArrays = false): array
    {
        if (! is_array($value)) {
            if (is_string($value) && str_contains($value, ',')) {
                return collect(explode(',', $value))->map(fn (string $item): string => trim($item))->filter()->values()->all();
            }

            return filled($value) ? [(string) $value] : [];
        }

        if ($preserveArrays && array_is_list($value)) {
            return collect($value)->filter(fn ($item): bool => filled($item))->values()->all();
        }

        return collect($value)
            ->flatMap(fn ($item): array => $this->valuesFromMixed($item, $preserveArrays))
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

    private function firstStringFromKeys(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;

            if (is_array($value)) {
                $value = collect($value)->filter(fn ($item): bool => filled($item) && ! is_array($item))->first();
            }

            if (filled($value) && ! is_array($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function stringFromRecord(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        if (is_bool($value)) {
            return $value ? '1' : null;
        }

        if (is_array($value) || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
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
