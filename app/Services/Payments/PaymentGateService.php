<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Repair;
use InvalidArgumentException;

class PaymentGateService
{
    public function __construct(private readonly PaymentSettingsService $settings) {}

    public function assertOrderStatusAllowed(Order $order, string $status): void
    {
        if (! (bool) $this->settings->get('require_full_payment_before_shop_shipping', true)) {
            return;
        }

        if ($order->fulfillment_method === 'shipping'
            && in_array($status, ['Shipped', 'Delivered', 'Completed'], true)
            && $order->payment_status !== 'paid') {
            throw new InvalidArgumentException('Shipping orders require confirmed full payment before shipment.');
        }
    }

    public function assertRepairStatusAllowed(Repair $repair, string $status): void
    {
        if ((bool) $this->settings->get('require_repair_deposit_before_work', true)
            && in_array($status, ['repair_in_progress', 'waiting_for_parts'], true)
            && (float) $repair->amount_paid <= 0) {
            throw new InvalidArgumentException('A repair deposit is required before work can start.');
        }

        if ((bool) $this->settings->get('require_full_payment_before_repair_pickup', true)
            && in_array($status, ['ready_for_pickup', 'completed'], true)
            && $repair->fulfillment_method === 'pickup'
            && $repair->payment_status !== 'paid') {
            throw new InvalidArgumentException('Full payment is required before pickup.');
        }

        if ((bool) $this->settings->get('require_full_payment_before_repair_delivery', true)
            && in_array($status, ['shipped', 'completed'], true)
            && $repair->fulfillment_method === 'shipping'
            && $repair->payment_status !== 'paid') {
            throw new InvalidArgumentException('Full payment is required before delivery.');
        }
    }
}
