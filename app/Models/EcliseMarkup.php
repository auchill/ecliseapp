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

    public const MARKUP_PERCENTAGE = 'percentage';

    public const MARKUP_FIXED = 'fixed';

    public const ITEM_TYPES = [
        self::ITEM_TYPE_PARTS => 'Parts',
        self::ITEM_TYPE_PRE_OWNED_DEVICES => 'Pre-Owned Devices',
    ];

    public const SCOPE_TYPES = [
        self::SCOPE_ALL => 'All',
        self::SCOPE_CATEGORY => 'Category',
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
        'markup_type',
        'markup_value',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
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

            if (! array_key_exists($markup->scope_type, self::SCOPE_TYPES)) {
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
            }

            if ($markup->scope_type === self::SCOPE_CATEGORY && ! $markup->category_id) {
                throw new InvalidArgumentException('A MobileSentrix category is required for category markup.');
            }

            if ($markup->is_active && $markup->activeDuplicateExists()) {
                throw new InvalidArgumentException('An active markup rule already exists for this item type, scope, and category.');
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
        return self::SCOPE_TYPES[$this->scope_type] ?? $this->scope_type;
    }

    public function markupTypeLabel(): string
    {
        return self::MARKUP_TYPES[$this->markup_type] ?? $this->markup_type;
    }

    public function activeDuplicateExists(): bool
    {
        return self::query()
            ->whereKeyNot($this->getKey() ?: 0)
            ->where('is_active', true)
            ->where('item_type', $this->item_type)
            ->where('scope_type', $this->scope_type)
            ->when(
                $this->category_id,
                fn (Builder $query): Builder => $query->where('category_id', $this->category_id),
                fn (Builder $query): Builder => $query->whereNull('category_id'),
            )
            ->exists();
    }
}
