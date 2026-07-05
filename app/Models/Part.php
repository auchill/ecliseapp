<?php

namespace App\Models;

use App\Support\ProductDescriptionSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Part extends Model
{
    use HasFactory;

    private const LOCAL_ID_FLOOR = 900000000000000000;

    public const MARKUP_TYPES = [
        'none' => 'No markup',
        'fixed' => 'Fixed amount',
        'percentage' => 'Percentage',
    ];

    public const CATEGORIES = [
        'Screens',
        'Batteries',
        'Charging Ports',
        'Cameras',
        'Back Covers',
        'Speakers',
        'Keyboards',
        'Laptop Screens',
        'Laptop Batteries',
        'Motherboards',
        'Other',
    ];

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Part $part): void {
            if (filled($part->getKey())) {
                return;
            }

            $part->id = (int) max(
                self::LOCAL_ID_FLOOR,
                ((int) static::query()->where('id', '>=', self::LOCAL_ID_FLOOR)->max('id')) + 1,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'api_updated_at' => 'datetime',
            'product_hold_date' => 'datetime',
            'featured' => 'boolean',
            'premium' => 'boolean',
            'end_of_life' => 'boolean',
            'weight' => 'decimal:4',
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'length' => 'decimal:4',
            'is_in_stock' => 'boolean',
            'category_ids' => 'array',
            'regular_price_with_tax' => 'decimal:4',
            'regular_price_without_tax' => 'decimal:4',
            'final_price_with_tax' => 'decimal:4',
            'final_price_without_tax' => 'decimal:4',
            'customer_price' => 'decimal:4',
            'is_saleable' => 'boolean',
            'image_gallery' => 'array',
            'in_stock_qty' => 'integer',
            'is_preorder' => 'boolean',
            'related_product' => 'array',
            'model_text' => 'array',
            'total_reviews_count' => 'integer',
            'tier_price' => 'array',
            'has_custom_options' => 'boolean',
            'cost_price' => 'decimal:4',
            'compatibility' => 'array',
            'specifications' => 'array',
            'price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'markup_value' => 'decimal:4',
            'api_price' => 'decimal:4',
            'final_price' => 'decimal:4',
            'quantity' => 'integer',
            'api_quantity' => 'integer',
            'is_api_item' => 'boolean',
            'is_active' => 'boolean',
            'raw_payload' => 'array',
            'tags_raw_payload' => 'array',
            'last_price_synced_at' => 'datetime',
            'last_stock_synced_at' => 'datetime',
            'synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_enriched_at' => 'datetime',
        ];
    }

    public function partCategory(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PartCategory::class, 'part_category_part', 'part_id', 'category_id')
            ->withTimestamps();
    }

    public function partCategories(): BelongsToMany
    {
        return $this->categories();
    }

    public function brandName(): ?string
    {
        return $this->brand ?: $this->brand_text ?: $this->manufacturer_text ?: $this->device_manufacturer_text;
    }

    public function categoryName(): ?string
    {
        return $this->partCategory?->name ?? $this->categories->first()?->name ?? $this->part_category;
    }

    public function modelName(): ?string
    {
        $modelText = $this->model_text;

        if (is_array($modelText)) {
            $value = collect($modelText)->filter(fn ($model): bool => filled($model))->implode(', ');

            if ($value !== '') {
                return $value;
            }
        }

        return $this->device_model_text ?: $this->model_compatibility ?: (is_string($this->model) ? $this->model : null);
    }

    public function displayPrice(): float
    {
        return (float) ($this->selling_price ?: $this->final_price ?: $this->customer_price ?: $this->price);
    }

    public function imageUrl(): string
    {
        if ($this->local_image_path ?: $this->image_path) {
            return asset('storage/'.($this->local_image_path ?: $this->image_path));
        }

        if ($this->default_image ?: $this->image_url) {
            return $this->default_image ?: $this->image_url;
        }

        if (file_exists(public_path('images/parts/part-placeholder.svg'))) {
            return asset('images/parts/part-placeholder.svg');
        }

        return asset('images/brand/logo.png');
    }

    public function mainImageUrl(): string
    {
        return $this->gallery_images->first()?->large_image_url ?: $this->imageUrl();
    }

    public function getMainImageUrlAttribute(): string
    {
        return $this->mainImageUrl();
    }

    public function getGalleryImagesAttribute()
    {
        $images = collect();

        if (filled($this->default_image ?: $this->image_url)) {
            $url = $this->default_image ?: $this->image_url;
            $images->push((object) [
                'image_url' => $url,
                'thumbnail_url' => $url,
                'large_image_url' => $url,
                'label' => 'Default',
                'alt_text' => $this->name,
            ]);
        }

        foreach ((array) $this->image_gallery as $image) {
            $row = is_array($image) ? $image : ['image_url' => $image];
            $url = data_get($row, 'url')
                ?: data_get($row, 'image_url')
                ?: data_get($row, 'file')
                ?: data_get($row, 'large_image_url')
                ?: data_get($row, 'thumbnail_url');

            if (! filled($url)) {
                continue;
            }

            $images->push((object) [
                'image_url' => $url,
                'thumbnail_url' => data_get($row, 'thumbnail_url') ?: data_get($row, 'small_image_url') ?: data_get($row, 'thumb_url') ?: $url,
                'large_image_url' => data_get($row, 'large_image_url') ?: data_get($row, 'full_image_url') ?: $url,
                'label' => data_get($row, 'label'),
                'alt_text' => data_get($row, 'alt_text') ?: data_get($row, 'alt') ?: data_get($row, 'label') ?: $this->name,
            ]);
        }

        return $images->unique('image_url')->values();
    }

    public function displayDescription(): string
    {
        return ProductDescriptionSanitizer::sanitize($this->description ?: $this->short_description);
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return $this->displayDescription();
    }

    public function getDisplayPriceAttribute(): float
    {
        return $this->displayPrice();
    }

    public function getDisplayBadgeIconUrlAttribute(): ?string
    {
        return PartBadge::displayIconUrl(
            $this->display_badge_name,
            $this->firstRawPayloadValue([
                'product_badges_icon_url',
                'product_badge_icon_url',
                'badge_icon_url',
                'badge_image_url',
            ]),
        );
    }

    public function getDisplayBadgeNameAttribute(): ?string
    {
        return PartBadge::displayName($this->product_badges_text ?: $this->firstScalarValue($this->product_badges));
    }

    public function getDisplayWarrantyIconUrlAttribute(): ?string
    {
        return PartWarranty::displayIconUrl(
            $this->warranty_period,
            $this->warranty_period_text,
            $this->firstRawPayloadValue([
                'warranty_icon_url',
                'warranty_image_url',
                'warranty_photo_url',
            ]),
        );
    }

    public function getDisplayWarrantyLabelAttribute(): ?string
    {
        return PartWarranty::displayLabel($this->warranty_period, $this->warranty_period_text);
    }

    public function getCompatibilityLabelsAttribute()
    {
        return $this->labelsFromPayload($this->compatibility, ['name', 'label', 'title', 'value', 'compatibility']);
    }

    public function getTagLabelsAttribute()
    {
        $labels = collect();

        $walk = function (mixed $value) use (&$walk, $labels): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                if (in_array($key, ['tag', 'tags'], true)) {
                    if (is_array($child)) {
                        $labels->push(...$this->labelsFromPayload($child, ['name', 'label', 'title', 'value'])->all());
                    } elseif (is_scalar($child) && trim((string) $child) !== '') {
                        $labels->push(trim((string) $child));
                    }

                    continue;
                }

                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        $walk($this->tags_raw_payload);

        return $labels->unique()->values();
    }

    public function getRelatedProductPartsAttribute()
    {
        $ids = collect((array) $this->related_product)
            ->map(function ($related) {
                if (is_numeric($related)) {
                    return (int) $related;
                }

                return is_array($related)
                    ? (int) ($related['entity_id'] ?? $related['product_id'] ?? $related['id'] ?? 0)
                    : 0;
            })
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $this->getKey())
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : static::query()->whereKey($ids->all())->get()->sortBy(fn (Part $part) => $ids->search((int) $part->getKey()))->values();
    }

    public function isAvailableForPartsPurchase(): bool
    {
        return (bool) ($this->is_saleable ?? false)
            || (bool) $this->is_in_stock
            || (int) $this->quantity > 0
            || (int) $this->in_stock_qty > 0;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->isAvailableForPartsPurchase();
    }

    public function sourceSkus(): array
    {
        return collect([$this->sku, $this->new_sku])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function stockLabel(): string
    {
        if ($this->is_in_stock || $this->quantity > 0 || $this->in_stock_qty > 0) {
            return 'In stock';
        }

        return $this->availability_status ?: $this->stock_status ?: 'Check availability';
    }

    private function firstRawPayloadValue(array $keys): ?string
    {
        $payload = (array) $this->raw_payload;

        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstScalarValue(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            $result = $this->firstScalarValue($item);

            if ($result) {
                return $result;
            }
        }

        return null;
    }

    private function labelsFromPayload(mixed $payload, array $preferredKeys)
    {
        $labels = collect();

        $walk = function (mixed $value) use (&$walk, $preferredKeys, $labels): void {
            if (! is_array($value)) {
                return;
            }

            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_scalar($item) && trim((string) $item) !== '') {
                        $labels->push(trim((string) $item));
                    } elseif (is_array($item)) {
                        $walk($item);
                    }
                }

                return;
            }

            foreach ($preferredKeys as $key) {
                $label = $value[$key] ?? null;

                if (is_scalar($label) && trim((string) $label) !== '') {
                    $labels->push(trim((string) $label));
                    break;
                }
            }

            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        if (is_array($payload)) {
            $walk($payload);
        }

        return $labels->unique()->values();
    }
}
