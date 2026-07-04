<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'item_source',
        'product_name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $item->item_source = $item->item_source ?: CartItem::SOURCE_ECLISE;

            if ($item->item_source === CartItem::SOURCE_ECLISE && is_numeric($item->product_id)) {
                $item->product_id = 'ecl'.$item->product_id;
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ecliseProductId(): ?int
    {
        if ($this->item_source !== CartItem::SOURCE_ECLISE) {
            return null;
        }

        $id = preg_replace('/^ecl/i', '', (string) $this->product_id);

        return is_numeric($id) ? (int) $id : null;
    }

    public function ecliseProduct(): ?Product
    {
        $id = $this->ecliseProductId();

        return $id ? Product::query()->find($id) : null;
    }
}
