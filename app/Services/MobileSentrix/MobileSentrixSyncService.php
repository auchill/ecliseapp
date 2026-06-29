<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixSyncLog;
use App\Models\Part;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PartImage;
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
                    $this->addError($summary, $this->safeError($exception, $record));
                    $this->updateProgress($log, $summary, 'MobileSentrix category record failed; continuing sync.');
                }
            }

            return $this->finishLog($log, $summary, 'MobileSentrix category sync completed.');
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $this->addError($summary, $exception->getMessage());

            return $this->finishLog($log, $summary, 'MobileSentrix category sync failed.', true);
        }
    }

    public function syncParts(?string $categoryId = null, array $query = [], array $options = []): array
    {
        $syncType = $categoryId ? 'parts_category' : 'parts_full';
        $limit = isset($options['limit']) && (int) $options['limit'] > 0 ? (int) $options['limit'] : null;
        $defaultPageSize = $categoryId ? 100 : 50;
        $pageSize = max(1, min((int) ($options['page_size'] ?? $defaultPageSize), 100));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $log = $this->startLog($syncType, $categoryId ? "Syncing MobileSentrix parts for category {$categoryId}." : 'Syncing all MobileSentrix parts.');
        $summary = array_merge($this->emptySummary(), [
            'price_changed_count' => 0,
            'stock_changed_count' => 0,
            'processed_count' => 0,
            'dry_run' => $dryRun,
        ]);

        try {
            $page = 1;
            $seenPageSignatures = [];

            do {
                $remaining = $limit ? max(0, $limit - $summary['processed_count']) : null;

                if ($remaining !== null && $remaining === 0) {
                    break;
                }

                $requestLimit = $remaining !== null ? min($pageSize, $remaining) : $pageSize;
                $requestQuery = array_filter(array_merge([
                    'category_id' => $categoryId,
                    'page' => $page,
                    'limit' => $requestLimit,
                ], $query), fn ($value) => filled($value));

                $this->updateProgress(
                    $log,
                    $summary,
                    $categoryId
                        ? "Fetching MobileSentrix parts page {$page} for category {$categoryId}."
                        : "Fetching MobileSentrix parts page {$page}.",
                );
                $this->delayBetweenRequests();

                $payload = $this->client->products($requestQuery);
                $records = $this->records($payload);

                if ($records->isEmpty()) {
                    $summary['skipped_count']++;
                    $this->addError($summary, [
                        'message' => "MobileSentrix products page {$page} returned no records.",
                        'page' => $page,
                    ]);
                    $this->updateProgress($log, $summary, "MobileSentrix products page {$page} returned no records.");
                    break;
                }

                $pageSignature = $this->pageSignature($records);

                if (isset($seenPageSignatures[$pageSignature])) {
                    $summary['skipped_count']++;
                    $this->addError($summary, [
                        'message' => "Stopped MobileSentrix products sync because page {$page} repeated a previous page.",
                        'page' => $page,
                    ]);
                    $this->updateProgress($log, $summary, "Stopped MobileSentrix products sync because page {$page} repeated a previous page.");
                    break;
                }

                $seenPageSignatures[$pageSignature] = true;

                foreach ($records->chunk(50) as $chunk) {
                    foreach ($chunk as $record) {
                        if ($limit && $summary['processed_count'] >= $limit) {
                            break 2;
                        }

                        try {
                            $this->processPartRecord($record, $summary, [
                                'dry_run' => $dryRun,
                                'force' => $force,
                            ]);
                        } catch (\Throwable $exception) {
                            $summary['failed_count']++;
                            $this->addError($summary, $this->safeError($exception, $record));
                            $this->updateProgress($log, $summary, 'MobileSentrix part record failed; continuing sync.');
                        }
                    }
                }

                $hasNextPage = $this->hasNextProductsPage($payload, $page, $records->count(), $requestLimit, $summary['processed_count'], $limit);
                unset($payload, $records);
                gc_collect_cycles();

                $page++;
            } while ($hasNextPage);

            return $this->finishLog($log, $summary, 'MobileSentrix parts sync completed.');
        } catch (\Throwable $exception) {
            $summary['failed_count']++;
            $this->addError($summary, $exception->getMessage());

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

            $payload = is_numeric($sku) && Part::query()->whereKey((int) $sku)->exists()
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
            $this->addError($summary, $exception->getMessage());

            return $this->finishLog($log, $summary, "MobileSentrix part {$sku} refresh failed.", true);
        }
    }

    public function upsertPart(array $record, array &$summary, array $options = []): ?Part
    {
        [$productId, $sku, $newSku] = $this->partIdentifiers($record);

        if (! $productId && ! $sku && ! $newSku) {
            $summary['skipped_count']++;

            return null;
        }

        return DB::transaction(function () use ($record, $productId, $sku, $newSku, &$summary): Part {
            $part = $this->findExistingPart($productId, $sku, $newSku);

            $exists = (bool) $part;
            $part ??= new Part;
            $oldCost = $part->cost_price;
            $oldSellingPrice = $part->selling_price;
            $oldQuantity = $part->quantity;
            $oldStock = $part->is_in_stock;

            $brand = $this->brandFromProduct($record);
            $models = $this->modelsFromProduct($record, $brand);
            $model = $models->first();
            $categories = $this->categoriesFromProduct($record);
            $primaryCategory = $categories->first();
            $brandName = $brand?->name
                ?: $this->stringValue($record, 'brand_text')
                ?: $this->stringValue($record, 'manufacturer_text')
                ?: $this->stringValue($record, 'manufacture_text')
                ?: $this->stringValue($record, 'device_manufacturer_text')
                ?: $this->stringValue($record, 'brand')
                ?: 'MobileSentrix';
            $name = $this->limitString($this->stringValue($record, 'name') ?: $this->stringValue($record, 'title') ?: 'MobileSentrix Part '.$productId);
            $urlKey = $this->limitString($this->stringValue($record, 'url_key'));
            $mobilesentrixUrl = $this->limitString($this->stringValue($record, 'url') ?: $this->stringValue($record, 'link') ?: $urlKey);
            $imageUrl = $this->limitString($this->stringValue($record, 'default_image') ?: $this->stringValue($record, 'image_url') ?: $this->stringValue($record, 'image_link'));
            $costPrice = $this->decimalValue($record, 'price') ?? $this->decimalValue($record, 'customer_price') ?? 0;
            $markupType = $part->markup_type ?: config('mobilesentrix.default_markup_type', 'none');
            $markupValue = (float) ($part->markup_value ?? config('mobilesentrix.default_markup_value', 0));
            $sellingPrice = $this->manualSellingPrice($part, $oldCost, $oldSellingPrice, $markupType)
                ?? $this->calculateSellingPrice($costPrice, $markupType, $markupValue);
            $quantity = $this->stockQuantity($record);
            $isInStock = $this->boolValue($record, 'is_in_stock') ?? $quantity > 0;
            $apiStatus = $this->apiStatus($record);
            $active = $part->exists ? (bool) $part->is_active : $apiStatus === 'active';
            $featured = $part->exists ? (bool) $part->featured : ($this->boolValue($record, 'featured') ?? false);
            $now = now();

            if ($productId && is_numeric($productId)) {
                $part->id = (int) $productId;
            }

            $part->fill([
                'external_api_id' => $productId,
                'external_api_source' => 'MobileSentrix',
                'sku' => $this->limitString($sku),
                'new_sku' => $this->limitString($newSku),
                'barcode' => $this->limitString($this->stringValue($record, 'barcode')),
                'name' => $name,
                'slug' => $this->limitString(Str::slug($name.' '.($productId ?: $sku))),
                'url_key' => $urlKey,
                'description' => $this->stringValue($record, 'description'),
                'short_description' => $this->stringValue($record, 'short_description'),
                'product_extra_info' => $this->stringValue($record, 'product_extra_info'),
                'url' => $mobilesentrixUrl,
                'default_image' => $imageUrl,
                'image_url' => $imageUrl,
                'part_brand_id' => $brand?->id,
                'part_category_id' => $primaryCategory?->id,
                'part_model_id' => $model?->id,
                'brand' => $this->limitString($brandName),
                'part_category' => $this->limitString($primaryCategory?->name ?: $this->stringValue($record, 'front_position_text') ?: 'MobileSentrix'),
                'model_compatibility' => $this->limitString($model?->name),
                'device_type' => $this->limitString($this->stringValue($record, 'front_position_text') ?: $this->stringValue($record, 'attribute_set') ?: 'Part'),
                'price' => $costPrice,
                'cost_price' => $costPrice,
                'api_price' => $costPrice,
                'customer_price' => $this->decimalValue($record, 'customer_price'),
                'regular_price_with_tax' => $this->decimalValue($record, 'regular_price_with_tax'),
                'regular_price_without_tax' => $this->decimalValue($record, 'regular_price_without_tax'),
                'final_price_with_tax' => $this->decimalValue($record, 'final_price_with_tax'),
                'final_price_without_tax' => $this->decimalValue($record, 'final_price_without_tax'),
                'selling_price' => $sellingPrice,
                'final_price' => $sellingPrice,
                'markup_type' => $markupType,
                'markup_value' => $markupValue,
                'stock_id' => $this->limitString($this->stringValue($record, 'stock_id')),
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
                'hst_code' => $this->limitString($this->stringValue($record, 'hst_code')),
                'hst_description' => $this->stringValue($record, 'hst_description'),
                'manufacturer' => $this->primaryStringValue($record['manufacturer'] ?? $record['device_manufacturer'] ?? null),
                'manufacturer_text' => $this->limitString($this->stringValue($record, 'manufacturer_text') ?: $this->stringValue($record, 'device_manufacturer_text') ?: $this->stringValue($record, 'brand_text') ?: $brandName),
                'model' => $this->stringValue($record, 'model') ?: $this->stringValue($record, 'device_model'),
                'model_text' => $this->arrayValue($record['model_text'] ?? $record['device_model_text'] ?? null),
                'front_position' => $this->limitString($this->stringValue($record, 'front_position')),
                'front_position_text' => $this->limitString($this->stringValue($record, 'front_position_text')),
                'warranty_period' => $this->limitString($this->stringValue($record, 'warranty_period')),
                'warranty_period_text' => $this->limitString($this->stringValue($record, 'warranty_period_text') ?: $this->warrantyText($this->stringValue($record, 'warranty_period'))),
                'product_badges' => $this->limitString($this->stringValue($record, 'product_badges')),
                'product_badges_text' => $this->limitString($this->stringValue($record, 'product_badges_text')),
                'featured' => $featured,
                'premium' => $this->boolValue($record, 'premium') ?? false,
                'end_of_life' => $this->boolValue($record, 'end_of_life') ?? false,
                'print_invoice_name' => $this->limitString($this->stringValue($record, 'print_invoice_name')),
                'battery_volt' => $this->limitString($this->stringValue($record, 'battery_volt')),
                'battery_mah' => $this->limitString($this->stringValue($record, 'battery_mah')),
                'battery_wh' => $this->limitString($this->stringValue($record, 'battery_wh')),
                'battery_weight' => $this->limitString($this->stringValue($record, 'battery_weight')),
                'product_hold_date' => $this->dateValue($record, 'product_hold_date'),
                'meta_title' => $this->stringValue($record, 'meta_title'),
                'meta_keyword' => $this->stringValue($record, 'meta_keyword'),
                'meta_description' => $this->stringValue($record, 'meta_description'),
                'category_ids' => $this->arrayValue($record['category_ids'] ?? null),
                'display_currency' => $this->limitString($this->stringValue($record, 'display_currency')),
                'is_saleable' => $this->boolValue($record, 'is_saleable'),
                'image_gallery' => $this->arrayValue($record['image_gallery'] ?? null),
                'is_preorder' => $this->boolValue($record, 'is_preorder') ?? false,
                'attribute_set' => $this->limitString($this->stringValue($record, 'attribute_set')),
                'related_product' => $this->arrayValue($record['related_product'] ?? null),
                'brand_text' => $this->limitString($this->stringValue($record, 'brand_text')),
                'color_bg' => $this->limitString($this->stringValue($record, 'color_bg')),
                'color_text' => $this->limitString($this->stringValue($record, 'color_text')),
                'device_carrier_text' => $this->limitString($this->stringValue($record, 'device_carrier_text')),
                'device_color_text' => $this->limitString($this->stringValue($record, 'device_color_text')),
                'device_grade_text' => $this->limitString($this->stringValue($record, 'device_grade_text')),
                'device_manufacturer_text' => $this->limitString($this->stringValue($record, 'device_manufacturer_text')),
                'device_model_text' => $this->limitString($this->stringValue($record, 'device_model_text')),
                'device_size_text' => $this->limitString($this->stringValue($record, 'device_size_text')),
                'product_badges_bg' => $this->limitString($this->stringValue($record, 'product_badges_bg')),
                'product_order_status' => $this->limitString($this->stringValue($record, 'product_order_status')),
                'product_order_status_text' => $this->limitString($this->stringValue($record, 'product_order_status_text')),
                'specification' => $this->stringValue($record, 'specification'),
                'specification_text' => $this->stringValue($record, 'specification_text'),
                'color' => $this->limitString($this->stringValue($record, 'color')),
                'total_reviews_count' => $this->intValue($record, 'total_reviews_count'),
                'tier_price' => $this->arrayValue($record['tier_price'] ?? null),
                'has_custom_options' => $this->boolValue($record, 'has_custom_options'),
                'buy_now_url' => $this->stringValue($record, 'buy_now_url'),
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
            $this->syncPartModels($part, $models);
            $this->syncPartImages($part, $record, $imageUrl);
            $this->syncRelatedParts($part, $record);

            $summary['processed_count']++;
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

    private function processPartRecord(array $record, array &$summary, array $options = []): ?Part
    {
        if ((bool) ($options['dry_run'] ?? false)) {
            $this->dryRunPart($record, $summary);

            return null;
        }

        return $this->upsertPart($record, $summary, $options);
    }

    private function dryRunPart(array $record, array &$summary): void
    {
        [$productId, $sku, $newSku] = $this->partIdentifiers($record);

        if (! $productId && ! $sku && ! $newSku) {
            $summary['skipped_count']++;
            $summary['processed_count']++;

            return;
        }

        $part = $this->findExistingPart($productId, $sku, $newSku);
        $summary[$part ? 'updated_count' : 'created_count']++;

        if ($part) {
            $costPrice = $this->decimalValue($record, 'price') ?? $this->decimalValue($record, 'customer_price') ?? 0;
            $quantity = $this->stockQuantity($record);
            $isInStock = $this->boolValue($record, 'is_in_stock') ?? $quantity > 0;

            if ((float) $part->cost_price !== (float) $costPrice) {
                $summary['price_changed_count']++;
            }

            if ((int) $part->quantity !== $quantity || (bool) $part->is_in_stock !== $isInStock) {
                $summary['stock_changed_count']++;
            }
        }

        $summary['processed_count']++;
    }

    private function processCategoryRecord(array $record, ?PartCategory $parent, array &$summary, MobileSentrixSyncLog $log, array &$state, int $depth, int $maxDepth): ?PartCategory
    {
        $categoryId = $this->categoryId($record);

        if ($categoryId && isset($state['processed'][$categoryId])) {
            $summary['skipped_count']++;
            $this->addError($summary, [
                'message' => "Skipped duplicate MobileSentrix category {$categoryId}.",
                'entity_id' => $categoryId,
            ]);
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
            $this->addError($summary, [
                'message' => "Skipped children for MobileSentrix category {$category->id}; maximum depth {$maxDepth} reached.",
                'entity_id' => $category->id,
            ]);
            $this->updateProgress($log, $summary, "Skipped children for MobileSentrix category {$category->id}; maximum depth reached.");

            return $category;
        }

        $this->syncChildCategories($category, $summary, $log, $state, $depth + 1, $maxDepth);

        return $category;
    }

    private function syncChildCategories(PartCategory $parent, array &$summary, MobileSentrixSyncLog $log, array &$state, int $depth, int $maxDepth): void
    {
        if (! $parent->id) {
            return;
        }

        $parentCategoryId = (string) $parent->id;

        if (isset($state['fetched'][$parentCategoryId])) {
            $summary['skipped_count']++;
            $this->addError($summary, [
                'message' => "Skipped duplicate fetch for MobileSentrix category {$parentCategoryId}.",
                'entity_id' => $parentCategoryId,
            ]);
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
                $this->addError($summary, $this->safeError($exception, $record));
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

        $category = PartCategory::query()->firstOrNew(['id' => (int) $categoryId]);
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
        $sourceField = null;
        $name = null;

        foreach (['brand_text', 'manufacturer_text', 'manufacture_text', 'device_manufacturer_text', 'brand', 'manufacturer', 'device_manufacturer'] as $field) {
            $value = $this->stringValue($record, $field);

            if (! filled($value)) {
                continue;
            }

            $sourceField = $field;
            $name = $value;
            break;
        }

        if (! $name) {
            return null;
        }

        return PartBrand::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'external_brand_id' => $this->stringValue($record, 'brand') ?: $this->stringValue($record, 'manufacturer') ?: $this->stringValue($record, 'device_manufacturer'),
                'name' => $name,
                'source_field' => $sourceField,
                'raw_value' => $sourceField ? $this->stringValue($record, $sourceField) : null,
                'is_active' => true,
                'status' => 'active',
                'raw_payload' => [
                    'brand' => $record['brand'] ?? null,
                    'brand_text' => $record['brand_text'] ?? null,
                    'manufacturer' => $record['manufacturer'] ?? null,
                    'manufacturer_text' => $record['manufacturer_text'] ?? null,
                    'manufacture_text' => $record['manufacture_text'] ?? null,
                    'device_manufacturer' => $record['device_manufacturer'] ?? null,
                    'device_manufacturer_text' => $record['device_manufacturer_text'] ?? null,
                ],
                'synced_at' => now(),
            ],
        );
    }

    private function modelsFromProduct(array $record, ?PartBrand $brand): Collection
    {
        $modelIds = $this->arrayValue($record['model'] ?? $record['device_model'] ?? null);
        $modelTexts = $this->arrayValue($record['model_text'] ?? $record['device_model_text'] ?? null);
        $models = collect();
        $max = max(count($modelIds), count($modelTexts));

        for ($index = 0; $index < $max; $index++) {
            $modelId = $modelIds[$index] ?? $modelIds[0] ?? null;
            $modelName = $modelTexts[$index] ?? $modelId;

            if (! filled($modelName)) {
                continue;
            }

            $model = PartModel::query()->updateOrCreate(
                ['slug' => Str::slug((string) $modelName)],
                [
                    'external_model_id' => $modelId ? (string) $modelId : null,
                    'part_brand_id' => $brand?->id,
                    'name' => (string) $modelName,
                    'source_field' => isset($modelTexts[$index]) ? 'model_text' : 'model',
                    'raw_value' => $modelId ? (string) $modelId : (string) $modelName,
                    'status' => 'active',
                    'raw_payload' => ['model' => $record['model'] ?? null, 'model_text' => $record['model_text'] ?? null],
                    'synced_at' => now(),
                ],
            );

            $models->push($model);
        }

        return $models->unique('id')->values();
    }

    private function categoriesFromProduct(array $record): Collection
    {
        return collect($this->arrayValue($record['category_ids'] ?? []))
            ->map(function ($categoryId) {
                $categoryId = (int) $categoryId;

                // Product pages often arrive before category sync has visited every category.
                // Keep the relationship by creating a placeholder and let category sync fill details later.
                return PartCategory::query()->firstOrCreate(
                    ['id' => $categoryId],
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
            $category->id => [],
        ])->all();

        $part->categories()->sync($sync);
    }

    private function syncPartModels(Part $part, Collection $models): void
    {
        $part->models()->sync($models->pluck('id')->all());
    }

    private function syncPartImages(Part $part, array $record, ?string $defaultImage): void
    {
        PartImage::query()->where('part_id', $part->id)->delete();

        $images = collect();

        if (filled($defaultImage)) {
            $images->push([
                'image_url' => $defaultImage,
                'thumbnail_url' => $defaultImage,
                'large_image_url' => $defaultImage,
                'position' => 0,
                'label' => 'Default',
                'alt_text' => $this->stringValue($record, 'name'),
                'is_default' => true,
                'raw_payload' => ['image_url' => $defaultImage],
            ]);
        }

        foreach ($this->arrayValue($record['image_gallery'] ?? null) as $index => $image) {
            $imageUrl = is_array($image)
                ? ($image['url'] ?? $image['image_url'] ?? $image['file'] ?? $image['large_image_url'] ?? $image['thumbnail_url'] ?? null)
                : $image;

            if (! filled($imageUrl)) {
                continue;
            }

            $images->push([
                'image_url' => (string) $imageUrl,
                'thumbnail_url' => is_array($image) ? ($image['thumbnail_url'] ?? $image['small_image_url'] ?? $image['thumb_url'] ?? $imageUrl) : (string) $imageUrl,
                'large_image_url' => is_array($image) ? ($image['large_image_url'] ?? $image['full_image_url'] ?? $imageUrl) : (string) $imageUrl,
                'position' => $index + 1,
                'label' => is_array($image) ? ($image['label'] ?? null) : null,
                'alt_text' => is_array($image) ? ($image['alt_text'] ?? $image['alt'] ?? $image['label'] ?? null) : null,
                'is_default' => false,
                'raw_payload' => is_array($image) ? $image : ['image_url' => $imageUrl],
            ]);
        }

        $images
            ->unique('image_url')
            ->each(fn (array $image): PartImage => $part->images()->create($image));
    }

    private function syncRelatedParts(Part $part, array $record): void
    {
        $relatedIds = collect($this->arrayValue($record['related_product'] ?? null))
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $id): bool => $id !== (int) $part->id)
            ->values();

        if ($relatedIds->isEmpty()) {
            $part->relatedParts()->sync([]);

            return;
        }

        $existingIds = Part::query()
            ->whereKey($relatedIds->all())
            ->pluck('id')
            ->all();

        $part->relatedParts()->sync($existingIds);
    }

    private function partIdentifiers(array $record): array
    {
        return [
            $this->stringValue($record, 'entity_id') ?: $this->stringValue($record, 'product_id'),
            $this->stringValue($record, 'sku') ?: $this->stringValue($record, 'product_code'),
            $this->stringValue($record, 'new_sku'),
        ];
    }

    private function findExistingPart(?string $productId, ?string $sku, ?string $newSku): ?Part
    {
        if ($productId) {
            $part = is_numeric($productId) ? Part::query()->whereKey((int) $productId)->first() : null;

            if ($part) {
                return $part;
            }
        }

        if ($sku) {
            $part = Part::query()->where('sku', $sku)->first();

            if ($part) {
                return $part;
            }
        }

        if ($newSku) {
            return Part::query()->where('new_sku', $newSku)->first();
        }

        return null;
    }

    private function manualSellingPrice(Part $part, mixed $oldCost, mixed $oldSellingPrice, ?string $markupType): ?float
    {
        if (! $part->exists || $markupType !== 'none' || $oldSellingPrice === null) {
            return null;
        }

        return (float) $oldSellingPrice !== (float) $oldCost ? (float) $oldSellingPrice : null;
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

    private function pageSignature(Collection $records): string
    {
        return $records
            ->take(5)
            ->map(function (array $record): string {
                [$productId, $sku, $newSku] = $this->partIdentifiers($record);

                return implode(':', array_filter([$productId, $sku, $newSku]));
            })
            ->filter()
            ->implode('|') ?: sha1(json_encode($records->take(5)->values()->all()));
    }

    private function hasNextProductsPage(array $payload, int $page, int $recordCount, int $pageSize, int $processedCount, ?int $limit): bool
    {
        if ($limit && $processedCount >= $limit) {
            return false;
        }

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
        $candidates = [
            data_get($payload, 'data.page_info'),
            data_get($payload, 'page_info'),
            data_get($payload, 'data.pagination'),
            data_get($payload, 'pagination'),
            data_get($payload, 'meta.pagination'),
            data_get($payload, 'meta'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && count(array_intersect(array_keys($candidate), ['current_page', 'page', 'total_pages', 'last_page', 'total_count', 'total'])) > 0) {
                return $candidate;
            }
        }

        return null;
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
            'error_details' => $this->logErrors($summary),
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
            'error_details' => $this->logErrors($summary),
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
            'omitted_error_count' => 0,
        ];
    }

    private function addError(array &$summary, mixed $error): void
    {
        $summary['errors'] ??= [];
        $summary['omitted_error_count'] ??= 0;

        if (count($summary['errors']) >= 100) {
            $summary['omitted_error_count']++;

            return;
        }

        $summary['errors'][] = $this->trimError($error);
    }

    private function logErrors(array $summary): array
    {
        $errors = $summary['errors'] ?? [];
        $omitted = (int) ($summary['omitted_error_count'] ?? 0);

        if ($omitted > 0) {
            $errors[] = ['message' => "{$omitted} additional sync errors were omitted from this log."];
        }

        return $errors;
    }

    private function trimError(mixed $error): mixed
    {
        if (is_string($error)) {
            return Str::limit($error, 500, '...');
        }

        if (! is_array($error)) {
            return $error;
        }

        return collect($error)
            ->map(fn ($value) => is_string($value) ? Str::limit($value, 500, '...') : $value)
            ->all();
    }

    private function safeError(\Throwable $exception, array $record): array
    {
        return [
            'message' => Str::limit($exception->getMessage(), 500, '...'),
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

    private function primaryStringValue(mixed $value, int $maxLength = 191): ?string
    {
        $values = $this->arrayValue($value);
        $primaryValue = $values[0] ?? null;

        return filled($primaryValue) ? Str::limit((string) $primaryValue, $maxLength, '') : null;
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
