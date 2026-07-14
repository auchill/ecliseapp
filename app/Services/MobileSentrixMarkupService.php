<?php

namespace App\Services;

use App\Models\EcliseMarkup;
use App\Models\MobileSentrixDevice;
use App\Models\Part;
use App\Models\PartCategory;
use App\Support\MarkupPriceResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MobileSentrixMarkupService
{
    private static ?Collection $rules = null;

    public static function flushRuleCache(): bool
    {
        self::$rules = null;
        Cache::forget('mobilesentrix.markup.rules');

        return true;
    }

    public function calculatePartPrice(Part $part): MarkupPriceResult
    {
        return $this->calculate(
            EcliseMarkup::ITEM_TYPE_PARTS,
            $this->partCategoryIds($part),
            $this->partSourcePrice($part),
        );
    }

    public function calculatePreOwnedDevicePrice(MobileSentrixDevice $device): MarkupPriceResult
    {
        return $this->calculate(
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES,
            $this->deviceCategoryIds($device),
            $this->deviceSourcePrice($device),
        );
    }

    public function resolveMarkupRule(string $itemType, array $categoryIds = []): ?EcliseMarkup
    {
        if (! array_key_exists($itemType, EcliseMarkup::ITEM_TYPES)) {
            return null;
        }

        $categoryIds = collect($categoryIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $rules = $this->activeRules()
            ->where('item_type', $itemType);

        $categoryRules = $rules
            ->where('scope_type', EcliseMarkup::SCOPE_CATEGORY)
            ->filter(fn (EcliseMarkup $rule): bool => $categoryIds->contains((int) $rule->category_id));

        if ($categoryRules->isNotEmpty()) {
            $depths = $this->categoryDepths($categoryIds->all());

            // Deterministic precedence: highest priority, deepest matching category when known,
            // then lowest rule id. Category rules do not stack with global rules.
            return $categoryRules
                ->sortBy([
                    fn (EcliseMarkup $rule): int => -1 * (int) $rule->priority,
                    fn (EcliseMarkup $rule): int => -1 * (int) ($depths[(int) $rule->category_id] ?? 0),
                    fn (EcliseMarkup $rule): int => (int) $rule->id,
                ])
                ->first();
        }

        return $rules
            ->where('scope_type', EcliseMarkup::SCOPE_ALL)
            ->where('category_id', null)
            ->sortBy([
                fn (EcliseMarkup $rule): int => -1 * (int) $rule->priority,
                fn (EcliseMarkup $rule): int => (int) $rule->id,
            ])
            ->first();
    }

    public function categoryOptions(string $itemType): Collection
    {
        return match ($itemType) {
            EcliseMarkup::ITEM_TYPE_PARTS => PartCategory::query()
                ->active()
                ->orderBy('level')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'level'])
                ->map(fn (PartCategory $category): array => [
                    'id' => (int) $category->id,
                    'label' => $this->partCategoryLabel($category),
                ])
                ->values(),
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES => MobileSentrixDevice::query()
                ->whereNotNull('raw_payload')
                ->get(['raw_payload'])
                ->flatMap(fn (MobileSentrixDevice $device): array => $this->deviceCategoryIds($device))
                ->unique()
                ->sort()
                ->values()
                ->map(fn (int $categoryId): array => [
                    'id' => $categoryId,
                    'label' => 'MobileSentrix Category #'.$categoryId,
                ]),
            default => collect(),
        };
    }

    public function refreshSummary(): array
    {
        self::flushRuleCache();

        return [
            'parts_rules' => EcliseMarkup::query()->active()->where('item_type', EcliseMarkup::ITEM_TYPE_PARTS)->count(),
            'pre_owned_device_rules' => EcliseMarkup::query()->active()->where('item_type', EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES)->count(),
            'source_prices_modified' => 0,
            'errors' => 0,
        ];
    }

    private function calculate(string $itemType, array $categoryIds, ?float $basePrice): MarkupPriceResult
    {
        if (! array_key_exists($itemType, EcliseMarkup::ITEM_TYPES) || $basePrice === null) {
            return new MarkupPriceResult($basePrice, null, 0.0, 0.0, $basePrice, null, null);
        }

        $rule = $this->resolveMarkupRule($itemType, $categoryIds);

        if (! $rule) {
            return new MarkupPriceResult($basePrice, null, 0.0, 0.0, round($basePrice, 2), null, null);
        }

        $markupValue = (float) $rule->markup_value;
        $markupAmount = $rule->markup_type === EcliseMarkup::MARKUP_PERCENTAGE
            ? $basePrice * ($markupValue / 100)
            : $markupValue;
        $sellingPrice = round($basePrice + $markupAmount, 2);

        return new MarkupPriceResult(
            round($basePrice, 2),
            $rule->markup_type,
            $markupValue,
            round($markupAmount, 2),
            $sellingPrice,
            (int) $rule->id,
            $rule->scope_type,
        );
    }

    private function activeRules(): Collection
    {
        return self::$rules ??= EcliseMarkup::query()
            ->active()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function partSourcePrice(Part $part): ?float
    {
        return $this->firstNumeric([
            $part->api_price,
            $part->price,
            $part->customer_price,
            $part->final_price_without_tax,
            $part->regular_price_without_tax,
            $part->cost_price,
        ]);
    }

    private function deviceSourcePrice(MobileSentrixDevice $device): ?float
    {
        return $this->firstNumeric([
            $device->price,
            $device->final_price,
            $device->regular_price,
            $device->cost,
        ]);
    }

    private function firstNumeric(array $values): ?float
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return max(0, (float) $value);
            }
        }

        return null;
    }

    private function partCategoryIds(Part $part): array
    {
        $ids = collect($part->category_ids ?: []);

        if ($part->relationLoaded('categories')) {
            $ids = $ids->merge($part->categories->pluck('id'));
        } else {
            $ids = $ids->merge($part->categories()->pluck('part_categories.id'));
        }

        return $ids
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function deviceCategoryIds(MobileSentrixDevice $device): array
    {
        return collect([
            data_get($device->raw_payload, 'category_id'),
            data_get($device->raw_payload, 'category_ids'),
            data_get($device->raw_payload, 'categories'),
            data_get($device->raw_payload, 'category'),
            data_get($device->raw_payload, 'product.category_id'),
            data_get($device->raw_payload, 'product.category_ids'),
        ])
            ->flatten()
            ->map(function ($value): ?int {
                if (is_array($value)) {
                    $value = $value['id'] ?? $value['category_id'] ?? null;
                }

                return is_numeric($value) ? (int) $value : null;
            })
            ->filter(fn (?int $id): bool => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function categoryDepths(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return PartCategory::query()
            ->whereIn('id', $categoryIds)
            ->pluck('level', 'id')
            ->map(fn ($level): int => (int) $level)
            ->all();
    }

    private function partCategoryLabel(PartCategory $category): string
    {
        $labels = collect([$category->name]);
        $parent = $category->parentCategory;

        while ($parent) {
            $labels->prepend($parent->name);
            $parent = $parent->parentCategory;
        }

        return $labels->implode(' > ');
    }
}
