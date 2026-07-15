<?php

namespace App\Services;

use App\Models\EcliseMarkup;
use App\Models\MobileSentrixDevice;
use App\Models\Part;
use App\Support\MarkupPriceResult;
use Illuminate\Database\Eloquent\Builder;
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
            $part->manufacturer_text,
            $this->partSourcePrice($part),
        );
    }

    public function calculatePreOwnedDevicePrice(MobileSentrixDevice $device): MarkupPriceResult
    {
        return $this->calculate(
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES,
            $device->manufacturer_text,
            $this->deviceSourcePrice($device),
        );
    }

    public function resolveMarkupRule(string $itemType, ?string $manufacturerText = null, ?float $basePrice = null): ?EcliseMarkup
    {
        if (! array_key_exists($itemType, EcliseMarkup::ITEM_TYPES)) {
            return null;
        }

        $rules = $this->activeRules()
            ->where('item_type', $itemType);

        $brand = EcliseMarkup::normalizeBrand($manufacturerText);

        if ($brand) {
            $brandRule = $rules
                ->where('scope_type', EcliseMarkup::SCOPE_BRAND)
                ->where('brand_normalized', $brand)
                ->sortBy([
                    fn (EcliseMarkup $rule): int => -1 * (int) $rule->priority,
                    fn (EcliseMarkup $rule): int => (int) $rule->id,
                ])
                ->first();

            if ($brandRule) {
                return $brandRule;
            }
        }

        if ($basePrice !== null) {
            $rangeRule = $rules
                ->where('scope_type', EcliseMarkup::SCOPE_PRICE_RANGE)
                ->filter(fn (EcliseMarkup $rule): bool => (float) $rule->min_price <= $basePrice && (float) $rule->max_price >= $basePrice)
                ->sortBy([
                    fn (EcliseMarkup $rule): int => -1 * (int) $rule->priority,
                    fn (EcliseMarkup $rule): float => (float) $rule->min_price,
                    fn (EcliseMarkup $rule): int => (int) $rule->id,
                ])
                ->first();

            if ($rangeRule) {
                return $rangeRule;
            }
        }

        return $rules
            ->where('scope_type', EcliseMarkup::SCOPE_ALL)
            ->sortBy([
                fn (EcliseMarkup $rule): int => -1 * (int) $rule->priority,
                fn (EcliseMarkup $rule): int => (int) $rule->id,
            ])
            ->first();
    }

    public function categoryOptions(string $itemType): Collection
    {
        return collect();
    }

    public function brandOptions(string $itemType): Collection
    {
        return match ($itemType) {
            EcliseMarkup::ITEM_TYPE_PARTS => $this->sourceBrands(Part::query()),
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES => $this->sourceBrands(MobileSentrixDevice::query()),
            default => collect(),
        };
    }

    public function priceBounds(string $itemType): array
    {
        $query = match ($itemType) {
            EcliseMarkup::ITEM_TYPE_PARTS => Part::query(),
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES => MobileSentrixDevice::query(),
            default => null,
        };

        if (! $query) {
            return ['min' => null, 'max' => null];
        }

        $expression = $this->sourcePriceExpression($itemType);
        $row = $query
            ->whereRaw($expression.' IS NOT NULL')
            ->selectRaw('MIN('.$expression.') as min_price, MAX('.$expression.') as max_price')
            ->first();

        return [
            'min' => $row?->min_price !== null ? round((float) $row->min_price, 2) : null,
            'max' => $row?->max_price !== null ? round((float) $row->max_price, 2) : null,
        ];
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

    private function calculate(string $itemType, ?string $manufacturerText, ?float $basePrice): MarkupPriceResult
    {
        if (! array_key_exists($itemType, EcliseMarkup::ITEM_TYPES) || $basePrice === null) {
            return new MarkupPriceResult($basePrice, null, 0.0, 0.0, $basePrice, null, null);
        }

        $rule = $this->resolveMarkupRule($itemType, $manufacturerText, $basePrice);

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

    private function sourceBrands(Builder $query): Collection
    {
        return $query
            ->whereNotNull('manufacturer_text')
            ->pluck('manufacturer_text')
            ->map(fn ($brand): string => trim((string) $brand))
            ->filter(fn (string $brand): bool => $brand !== '')
            ->groupBy(fn (string $brand): string => EcliseMarkup::normalizeBrand($brand) ?? $brand)
            ->map(fn (Collection $brands): string => $brands->sortBy(fn (string $brand): string => mb_strtolower($brand))->first())
            ->sortBy(fn (string $brand): string => mb_strtolower($brand))
            ->map(fn (string $brand): array => [
                'value' => $brand,
                'label' => $brand,
            ])
            ->values();
    }

    private function sourcePriceExpression(string $itemType): string
    {
        return match ($itemType) {
            EcliseMarkup::ITEM_TYPE_PARTS => 'COALESCE(api_price, price, customer_price, final_price_without_tax, regular_price_without_tax, cost_price)',
            EcliseMarkup::ITEM_TYPE_PRE_OWNED_DEVICES => 'COALESCE(price, final_price, regular_price, cost)',
            default => 'NULL',
        };
    }
}
