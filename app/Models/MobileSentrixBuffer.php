<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A MobileSentrix item that a customer has already paid for and that Eclise must now
 * procure from MobileSentrix.
 */
class MobileSentrixBuffer extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_buffer';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_PROCESSED = 'Processed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSED,
    ];

    /** The purchased shop order line that created the requirement. */
    public const SOURCE_ORDER_ITEM = 'order_item';

    /** The selected repair part option that created the requirement. */
    public const SOURCE_REPAIR_PART_SELECTION = 'repair_part_selection';

    public const SOURCE_REFERENCE_TYPES = [
        self::SOURCE_ORDER_ITEM,
        self::SOURCE_REPAIR_PART_SELECTION,
    ];

    protected $fillable = [
        'customer_id',
        'order_number',
        'repair_number',
        'source_reference_type',
        'source_reference_id',
        'is_device',
        'is_part',
        'source_id',
        'source_sku',
        'quantity',
        'processed_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'source_reference_id' => 'integer',
            'is_device' => 'boolean',
            'is_part' => 'boolean',
            'source_id' => 'integer',
            'quantity' => 'integer',
            'processed_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MobileSentrixBuffer $buffer): void {
            $buffer->assertValid();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MobileSentrixOrderItem::class, 'mobilesentrix_buffer_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->processed_quantity);
    }

    public function isFullyProcessed(): bool
    {
        return $this->remainingQuantity() <= 0;
    }

    public function typeLabel(): string
    {
        return $this->is_device ? 'Device' : 'Part';
    }

    /**
     * Resolves the live MobileSentrix catalogue record. Descriptions are deliberately not
     * duplicated into the buffer; only procurement order lines snapshot a price.
     */
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

    private function assertValid(): void
    {
        if (! in_array($this->status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid MobileSentrix buffer status.');
        }

        if (! in_array($this->source_reference_type, self::SOURCE_REFERENCE_TYPES, true)) {
            throw new InvalidArgumentException('Invalid MobileSentrix buffer source reference type.');
        }

        if ((bool) $this->is_device === (bool) $this->is_part) {
            throw new InvalidArgumentException('A MobileSentrix buffer item must be exactly one of device or part.');
        }

        if ((int) $this->source_id <= 0 || blank($this->source_sku)) {
            throw new InvalidArgumentException('MobileSentrix buffer source identity is required.');
        }

        if ((int) $this->quantity <= 0) {
            throw new InvalidArgumentException('MobileSentrix buffer quantity must be greater than zero.');
        }

        if ((int) $this->processed_quantity < 0 || (int) $this->processed_quantity > (int) $this->quantity) {
            throw new InvalidArgumentException('MobileSentrix processed quantity must be between zero and the required quantity.');
        }

        if (blank($this->order_number) && blank($this->repair_number)) {
            throw new InvalidArgumentException('A MobileSentrix buffer item requires an originating order or repair reference.');
        }
    }
}
