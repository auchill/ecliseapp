<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * An internal Eclise procurement order against MobileSentrix.
 *
 * At this stage the order is placed with MobileSentrix manually by an administrator;
 * the record exists so a future gateway can submit it without schema changes.
 */
class MobileSentrixOrder extends Model
{
    use HasFactory;

    protected $table = 'mobilesentrix_orders';

    public const STATUS_ORDERED = 'Ordered';

    public const STATUS_RECEIVED = 'Received';

    public const STATUS_RETURNED = 'Returned';

    public const STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_RETURNED,
    ];

    /** Allowed status moves. Returned is terminal. */
    public const STATUS_TRANSITIONS = [
        self::STATUS_ORDERED => [self::STATUS_RECEIVED, self::STATUS_RETURNED],
        self::STATUS_RECEIVED => [self::STATUS_RETURNED],
        self::STATUS_RETURNED => [],
    ];

    protected $fillable = [
        'order_number',
        'supplier_order_number',
        'subtotal',
        'tax',
        'total',
        'payment_amount',
        'currency',
        'paid_at',
        'shipping_method_id',
        'shipping_method_name',
        'shipping_delivery_days',
        'shipping_discount_amount',
        'shipping_cost',
        'delivery_carrier',
        'tracking_number',
        'tracking_notes',
        'admin_notes',
        'notes',
        'order_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'shipping_discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MobileSentrixOrder $order): void {
            if (! in_array($order->order_status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Invalid MobileSentrix order status.');
            }

            foreach (['tax', 'total', 'subtotal', 'payment_amount', 'shipping_cost', 'shipping_discount_amount'] as $field) {
                if ((float) $order->{$field} < 0) {
                    throw new InvalidArgumentException('MobileSentrix order monetary values cannot be negative.');
                }
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(MobileSentrixOrderItem::class, 'mobilesentrix_order_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->order_status] ?? [], true);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->order_status) {
            self::STATUS_RECEIVED => 'bg-success-subtle text-success-emphasis',
            self::STATUS_RETURNED => 'bg-danger-subtle text-danger-emphasis',
            default => 'bg-primary-subtle text-primary-emphasis',
        };
    }
}
