<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    public const SOURCE_ECLISE = 'Eclise';

    public const SOURCE_MOBILESENTRIX = 'Mobilesentrix';

    protected $fillable = [
        'cart_id',
        'product_id',
        'item_source',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CartItem $item): void {
            $item->item_source = $item->item_source ?: self::SOURCE_ECLISE;

            if ($item->item_source === self::SOURCE_ECLISE && is_numeric($item->product_id)) {
                $item->product_id = 'ecl'.$item->product_id;
            }
        });
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->ecliseProduct();
    }

    public function ecliseProduct()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function ecliseProductId(): ?int
    {
        if ($this->item_source !== self::SOURCE_ECLISE) {
            return null;
        }

        $id = preg_replace('/^ecl/i', '', (string) $this->product_id);

        return is_numeric($id) ? (int) $id : null;
    }

    public function purchasable(): Product|MobileSentrixDevice|null
    {
        if ($this->item_source === self::SOURCE_MOBILESENTRIX) {
            return MobileSentrixDevice::query()
                ->where('entity_id', $this->product_id)
                ->orWhere('sku', $this->product_id)
                ->first();
        }

        $id = $this->ecliseProductId();

        return $id ? Product::query()->find($id) : null;
    }

    public function displayName(): string
    {
        $purchasable = $this->purchasable();

        return $purchasable instanceof MobileSentrixDevice
            ? $purchasable->displayName()
            : ($purchasable?->name ?? 'Unavailable item');
    }

    public function displaySku(): ?string
    {
        return $this->purchasable()?->sku;
    }

    public function displayImageUrl(): string
    {
        $purchasable = $this->purchasable();

        if ($purchasable instanceof MobileSentrixDevice) {
            return $purchasable->imageUrl();
        }

        return $purchasable?->imageUrl() ?? asset('images/brand/logo_main.png');
    }

    public function maxQuantity(): int
    {
        $purchasable = $this->purchasable();

        if ($purchasable instanceof MobileSentrixDevice) {
            return max(1, $purchasable->availableQuantity());
        }

        return max(1, (int) ($purchasable?->quantity ?? $this->quantity));
    }

    public function lineTotal(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }
}
