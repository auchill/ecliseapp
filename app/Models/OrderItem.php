<?php

namespace App\Models;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'source_id',
        'source_sku',
        'source',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            if (! in_array($item->source, CartItem::SOURCES, true)) {
                throw new InvalidArgumentException('Invalid order item source.');
            }

            if ((int) $item->source_id <= 0 || blank($item->source_sku)) {
                throw new InvalidArgumentException('Order item source identity is required.');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function displayImageUrl(): string
    {
        return $this->display_image_url;
    }

    public function getSourceItemAttribute(): Product|MobileSentrixDevice|null
    {
        if (array_key_exists('source_item', $this->relations)) {
            return $this->relations['source_item'];
        }

        $sourceItem = match ($this->source) {
            CartItem::SOURCE_ECLISE => Product::query()
                ->whereKey($this->source_id)
                ->where('sku', $this->source_sku)
                ->first(),
            CartItem::SOURCE_MOBILESENTRIX => MobileSentrixDevice::query()
                ->where('entity_id', $this->source_id)
                ->where('sku', $this->source_sku)
                ->first(),
            default => null,
        };

        $this->setRelation('source_item', $sourceItem);

        return $sourceItem;
    }

    public function getDisplayNameAttribute(): string
    {
        $sourceItem = $this->source_item;

        if ($sourceItem instanceof MobileSentrixDevice) {
            return $sourceItem->displayName();
        }

        return $sourceItem?->name ?: 'Item unavailable';
    }

    public function getDisplayImageUrlAttribute(): string
    {
        return $this->source_item?->display_image_url ?: CatalogImage::fallbackUrl();
    }
}
