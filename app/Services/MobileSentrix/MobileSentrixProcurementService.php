<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixBuffer;
use App\Models\MobileSentrixOrder;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Payments\PaymentAuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the procurement order lifecycle.
 *
 * Deliberately free of Blade and controller concerns so a future MobileSentrixOrderGateway can
 * call createProcurementOrder() and then submit($order) without any of this being rewritten.
 */
class MobileSentrixProcurementService
{
    public function __construct(
        private readonly MobileSentrixItemResolver $resolver,
        private readonly MobileSentrixOrderNumberGenerator $orderNumbers,
        private readonly PaymentAuditLogger $audit,
    ) {}

    /**
     * @param  array<int,int>  $quantitiesByBufferId  buffer id => quantity to order now
     */
    public function createProcurementOrder(array $quantitiesByBufferId, User $actor, ?string $sourceIp = null): MobileSentrixOrder
    {
        $quantitiesByBufferId = array_filter(
            array_map('intval', $quantitiesByBufferId),
            fn (int $quantity): bool => $quantity > 0,
        );

        if ($quantitiesByBufferId === []) {
            throw new InvalidArgumentException('Select at least one item with a quantity to order.');
        }

        return DB::transaction(function () use ($quantitiesByBufferId, $actor, $sourceIp): MobileSentrixOrder {
            // Lock every selected requirement before validating, so two admins cannot both
            // pass validation against the same remaining quantity.
            $buffers = MobileSentrixBuffer::query()
                ->whereKey(array_keys($quantitiesByBufferId))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($buffers->count() !== count($quantitiesByBufferId)) {
                throw new InvalidArgumentException('One or more selected procurement items no longer exist.');
            }

            $lines = [];
            $subtotalMinor = 0;

            foreach ($quantitiesByBufferId as $bufferId => $quantity) {
                /** @var MobileSentrixBuffer $buffer */
                $buffer = $buffers->get($bufferId);

                // Revalidated inside the transaction against freshly locked rows.
                if ($buffer->status !== MobileSentrixBuffer::STATUS_PENDING || $buffer->remainingQuantity() <= 0) {
                    throw new InvalidArgumentException("Requirement {$buffer->source_sku} has already been fully processed.");
                }

                if ($quantity > $buffer->remainingQuantity()) {
                    throw new InvalidArgumentException(
                        "Only {$buffer->remainingQuantity()} of {$buffer->source_sku} remains available to order."
                    );
                }

                $record = $buffer->sourceRecord();
                $price = $this->resolver->procurementPrice($record);

                if ($price === null) {
                    throw new InvalidArgumentException(
                        "No MobileSentrix price is available for {$buffer->source_sku}. Sync the catalogue and try again."
                    );
                }

                $priceMinor = Money::toMinorUnits($price);
                $subtotalMinor += $priceMinor * $quantity;

                $lines[] = [
                    'buffer' => $buffer,
                    'quantity' => $quantity,
                    'price' => Money::fromMinorUnits($priceMinor),
                ];
            }

            $order = MobileSentrixOrder::query()->create([
                'order_number' => $this->orderNumbers->next(),
                'subtotal' => Money::fromMinorUnits($subtotalMinor),
                'tax' => 0,
                'total' => Money::fromMinorUnits($subtotalMinor),
                'payment_amount' => 0,
                'currency' => 'cad',
                'order_status' => MobileSentrixOrder::STATUS_ORDERED,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                /** @var MobileSentrixBuffer $buffer */
                $buffer = $line['buffer'];

                $order->items()->create([
                    'mobilesentrix_buffer_id' => $buffer->id,
                    'customer_id' => $buffer->customer_id,
                    // Preserves the originating Eclise reference. Never the procurement number,
                    // which lives on the parent order.
                    'order_number' => $buffer->order_number,
                    'repair_number' => $buffer->repair_number,
                    'is_device' => $buffer->is_device,
                    'is_part' => $buffer->is_part,
                    'source_id' => $buffer->source_id,
                    'source_sku' => $buffer->source_sku,
                    'quantity' => $line['quantity'],
                    'mobilesentrix_price' => $line['price'],
                    // Supplier tax is unknown until the manual order is placed; the admin enters
                    // it afterwards rather than the system fabricating a rate.
                    'mobilesentrix_tax' => 0,
                ]);

                $processed = (int) $buffer->processed_quantity + $line['quantity'];

                $buffer->update([
                    'processed_quantity' => $processed,
                    'status' => $processed >= (int) $buffer->quantity
                        ? MobileSentrixBuffer::STATUS_PROCESSED
                        : MobileSentrixBuffer::STATUS_PENDING,
                ]);
            }

            $this->audit->log('mobilesentrix.order.created', $order, $actor, [
                'mobilesentrix_order_id' => $order->id,
                'order_number' => $order->order_number,
                'line_count' => count($lines),
                'subtotal' => (float) $order->subtotal,
            ], $sourceIp);

            return $order->fresh('items');
        });
    }

    public function updateOrder(MobileSentrixOrder $order, array $data, User $actor, ?string $sourceIp = null): MobileSentrixOrder
    {
        return DB::transaction(function () use ($order, $data, $actor, $sourceIp): MobileSentrixOrder {
            $order = MobileSentrixOrder::query()->lockForUpdate()->findOrFail($order->id);
            $before = $order->only([
                'supplier_order_number', 'tax', 'shipping_cost', 'shipping_discount_amount',
                'payment_amount', 'paid_at', 'tracking_number', 'delivery_carrier',
            ]);

            if (array_key_exists('shipping_method_id', $data) && filled($data['shipping_method_id'])) {
                $method = ShippingMethod::query()->find($data['shipping_method_id']);
                $data['shipping_method_name'] = $method?->name;
                $data['shipping_delivery_days'] = $method
                    ? $this->deliveryDaysLabel($method)
                    : ($data['shipping_delivery_days'] ?? null);
            }

            $order->fill($data);
            $order->total = $this->calculateTotal($order);
            $order->save();

            $this->audit->log('mobilesentrix.order.updated', $order, $actor, [
                'mobilesentrix_order_id' => $order->id,
                'order_number' => $order->order_number,
                'before' => $before,
                'after' => $order->only(array_keys($before)),
            ], $sourceIp);

            return $order->fresh('items');
        });
    }

    public function transitionStatus(MobileSentrixOrder $order, string $status, User $actor, ?string $sourceIp = null): MobileSentrixOrder
    {
        return DB::transaction(function () use ($order, $status, $actor, $sourceIp): MobileSentrixOrder {
            $order = MobileSentrixOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canTransitionTo($status)) {
                throw new InvalidArgumentException("A {$order->order_status} procurement order cannot be marked {$status}.");
            }

            $previous = $order->order_status;
            $order->update(['order_status' => $status]);

            $this->audit->log('mobilesentrix.order.'.strtolower($status), $order, $actor, [
                'mobilesentrix_order_id' => $order->id,
                'order_number' => $order->order_number,
                'before' => ['order_status' => $previous],
                'after' => ['order_status' => $status],
            ], $sourceIp);

            return $order->fresh('items');
        });
    }

    private function deliveryDaysLabel(ShippingMethod $method): ?string
    {
        $min = $method->delivery_days_min;
        $max = $method->delivery_days_max;

        return match (true) {
            $min !== null && $max !== null && $min !== $max => $min.'-'.$max,
            $min !== null => (string) $min,
            $max !== null => (string) $max,
            default => null,
        };
    }

    /**
     * Recomputes the order subtotal from its lines and derives the total.
     *
     * All arithmetic runs in integer minor units, matching the payment module's convention.
     */
    public function calculateTotal(MobileSentrixOrder $order): float
    {
        $order->loadMissing('items');

        $subtotalMinor = $order->items->reduce(
            fn (int $carry, $item): int => $carry
                + (Money::toMinorUnits($item->mobilesentrix_price) * (int) $item->quantity)
                + Money::toMinorUnits($item->mobilesentrix_tax),
            0,
        );

        $order->subtotal = Money::fromMinorUnits($subtotalMinor);

        $totalMinor = $subtotalMinor
            + Money::toMinorUnits($order->tax)
            + Money::toMinorUnits($order->shipping_cost)
            - Money::toMinorUnits($order->shipping_discount_amount);

        return Money::fromMinorUnits(max(0, $totalMinor));
    }
}
