<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A single procurement line. `mobilesentrix_price` is a snapshot of the MobileSentrix cost
 * at order time, so historical orders stay accurate when the catalogue price changes.
 */
class MobileSentrixOrderItem extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_order_items';

    protected $fillable = [
        'mobilesentrix_order_id',
        'mobilesentrix_buffer_id',
        'customer_id',
        'order_number',
        'repair_number',
        'is_device',
        'is_part',
        'source_id',
        'source_sku',
        'quantity',
        'mobilesentrix_price',
        'mobilesentrix_tax',
    ];

    protected function casts(): array
    {
        return [
            'is_device' => 'boolean',
            'is_part' => 'boolean',
            'source_id' => 'integer',
            'quantity' => 'integer',
            'mobilesentrix_price' => 'decimal:2',
            'mobilesentrix_tax' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MobileSentrixOrderItem $item): void {
            if ((bool) $item->is_device === (bool) $item->is_part) {
                throw new InvalidArgumentException('A MobileSentrix order item must be exactly one of device or part.');
            }

            if ((int) $item->quantity <= 0) {
                throw new InvalidArgumentException('MobileSentrix order item quantity must be greater than zero.');
            }

            if ((float) $item->mobilesentrix_price < 0 || (float) $item->mobilesentrix_tax < 0) {
                throw new InvalidArgumentException('MobileSentrix order item monetary values cannot be negative.');
            }

            if ((int) $item->source_id <= 0 || blank($item->source_sku)) {
                throw new InvalidArgumentException('MobileSentrix order item source identity is required.');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MobileSentrixOrder::class, 'mobilesentrix_order_id');
    }

    public function buffer(): BelongsTo
    {
        return $this->belongsTo(MobileSentrixBuffer::class, 'mobilesentrix_buffer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function typeLabel(): string
    {
        return $this->is_device ? 'Device' : 'Part';
    }

    public function lineTotal(): float
    {
        return round(((int) $this->quantity * (float) $this->mobilesentrix_price) + (float) $this->mobilesentrix_tax, 2);
    }

    public function sourceRecord(): Part|MobileSentrixDevice|null
    {
        if ($this->is_device) {
            return MobileSentrixDevice::query()
                ->where('entity_id', $this->source_id)
                ->where('sku', $this->source_sku)
                ->first();
        }

        return Part::query()
            ->whereKey($this->source_id)
            ->where('sku', $this->source_sku)
            ->first();
    }
}
