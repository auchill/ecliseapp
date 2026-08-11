<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixBuffer;
use App\Models\Order;
use App\Models\Repair;
use App\Models\RepairPartSelection;
use App\Services\Payments\PaymentAuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Turns confirmed customer payments into MobileSentrix procurement requirements.
 *
 * Every entry point is idempotent: requirements are keyed on the purchased line that caused
 * them, not on the payment event, so a webhook replayed three times still yields one row.
 */
class MobileSentrixBufferService
{
    public function __construct(
        private readonly MobileSentrixItemResolver $resolver,
        private readonly PaymentAuditLogger $audit,
    ) {}

    /**
     * @return int number of requirements newly queued
     */
    public function queuePaidShopOrder(Order $order): int
    {
        $order->loadMissing('items');
        $queued = 0;

        foreach ($order->items as $item) {
            if (! $this->resolver->isMobileSentrixOrderItem($item)) {
                continue;
            }

            $queued += $this->queue([
                'customer_id' => $order->customer_id,
                'order_number' => $order->order_number,
                'repair_number' => null,
                'source_reference_type' => MobileSentrixBuffer::SOURCE_ORDER_ITEM,
                'source_reference_id' => $item->id,
                // The shop catalogue only carries Eclise products and MobileSentrix devices;
                // MobileSentrix replacement parts reach procurement through repairs.
                'is_device' => true,
                'is_part' => false,
                'source_id' => (int) $item->source_id,
                'source_sku' => (string) $item->source_sku,
                'quantity' => max(1, (int) $item->quantity),
            ]) ? 1 : 0;
        }

        return $queued;
    }

    /**
     * @return int number of requirements newly queued
     */
    public function queuePaidRepair(Repair $repair): int
    {
        $conversation = $repair->repairConversation;

        if (! $conversation) {
            return 0;
        }

        $selections = RepairPartSelection::query()
            ->with('option', 'group')
            ->whereHas('group', fn ($query) => $query
                ->where('repair_conversation_id', $conversation->id)
                ->where('is_active', true))
            ->get();

        $queued = 0;

        foreach ($selections as $selection) {
            $option = $selection->option;

            // Skips unselected alternatives, removed options, "I Have the Parts", and any
            // locally sourced part: none of those are procured from MobileSentrix.
            if (! $option || ! $option->is_active || ! $this->resolver->isMobileSentrixRepairOption($option)) {
                continue;
            }

            $queued += $this->queue([
                'customer_id' => $repair->customer_id,
                'order_number' => null,
                'repair_number' => $repair->repair_number,
                'source_reference_type' => MobileSentrixBuffer::SOURCE_REPAIR_PART_SELECTION,
                'source_reference_id' => $selection->id,
                'is_device' => false,
                'is_part' => true,
                'source_id' => (int) $option->source_id,
                'source_sku' => (string) $option->sku_snapshot,
                // Repair part groups represent one required part each.
                'quantity' => 1,
            ]) ? 1 : 0;
        }

        return $queued;
    }

    /**
     * Creates the requirement unless it already exists.
     *
     * The unique index on (source_reference_type, source_reference_id) is the real guarantee;
     * the pre-check merely avoids the exception on the common path.
     */
    private function queue(array $attributes): bool
    {
        $exists = MobileSentrixBuffer::query()
            ->where('source_reference_type', $attributes['source_reference_type'])
            ->where('source_reference_id', $attributes['source_reference_id'])
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            $buffer = MobileSentrixBuffer::query()->create($attributes + [
                'processed_quantity' => 0,
                'status' => MobileSentrixBuffer::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent payment confirmation won the race. The requirement exists; nothing to do.
            return false;
        }

        $this->audit->log('mobilesentrix.buffer.created', $buffer, null, [
            'buffer_id' => $buffer->id,
            'customer_id' => $buffer->customer_id,
            'order_number' => $buffer->order_number,
            'repair_number' => $buffer->repair_number,
            'source_sku' => $buffer->source_sku,
            'quantity' => $buffer->quantity,
        ]);

        return true;
    }
}
