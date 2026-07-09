<?php

namespace App\Services;

use App\Mail\AdminPaymentReceivedMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PaymentFinalizer
{
    public function __construct(private readonly OrderNumberGenerator $orderNumbers) {}

    public function markPaid(Payment $payment, array $attributes = []): Payment
    {
        $sendMail = false;

        $payment = DB::transaction(function () use ($payment, $attributes, &$sendMail): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                return $payment;
            }

            $payment->fill(array_merge([
                'status' => 'paid',
                'paid_at' => now(),
            ], $attributes));

            $payable = $payment->payable()->lockForUpdate()->first();

            if ($payment->source === 'shop' && filled($payment->checkout_data)) {
                $order = $this->createOrderFromCheckout(
                    $payment,
                    $payable instanceof Cart ? $payable : null,
                );

                $payment->payable()->associate($order);
                $payment->order_id = $order->id;
                $payment->save();
            } elseif ($payable instanceof Order) {
                $payment->save();
                $this->markExistingOrderPaid($payable, $payment);
            } elseif ($payable instanceof RepairBooking) {
                $payment->save();
                $this->markRepairPaid($payable, $payment);
            } else {
                throw new RuntimeException('The payment checkout record is no longer available.');
            }

            $sendMail = true;

            return $payment->fresh('payable');
        });

        if ($sendMail) {
            $recipient = $payment->payable?->email
                ?: data_get($payment->checkout_data, 'customer.email');

            if ($recipient) {
                Mail::to($recipient)->send(new PaymentReceiptMail($payment->fresh('payable')));
            }

            User::query()
                ->admins()
                ->get()
                ->each(fn (User $admin) => Mail::to($admin->email)->send(new AdminPaymentReceivedMail($payment->fresh('payable'))));
        }

        return $payment;
    }

    public function markFailed(Payment $payment, string $status, array $rawResponse = []): Payment
    {
        if (! in_array($status, ['failed', 'cancelled', 'refunded', 'partially_refunded'], true)) {
            $status = 'failed';
        }

        $payment->update([
            'status' => $status,
            'raw_response' => $rawResponse ?: $payment->raw_response,
        ]);

        $payable = $payment->payable;

        if ($payable instanceof RepairBooking) {
            $payable->update(['payment_status' => $payable->amount_paid > 0 ? 'partially_paid' : 'unpaid']);
        } elseif ($payable instanceof Order) {
            $payable->update(['payment_status' => $status]);
        }

        return $payment->fresh('payable');
    }

    private function createOrderFromCheckout(Payment $payment, ?Cart $cart): Order
    {
        if ($payment->order_id) {
            return Order::query()->lockForUpdate()->findOrFail($payment->order_id);
        }

        $snapshot = $payment->checkout_data;
        $userId = (int) data_get($snapshot, 'user_id');
        $snapshotCustomerId = (int) data_get($snapshot, 'customer_id');
        $customerData = (array) data_get($snapshot, 'customer', []);
        $fulfillment = (array) data_get($snapshot, 'fulfillment', []);
        $totals = (array) data_get($snapshot, 'totals', []);
        $items = collect(data_get($snapshot, 'items', []));

        if ($userId <= 0 || $items->isEmpty()) {
            throw new RuntimeException('The checkout snapshot is incomplete.');
        }

        $customer = $this->updateCustomer($snapshotCustomerId, $userId, $customerData, $fulfillment);

        if ($cart && $cart->customer_id !== $customer->id) {
            throw new RuntimeException('The checkout cart does not belong to the resolved customer.');
        }

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'cart_id' => $cart?->id,
            'order_number' => $this->orderNumbers->next(),
            'customer_name' => $customerData['full_name'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'address' => $this->legacyAddressValue($fulfillment),
            'subtotal' => (float) $totals['subtotal'],
            'tax' => (float) $totals['tax'],
            'total' => (float) $totals['total'],
            'status' => 'Paid',
            'payment_provider' => $payment->gateway,
            'payment_gateway' => $payment->gateway,
            'payment_reference' => $payment->gateway_reference_id,
            'fulfillment_method' => $fulfillment['fulfillment_method'],
            'payment_status' => 'paid',
            'payment_amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at ?? now(),
            'shipping_full_name' => $fulfillment['shipping_full_name'] ?? null,
            'shipping_phone' => $fulfillment['shipping_phone'] ?? null,
            'shipping_email' => $fulfillment['shipping_email'] ?? null,
            'shipping_address_line1' => $fulfillment['shipping_address_line1'] ?? null,
            'shipping_address_line2' => $fulfillment['shipping_address_line2'] ?? null,
            'shipping_city' => $fulfillment['shipping_city'] ?? null,
            'shipping_province' => $fulfillment['shipping_province'] ?? null,
            'shipping_postal_code' => $fulfillment['shipping_postal_code'] ?? null,
            'shipping_country' => $fulfillment['shipping_country'] ?? null,
            'shipping_method_id' => $fulfillment['shipping_method_id'] ?? null,
            'shipping_method_name' => $fulfillment['shipping_method_name'] ?? null,
            'shipping_delivery_days' => $fulfillment['shipping_delivery_days'] ?? null,
            'shipping_base_cost' => (float) ($fulfillment['shipping_base_cost'] ?? 0),
            'shipping_discount_amount' => (float) ($fulfillment['shipping_discount_amount'] ?? 0),
            'shipping_cost' => (float) ($fulfillment['shipping_cost'] ?? 0),
            'delivery_carrier' => $fulfillment['delivery_carrier'] ?? null,
            'tracking_number' => $fulfillment['tracking_number'] ?? null,
            'customer_notes' => $fulfillment['notes'] ?? null,
            'notes' => $fulfillment['notes'] ?? null,
        ]);

        foreach ($items as $item) {
            $item = (array) $item;
            $this->validateSnapshotItem($item);
            $order->items()->create([
                'source_id' => $item['source_id'],
                'source_sku' => $item['source_sku'],
                'source' => $item['source'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ]);
        }

        $this->commitInventory($order);

        $order->update(['inventory_committed_at' => now()]);
        $order->statusUpdates()->create([
            'status' => 'Paid',
            'note' => 'Payment successful. Order created from shop checkout.',
            'is_customer_visible' => true,
        ]);

        $cartId = (int) (data_get($snapshot, 'cart_id') ?: $cart?->id);
        $cartToDelete = $cart ?: Cart::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->find($cartId);

        if ($cartToDelete) {
            $cartToDelete->items()->delete();
            $cartToDelete->delete();
        }

        return $order->fresh();
    }

    private function updateCustomer(
        int $customerId,
        int $userId,
        array $customerData,
        array $fulfillment,
    ): Customer {
        $profile = Customer::query()
            ->when($customerId > 0, fn ($query) => $query->whereKey($customerId))
            ->where('user_id', $userId)
            ->first()
            ?? Customer::query()->firstOrNew(['user_id' => $userId]);
        $profile->fill([
            'full_name' => $customerData['full_name'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'customer_since' => $profile->customer_since ?: now(),
            'status' => $profile->status ?: 'active',
        ]);

        if (($fulfillment['fulfillment_method'] ?? null) === 'shipping') {
            $profile->fill([
                'street_address' => $fulfillment['shipping_address_line1'] ?? null,
                'address_line_2' => $fulfillment['shipping_address_line2'] ?? null,
                'city' => $fulfillment['shipping_city'] ?? null,
                'province' => $fulfillment['shipping_province'] ?? null,
                'postal_code' => $fulfillment['shipping_postal_code'] ?? null,
                'country' => $fulfillment['shipping_country'] ?? null,
            ]);
        }

        $profile->save();

        return $profile;
    }

    private function validateSnapshotItem(array $item): void
    {
        if (
            ! in_array($item['source'] ?? null, CartItem::SOURCES, true)
            || (int) ($item['source_id'] ?? 0) <= 0
            || blank($item['source_sku'] ?? null)
            || (int) ($item['quantity'] ?? 0) <= 0
            || (float) ($item['unit_price'] ?? -1) < 0
        ) {
            throw new RuntimeException('A checkout item is invalid.');
        }
    }

    private function commitInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->source === CartItem::SOURCE_ECLISE) {
                $product = Product::query()
                    ->whereKey($item->source_id)
                    ->where('sku', $item->source_sku)
                    ->lockForUpdate()
                    ->first();

                if (! $product || $product->quantity < $item->quantity) {
                    throw new RuntimeException("Insufficient inventory for {$item->source_sku}.");
                }

                $product->decrement('quantity', $item->quantity);

                if ($product->fresh()->quantity === 0) {
                    $product->update(['status' => 'Out of Stock']);
                }

                continue;
            }

            $device = MobileSentrixDevice::query()
                ->where('entity_id', $item->source_id)
                ->where('sku', $item->source_sku)
                ->lockForUpdate()
                ->first();

            if (! $device || $device->availableQuantity() < $item->quantity) {
                throw new RuntimeException("Insufficient inventory for {$item->source_sku}.");
            }

            $updates = [];

            if ($device->available_qty !== null) {
                $updates['available_qty'] = max(0, $device->available_qty - $item->quantity);
            }

            if ($device->qty !== null) {
                $updates['qty'] = max(0, $device->qty - $item->quantity);
            }

            if ($updates !== []) {
                $device->update($updates);
            }
        }
    }

    private function markExistingOrderPaid(Order $order, Payment $payment): void
    {
        if (! $order->inventory_committed_at) {
            $this->commitInventory($order);
        }

        $order->update([
            'status' => $order->status === 'Pending' ? 'Paid' : $order->status,
            'payment_status' => 'paid',
            'payment_provider' => $payment->gateway,
            'payment_gateway' => $payment->gateway,
            'payment_reference' => $payment->gateway_reference_id,
            'payment_amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at ?? now(),
            'inventory_committed_at' => $order->inventory_committed_at ?: now(),
        ]);

        $order->statusUpdates()->create([
            'status' => $order->status,
            'note' => 'Payment confirmed through '.$payment->gatewayLabel().'.',
            'is_customer_visible' => true,
        ]);

        if ($order->cart) {
            $order->cart->items()->delete();
            $order->cart->delete();
        }
    }

    private function markRepairPaid(RepairBooking $repair, Payment $payment): void
    {
        $amountPaid = round((float) $repair->amount_paid + (float) $payment->amount, 2);
        $total = (float) ($repair->total_amount ?: $repair->repair_total);
        $balanceDue = round(max(0, $total - $amountPaid), 2);
        $paymentStatus = $balanceDue <= 0.01 ? 'paid' : 'partially_paid';

        $repair->update([
            'amount_paid' => min($amountPaid, $total),
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
            'payment_gateway' => $payment->gateway,
            'payment_amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $paymentStatus === 'paid' ? ($payment->paid_at ?? now()) : $repair->paid_at,
        ]);

        $repair->statusUpdates()->create([
            'status' => $repair->repair_status ?: $repair->status,
            'note' => 'Payment confirmed through '.$payment->gatewayLabel().'.',
            'is_customer_visible' => true,
        ]);
    }

    private function legacyAddressValue(array $data): ?string
    {
        if (($data['fulfillment_method'] ?? null) !== 'shipping') {
            return null;
        }

        return implode("\n", array_filter([
            $data['shipping_full_name'] ?? null,
            $data['shipping_address_line1'] ?? null,
            $data['shipping_address_line2'] ?? null,
            trim(implode(', ', array_filter([
                $data['shipping_city'] ?? null,
                $data['shipping_province'] ?? null,
                $data['shipping_postal_code'] ?? null,
            ]))),
            $data['shipping_country'] ?? null,
        ]));
    }
}
