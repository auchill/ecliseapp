<?php

namespace App\Models;

use App\Services\MobileSentrixMarkupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EcliseMarkup extends Model
{
    use HasFactory;

    public const ITEM_TYPE_PARTS = 'parts';

    public const ITEM_TYPE_PRE_OWNED_DEVICES = 'pre_owned_devices';

    public const SCOPE_ALL = 'all';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_BRAND = 'brand';

    public const SCOPE_PRICE_RANGE = 'price_range';

    public const MARKUP_PERCENTAGE = 'percentage';

    public const MARKUP_FIXED = 'fixed';

    public const ITEM_TYPES = [
        self::ITEM_TYPE_PARTS => 'Parts',
        self::ITEM_TYPE_PRE_OWNED_DEVICES => 'Pre-Owned Devices',
    ];

    public const SCOPE_TYPES = [
        self::SCOPE_ALL => 'All',
        self::SCOPE_BRAND => 'Brand',
        self::SCOPE_PRICE_RANGE => 'Price Range',
    ];

    public const LEGACY_SCOPE_TYPES = [
        self::SCOPE_CATEGORY => 'Legacy Category',
    ];

    public const MARKUP_TYPES = [
        self::MARKUP_PERCENTAGE => 'Percentage',
        self::MARKUP_FIXED => 'Fixed Amount',
    ];

    protected $table = 'eclise_markup';

    protected $fillable = [
        'item_type',
        'scope_type',
        'category_id',
        'brand_text',
        'brand_normalized',
        'min_price',
        'max_price',
        'markup_type',
        'markup_value',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'markup_value' => 'decimal:2',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EcliseMarkup $markup): void {
            if (! array_key_exists($markup->item_type, self::ITEM_TYPES)) {
                throw new InvalidArgumentException('Unsupported MobileSentrix markup item type.');
            }

            if (! array_key_exists($markup->scope_type, self::scopeTypeLabels())) {
                throw new InvalidArgumentException('Unsupported MobileSentrix markup scope type.');
            }

            if (! array_key_exists($markup->markup_type, self::MARKUP_TYPES)) {
                throw new InvalidArgumentException('Unsupported MobileSentrix markup type.');
            }

            if ((float) $markup->markup_value < 0) {
                throw new InvalidArgumentException('Markup value cannot be negative.');
            }

            if ($markup->scope_type === self::SCOPE_ALL) {
                $markup->category_id = null;
                $markup->brand_text = null;
                $markup->brand_normalized = null;
                $markup->min_price = null;
                $markup->max_price = null;
            }

            if ($markup->scope_type === self::SCOPE_BRAND) {
                $markup->category_id = null;
                $markup->min_price = null;
                $markup->max_price = null;
                $markup->brand_text = trim((string) $markup->brand_text);
                $markup->brand_normalized = self::normalizeBrand($markup->brand_text);

                if (! $markup->brand_normalized) {
                    throw new InvalidArgumentException('A MobileSentrix manufacturer is required for brand markup.');
                }
            }

            if ($markup->scope_type === self::SCOPE_PRICE_RANGE) {
                $markup->category_id = null;
                $markup->brand_text = null;
                $markup->brand_normalized = null;

                if ($markup->min_price === null || $markup->max_price === null) {
                    throw new InvalidArgumentException('Minimum and maximum prices are required for price range markup.');
                }

                if ((float) $markup->min_price > (float) $markup->max_price) {
                    throw new InvalidArgumentException('Minimum price cannot be greater than maximum price.');
                }
            }

            if ($markup->scope_type === self::SCOPE_CATEGORY && $markup->is_active) {
                throw new InvalidArgumentException('Category markup rules are no longer supported as active MobileSentrix markup conditions.');
            }

            if ($markup->is_active && $markup->activeDuplicateExists()) {
                throw new InvalidArgumentException('An active markup rule already exists for this source and condition.');
            }

            if ($markup->is_active && $markup->overlappingRangeExists()) {
                throw new InvalidArgumentException('An active price range markup rule overlaps another active range for this source.');
            }
        });

        static::saved(fn (): bool => MobileSentrixMarkupService::flushRuleCache());
        static::deleted(fn (): bool => MobileSentrixMarkupService::flushRuleCache());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function itemTypeLabel(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? $this->item_type;
    }

    public function scopeTypeLabel(): string
    {
        return self::scopeTypeLabels()[$this->scope_type] ?? $this->scope_type;
    }

    public function markupTypeLabel(): string
    {
        return self::MARKUP_TYPES[$this->markup_type] ?? $this->markup_type;
    }

    public function activeDuplicateExists(): bool
    {
        if (! in_array($this->scope_type, [self::SCOPE_ALL, self::SCOPE_BRAND], true)) {
            return false;
        }

        return self::query()
            ->whereKeyNot($this->getKey() ?: 0)
            ->where('is_active', true)
            ->where('item_type', $this->item_type)
            ->where('scope_type', $this->scope_type)
            ->when($this->scope_type === self::SCOPE_BRAND, fn (Builder $query): Builder => $query->where('brand_normalized', $this->brand_normalized))
            ->exists();
    }

    public function overlappingRangeExists(): bool
    {
        if ($this->scope_type !== self::SCOPE_PRICE_RANGE || $this->min_price === null || $this->max_price === null) {
            return false;
        }

        return self::query()
            ->whereKeyNot($this->getKey() ?: 0)
            ->where('is_active', true)
            ->where('item_type', $this->item_type)
            ->where('scope_type', self::SCOPE_PRICE_RANGE)
            ->where('min_price', '<=', (float) $this->max_price)
            ->where('max_price', '>=', (float) $this->min_price)
            ->exists();
    }

    public function appliesToLabel(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_BRAND => $this->brand_text ?: 'Brand',
            self::SCOPE_PRICE_RANGE => '$'.number_format((float) $this->min_price, 2).' - $'.number_format((float) $this->max_price, 2),
            self::SCOPE_CATEGORY => $this->category_id ? 'Legacy category #'.$this->category_id : 'Legacy category',
            default => 'All '.$this->itemTypeLabel(),
        };
    }

    public static function normalizeBrand(?string $brand): ?string
    {
        $normalized = mb_strtolower(trim((string) $brand));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        return $normalized === '' ? null : $normalized;
    }

    public static function scopeTypeLabels(): array
    {
        return self::SCOPE_TYPES + self::LEGACY_SCOPE_TYPES;
    }
}
