<?php

namespace App\Models;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class CartItem extends Model
{
    use HasFactory;

    public const SOURCE_ECLISE = 'Eclise';

    public const SOURCE_MOBILESENTRIX = 'Mobilesentrix';

    public const SOURCES = [
        self::SOURCE_ECLISE,
        self::SOURCE_MOBILESENTRIX,
    ];

    protected $fillable = [
        'cart_id',
        'source_id',
        'source_sku',
        'source',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CartItem $item): void {
            if (! in_array($item->source, self::SOURCES, true)) {
                throw new InvalidArgumentException('Invalid cart item source.');
            }

            if ((int) $item->source_id <= 0 || blank($item->source_sku)) {
                throw new InvalidArgumentException('Cart item source identity is required.');
            }

            if ((int) $item->quantity <= 0 || (float) $item->unit_price < 0) {
                throw new InvalidArgumentException('Cart item quantity and price are invalid.');
            }
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function purchasable(): Product|MobileSentrixDevice|null
    {
        if ($this->source === self::SOURCE_MOBILESENTRIX) {
            return MobileSentrixDevice::query()
                ->where('entity_id', $this->source_id)
                ->where('sku', $this->source_sku)
                ->first();
        }

        return Product::query()
            ->whereKey($this->source_id)
            ->where('sku', $this->source_sku)
            ->first();
    }

    public function displayName(): string
    {
        $purchasable = $this->purchasable();

        return $purchasable instanceof MobileSentrixDevice
            ? $purchasable->displayName()
            : ($purchasable?->name ?? 'Unavailable item');
    }

    public function displaySku(): string
    {
        return $this->source_sku;
    }

    public function displayImageUrl(): string
    {
        return $this->purchasable()?->imageUrl() ?? CatalogImage::fallbackUrl();
    }

    public function maxQuantity(): int
    {
        $purchasable = $this->purchasable();

        if ($purchasable instanceof MobileSentrixDevice) {
            return max(0, $purchasable->availableQuantity());
        }

        return max(0, (int) ($purchasable?->quantity ?? 0));
    }

    public function lineTotal(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }
}
