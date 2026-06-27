<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PartModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileSentrixSyncService
{
    public function __construct(private readonly MobileSentrixClient $client) {}

    public function syncCategories(?string $categoryId = null, ?int $maxDepth = null): array
    {
        $log = $this->startLog('categories', $categoryId ? "Syncing MobileSentrix category {$categoryId}." : 'Syncing MobileSentrix categories.');
        $summary = $this->emptySummary();
        $state = [
            'processed' => [],
            'fetched' => [],
        ];
        $maxDepth = max(1, min((int) ($maxDepth ?: 10), 25));

        try {
            if ($categoryId) {
                $state['fetched'][(string) $categoryId] = true;
            }

            $this->updateProgress($log, $summary, $categoryId ? "Fetching MobileSentrix category {$categoryId}." : 'Fetching MobileSentrix root categories.');
            $this->delayBetweenRequests();

            $payload = $categoryId ? $this->client->category($categoryId) : $this->client->categories();
            $records = $this->records($payload);

            foreach ($records as $record) {
                try {
                    $this->processCategoryRecord($record, null, $summary, $log, $state, 1, $maxDepth);
                } catch (\Throwable $exception) {
                    $summary['failed_count']++;
                    $summary['errors'][] = $this->safeError($exception, $record);
                    $this->updateProgress($log, $summary, 'MobileSentrix category record failed; continuing sync.');
                }
            }

            return $this->finishLog($log, $summary, 'MobileSentrix category sync completed.');
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $summary['errors'][] = $exception->getMessage();

            return $this->finishLog($log, $summary, 'MobileSentrix category sync failed.', true);
        }
    }

    public function syncParts(?string $categoryId = null, array $query = []): array
    {
        $log = $this->startLog('parts', $categoryId ? "Syncing MobileSentrix parts for category {$categoryId}." : 'Syncing MobileSentrix parts.');
        $summary = array_merge($this->emptySummary(), [
            'price_changed_count' => 0,
            'stock_changed_count' => 0,
        ]);

        try {
            $this->updateProgress($log, $summary, $categoryId ? "Fetching MobileSentrix parts for category {$categoryId}." : 'Fetching MobileSentrix parts.');
            $this->delayBetweenRequests();

            $payload = $this->client->products(array_filter(array_merge([
                'category_id' => $categoryId,
                'load' => 'image_gallery,related_product',
            ], $query), fn ($value) => filled($value)));

            foreach ($this->records($payload) as $record) {
                try {
                    $this->upsertPart($record, $summary);
                } catch (\Throwable $exception) {
                    $summary['failed_count']++;
                    $summary['errors'][] = $this->safeError($exception, $record);
                    $this->updateProgress($log, $summary, 'MobileSentrix part record failed; continuing sync.');
                }
            }

            return $this->finishLog($log, $summary, 'MobileSentrix parts sync completed.');
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $summary['errors'][] = $exception->getMessage();

            return $this->finishLog($log, $summary, 'MobileSentrix parts sync failed.', true);
        }
    }

    public function refreshPart(string $sku): array
    {
        $log = $this->startLog('single_part', "Refreshing MobileSentrix part {$sku}.");
        $summary = array_merge($this->emptySummary(), [
            'price_changed_count' => 0,
            'stock_changed_count' => 0,
        ]);

        try {
            $this->updateProgress($log, $summary, "Fetching MobileSentrix part {$sku}.");
            $this->delayBetweenRequests();

            $payload = is_numeric($sku) && Part::query()->where('mobilesentrix_product_id', $sku)->exists()
                ? $this->client->product($sku, ['load' => 'image_gallery,related_product'])
                : $this->client->lookupBySku($sku);

            $records = $this->records($payload);

            if ($records->isEmpty()) {
                $summary['skipped_count']++;

                return $this->finishLog($log, $summary, "No MobileSentrix part was found for {$sku}.", true);
            }

            $this->upsertPart($records->first(), $summary);

            return $this->finishLog($log, $summary, "MobileSentrix part {$sku} refreshed.");
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $summary['errors'][] = $exception->getMessage();

            return $this->finishLog($log, $summary, "MobileSentrix part {$sku} refresh failed.", true);
        }
    }

    public function upsertPart(array $record, array &$summary): ?Part
    {
        $productId = $this->stringValue($record, 'entity_id') ?: $this->stringValue($record, 'product_id');
        $sku = $this->stringValue($record, 'sku') ?: $this->stringValue($record, 'product_code');

        if (! $productId && ! $sku) {
            $summary['skipped_count']++;

            return null;
        }

        return DB::transaction(function () use ($record, $productId, $sku, &$summary): Part {
            $part = Part::query()
                ->where(function ($query) use ($productId, $sku): void {
                    if ($productId) {
                        $query->orWhere('mobilesentrix_product_id', $productId);
                    }
                    if ($sku) {
                        $query->orWhere('sku', $sku)->orWhere('new_sku', $sku);
                    }
                })
                ->first();

            $exists = (bool) $part;
            $part ??= new Part;
            $oldCost = $part->cost_price;
            $oldQuantity = $part->quantity;
            $oldStock = $part->is_in_stock;

            $brand = $this->brandFromProduct($record);
            $model = $this->modelFromProduct($record, $brand);
            $categories = $this->categoriesFromProduct($record);
            $primaryCategory = $categories->first();
            $costPrice = $this->decimalValue($record, 'price') ?? $this->decimalValue($record, 'customer_price') ?? 0;
            $markupType = $part->markup_type ?: config('mobilesentrix.default_markup_type', 'none');
            $markupValue = (float) ($part->markup_value ?? config('mobilesentrix.default_markup_value', 0));
            $sellingPrice = $this->calculateSellingPrice($costPrice, $markupType, $markupValue);
            $quantity = $this->stockQuantity($record);
            $isInStock = $this->boolValue($record, 'is_in_stock') ?? $quantity > 0;
            $apiStatus = $this->apiStatus($record);
            $active = $part->exists ? (bool) $part->is_active : $apiStatus === 'active';
            $now = now();

            $part->fill([
                'mobilesentrix_product_id' => $productId,
                'external_api_id' => $productId,
                'external_api_source' => 'MobileSentrix',
                'sku' => $sku,
                'new_sku' => $this->stringValue($record, 'new_sku'),
                'barcode' => $this->stringValue($record, 'barcode'),
                'name' => $this->stringValue($record, 'name') ?: $this->stringValue($record, 'title') ?: 'MobileSentrix Part '.$productId,
                'slug' => Str::slug(($this->stringValue($record, 'name') ?: $this->stringValue($record, 'title') ?: 'part').' '.($productId ?: $sku)),
                'description' => $this->stringValue($record, 'description'),
                'short_description' => $this->stringValue($record, 'short_description'),
                'product_extra_info' => $this->stringValue($record, 'product_extra_info'),
                'mobilesentrix_url_key' => $this->stringValue($record, 'url_key'),
                'mobilesentrix_url' => $this->stringValue($record, 'url') ?: $this->stringValue($record, 'link'),
                'default_image' => $this->stringValue($record, 'default_image') ?: $this->stringValue($record, 'image_url') ?: $this->stringValue($record, 'image_link'),
                'image_url' => $this->stringValue($record, 'default_image') ?: $this->stringValue($record, 'image_url') ?: $this->stringValue($record, 'image_link'),
                'part_brand_id' => $brand?->id,
                'part_category_id' => $primaryCategory?->id,
                'part_model_id' => $model?->id,
                'brand' => $brand?->name ?: $this->stringValue($record, 'manufacturer_text'),
                'part_category' => $primaryCategory?->name ?: $this->stringValue($record, 'front_position_text') ?: 'MobileSentrix',
                'model_compatibility' => $model?->name,
                'device_type' => $this->stringValue($record, 'front_position_text') ?: $this->stringValue($record, 'attribute_set') ?: 'Part',
                'price' => $costPrice,
                'cost_price' => $costPrice,
                'api_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'final_price' => $sellingPrice,
                'markup_type' => $markupType,
                'markup_value' => $markupValue,
                'stock_id' => $this->stringValue($record, 'stock_id'),
                'is_in_stock' => $isInStock,
                'in_stock_qty' => $quantity,
                'quantity' => $quantity,
                'api_quantity' => $quantity,
                'availability_status' => $isInStock ? 'In stock' : 'Out of stock',
                'stock_status' => $isInStock ? 'In stock' : 'Out of stock',
                'weight' => $this->decimalValue($record, 'weight'),
                'height' => $this->decimalValue($record, 'height'),
                'width' => $this->decimalValue($record, 'width'),
                'length' => $this->decimalValue($record, 'length'),
                'hst_code' => $this->stringValue($record, 'hst_code'),
                'hst_description' => $this->stringValue($record, 'hst_description'),
                'manufacturer_id' => $this->stringValue($record, 'manufacturer') ?: $this->stringValue($record, 'device_manufacturer'),
                'manufacturer_text' => $this->stringValue($record, 'manufacturer_text') ?: $this->stringValue($record, 'device_manufacturer_text'),
                'model_id' => $this->stringValue($record, 'model') ?: $this->stringValue($record, 'device_model'),
                'model_text' => $this->arrayValue($record['model_text'] ?? $record['device_model_text'] ?? null),
                'front_position' => $this->stringValue($record, 'front_position'),
                'front_position_text' => $this->stringValue($record, 'front_position_text'),
                'warranty_period' => $this->stringValue($record, 'warranty_period'),
                'warranty_period_text' => $this->warrantyText($this->stringValue($record, 'warranty_period')),
                'product_badges' => $this->stringValue($record, 'product_badges'),
                'product_badges_text' => $this->stringValue($record, 'product_badges_text'),
                'featured' => $this->boolValue($record, 'featured') ?? false,
                'premium' => $this->boolValue($record, 'premium') ?? false,
                'end_of_life' => $this->boolValue($record, 'end_of_life') ?? false,
                'api_status' => $apiStatus,
                'status' => $active ? 'active' : 'inactive',
                'is_active' => $active,
                'is_api_item' => true,
                'supplier' => 'MobileSentrix',
                'raw_payload' => $record,
                'api_updated_at' => $this->dateValue($record, 'updated_at'),
                'last_price_synced_at' => $now,
                'last_stock_synced_at' => $now,
                'synced_at' => $now,
                'last_synced_at' => $now,
            ]);

            $part->save();
            $this->syncPartCategories($part, $categories);

            $summary[$exists ? 'updated_count' : 'created_count']++;

            if ($exists && (float) $oldCost !== (float) $part->cost_price) {
                $summary['price_changed_count']++;
            }

            if ($exists && ((int) $oldQuantity !== (int) $part->quantity || (bool) $oldStock !== (bool) $part->is_in_stock)) {
                $summary['stock_changed_count']++;
            }

            return $part;
        });
    }

    private function processCategoryRecord(array $record, ?PartCategory $parent, array &$summary, MobileSentrixSyncLog $log, array &$state, int $depth, int $maxDepth): ?PartCategory
    {
        $categoryId = $this->categoryId($record);

        if ($categoryId && isset($state['processed'][$categoryId])) {
            $summary['skipped_count']++;
            $summary['errors'][] = [
                'message' => "Skipped duplicate MobileSentrix category {$categoryId}.",
                'entity_id' => $categoryId,
            ];
            $this->updateProgress($log, $summary, "Skipped duplicate MobileSentrix category {$categoryId}.");

            return null;
        }

        if ($categoryId) {
            $state['processed'][$categoryId] = true;
        }

        $category = $this->upsertCategory($record, $parent, $summary);

        if (! $category || ! $category->has_children) {
            $this->updateProgress($log, $summary, $categoryId ? "Synced MobileSentrix category {$categoryId}." : 'Synced MobileSentrix category.');

            return $category;
        }

        if ($depth >= $maxDepth) {
            $summary['skipped_count']++;
            $summary['errors'][] = [
                'message' => "Skipped children for MobileSentrix category {$category->mobilesentrix_category_id}; maximum depth {$maxDepth} reached.",
                'entity_id' => $category->mobilesentrix_category_id,
            ];
            $this->updateProgress($log, $summary, "Skipped children for MobileSentrix category {$category->mobilesentrix_category_id}; maximum depth reached.");

            return $category;
        }

        $this->syncChildCategories($category, $summary, $log, $state, $depth + 1, $maxDepth);

        return $category;
    }

    private function syncChildCategories(PartCategory $parent, array &$summary, MobileSentrixSyncLog $log, array &$state, int $depth, int $maxDepth): void
    {
        if (! $parent->mobilesentrix_category_id) {
            return;
        }

        $parentCategoryId = (string) $parent->mobilesentrix_category_id;

        if (isset($state['fetched'][$parentCategoryId])) {
            $summary['skipped_count']++;
            $summary['errors'][] = [
                'message' => "Skipped duplicate fetch for MobileSentrix category {$parentCategoryId}.",
                'entity_id' => $parentCategoryId,
            ];
            $this->updateProgress($log, $summary, "Skipped duplicate fetch for MobileSentrix category {$parentCategoryId}.");

            return;
        }

        $state['fetched'][$parentCategoryId] = true;
        $this->updateProgress($log, $summary, "Fetching MobileSentrix child categories for {$parentCategoryId}.");
        $this->delayBetweenRequests();

        foreach ($this->records($this->client->category($parentCategoryId)) as $record) {
            try {
                $this->processCategoryRecord($record, $parent, $summary, $log, $state, $depth, $maxDepth);
            } catch (\Throwable $exception) {
                $summary['failed_count']++;
                $summary['errors'][] = $this->safeError($exception, $record);
                $this->updateProgress($log, $summary, "MobileSentrix child category failed for parent {$parentCategoryId}; continuing sync.");
            }
        }
    }

    private function upsertCategory(array $record, ?PartCategory $parent, array &$summary): ?PartCategory
    {
        $categoryId = $this->stringValue($record, 'entity_id') ?: $this->stringValue($record, 'category_id');
        $name = $this->stringValue($record, 'name') ?: $this->stringValue($record, 'title');

        if (! $categoryId || ! $name) {
            $summary['skipped_count']++;

            return null;
        }

        $category = PartCategory::query()->firstOrNew(['mobilesentrix_category_id' => $categoryId]);
        $exists = $category->exists;
        $isActive = $this->boolValue($record, 'is_active') ?? true;

        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => Str::slug($name.' '.$categoryId),
            'level' => $this->intValue($record, 'level'),
            'children_count' => $this->intValue($record, 'children_count') ?? 0,
            'description' => $category->description,
            'meta_keywords' => $this->stringValue($record, 'meta_keywords'),
            'meta_title' => $this->stringValue($record, 'meta_title'),
            'is_anchor' => $this->boolValue($record, 'is_anchor') ?? false,
            'is_part' => $this->boolValue($record, 'is_part') ?? true,
            'is_active' => $isActive,
            'has_children' => $this->boolValue($record, 'has_children') ?? false,
            'image_url' => $this->stringValue($record, 'image_url') ?: $this->stringValue($record, 'image_link'),
            'status' => $isActive ? 'active' : 'inactive',
            'raw_payload' => $record,
            'synced_at' => now(),
        ]);
        $category->save();

        $summary[$exists ? 'updated_count' : 'created_count']++;

        return $category;
    }

    private function brandFromProduct(array $record): ?PartBrand
    {
        $name = $this->stringValue($record, 'manufacturer_text') ?: $this->stringValue($record, 'device_manufacturer_text');

        if (! $name) {
            return null;
        }

        return PartBrand::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'mobilesentrix_manufacturer_id' => $this->stringValue($record, 'manufacturer') ?: $this->stringValue($record, 'device_manufacturer'),
                'name' => $name,
                'is_active' => true,
                'status' => 'active',
                'raw_payload' => [
                    'manufacturer' => $record['manufacturer'] ?? null,
                    'manufacturer_text' => $record['manufacturer_text'] ?? null,
                    'device_manufacturer' => $record['device_manufacturer'] ?? null,
                    'device_manufacturer_text' => $record['device_manufacturer_text'] ?? null,
                ],
                'synced_at' => now(),
            ],
        );
    }

    private function modelFromProduct(array $record, ?PartBrand $brand): ?PartModel
    {
        $models = $this->arrayValue($record['model_text'] ?? $record['device_model_text'] ?? null);
        $modelIds = $this->arrayValue($record['model'] ?? $record['device_model'] ?? null);
        $firstRecord = null;

        foreach ($models as $index => $modelName) {
            if (! $modelName) {
                continue;
            }

            $model = PartModel::query()->updateOrCreate(
                ['slug' => Str::slug($modelName)],
                [
                    'mobilesentrix_model_id' => $modelIds[$index] ?? $modelIds[0] ?? null,
                    'part_brand_id' => $brand?->id,
                    'name' => $modelName,
                    'status' => 'active',
                    'raw_payload' => ['model' => $record['model'] ?? null, 'model_text' => $record['model_text'] ?? null],
                    'synced_at' => now(),
                ],
            );

            $firstRecord ??= $model;
        }

        return $firstRecord;
    }

    private function categoriesFromProduct(array $record): Collection
    {
        return collect($this->arrayValue($record['category_ids'] ?? []))
            ->map(function ($categoryId) {
                $categoryId = (string) $categoryId;

                return PartCategory::query()->firstOrCreate(
                    ['mobilesentrix_category_id' => $categoryId],
                    [
                        'name' => 'MobileSentrix Category '.$categoryId,
                        'slug' => Str::slug('MobileSentrix Category '.$categoryId),
                        'status' => 'active',
                        'is_active' => true,
                        'synced_at' => now(),
                    ],
                );
            })
            ->filter()
            ->values();
    }

    private function syncPartCategories(Part $part, Collection $categories): void
    {
        $sync = $categories->mapWithKeys(fn (PartCategory $category) => [
            $category->id => ['mobilesentrix_category_id' => $category->mobilesentrix_category_id],
        ])->all();

        $part->partCategories()->sync($sync);
    }

    private function calculateSellingPrice(float $costPrice, ?string $markupType, float $markupValue): float
    {
        return match ($markupType) {
            'percentage' => round($costPrice + ($costPrice * ($markupValue / 100)), 2),
            'fixed' => round($costPrice + $markupValue, 2),
            default => round($costPrice, 2),
        };
    }

    private function records(array $payload): Collection
    {
        if (isset($payload['data']['items']) && is_array($payload['data']['items'])) {
            return collect($payload['data']['items']);
        }

        if (isset($payload['data']) && is_array($payload['data']) && ! $this->looksLikeRecord($payload['data'])) {
            return collect($payload['data'])->filter(fn ($value, $key) => $key !== 'page_info' && is_array($value))->values();
        }

        if ($this->looksLikeRecord($payload)) {
            return collect([$payload]);
        }

        return collect($payload)->filter(fn ($value, $key) => $key !== 'page_info' && is_array($value))->values();
    }

    private function looksLikeRecord(array $payload): bool
    {
        return isset($payload['entity_id']) || isset($payload['product_id']) || isset($payload['sku']) || isset($payload['category_id']);
    }

    private function startLog(string $type, string $message): MobileSentrixSyncLog
    {
        return MobileSentrixSyncLog::query()->create([
            'sync_type' => $type,
            'status' => 'started',
            'started_at' => now(),
            'message' => $message,
        ]);
    }

    private function finishLog(MobileSentrixSyncLog $log, array $summary, string $message, bool $failed = false): array
    {
        $status = $failed
            ? 'failed'
            : (($summary['failed_count'] ?? 0) > 0 ? 'partial' : 'success');

        $log->update([
            'status' => $status,
            'finished_at' => now(),
            'created_count' => $summary['created_count'] ?? 0,
            'updated_count' => $summary['updated_count'] ?? 0,
            'skipped_count' => $summary['skipped_count'] ?? 0,
            'failed_count' => $summary['failed_count'] ?? 0,
            'message' => $message,
            'error_details' => $summary['errors'] ?? [],
        ]);

        return array_merge($summary, [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'log_id' => $log->id,
        ]);
    }

    private function updateProgress(MobileSentrixSyncLog $log, array $summary, string $message): void
    {
        $log->update([
            'created_count' => $summary['created_count'] ?? 0,
            'updated_count' => $summary['updated_count'] ?? 0,
            'skipped_count' => $summary['skipped_count'] ?? 0,
            'failed_count' => $summary['failed_count'] ?? 0,
            'message' => $message,
            'error_details' => $summary['errors'] ?? [],
        ]);

        Log::info('MobileSentrix sync progress.', [
            'sync_log_id' => $log->id,
            'sync_type' => $log->sync_type,
            'message' => $message,
            'created_count' => $summary['created_count'] ?? 0,
            'updated_count' => $summary['updated_count'] ?? 0,
            'skipped_count' => $summary['skipped_count'] ?? 0,
            'failed_count' => $summary['failed_count'] ?? 0,
        ]);
    }

    private function emptySummary(): array
    {
        return [
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'errors' => [],
        ];
    }

    private function safeError(\Throwable $exception, array $record): array
    {
        return [
            'message' => $exception->getMessage(),
            'entity_id' => $record['entity_id'] ?? $record['product_id'] ?? $record['category_id'] ?? null,
            'sku' => $record['sku'] ?? $record['product_code'] ?? null,
        ];
    }

    private function categoryId(array $record): ?string
    {
        return $this->stringValue($record, 'entity_id') ?: $this->stringValue($record, 'category_id');
    }

    private function delayBetweenRequests(): void
    {
        $delayMs = max(0, (int) config('mobilesentrix.sync_request_delay_ms', 200));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function stringValue(array $record, string $key): ?string
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

    private function boolValue(array $record, string $key): ?bool
    {
        if (! array_key_exists($key, $record) || $record[$key] === null || $record[$key] === '') {
            return null;
        }

        return in_array($record[$key], [true, 1, '1', 'true', 'Enabled', 'enabled'], true);
    }

    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => filled($item)));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return collect(explode(',', $value))->map(fn ($item) => trim($item))->filter()->values()->all();
        }

        return filled($value) && $value !== false ? [(string) $value] : [];
    }

    private function stockQuantity(array $record): int
    {
        if (isset($record['in_stock_qty']) && is_numeric($record['in_stock_qty'])) {
            return max(0, (int) $record['in_stock_qty']);
        }

        if (isset($record['quantity']) && is_numeric($record['quantity'])) {
            return max(0, (int) $record['quantity']);
        }

        return 0;
    }

    private function apiStatus(array $record): string
    {
        $status = $record['status'] ?? null;

        if ($status === null || $status === '') {
            return 'active';
        }

        if (in_array($status, [1, '1', 'Enabled', 'enabled'], true)) {
            return 'active';
        }

        return 'inactive';
    }

    private function dateValue(array $record, string $key): mixed
    {
        return filled($record[$key] ?? null) ? $record[$key] : null;
    }

    private function warrantyText(?string $code): ?string
    {
        return [
            '7627' => 'No Warranty',
            '7630' => '30 Days',
            '7633' => '60 Days',
            '7636' => '90 Days',
            '7642' => '6 Months',
            '7648' => '1 Year',
            '7645' => 'Lifetime Warranty',
        ][$code] ?? null;
    }
}
