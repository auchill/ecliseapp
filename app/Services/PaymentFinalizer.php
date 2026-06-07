<?php

namespace App\Services;

use App\Mail\AdminPaymentReceivedMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentFinalizer
{
    public function markPaid(Payment $payment, array $attributes = []): Payment
    {
        $sendMail = false;

        $payment = DB::transaction(function () use ($payment, $attributes, &$sendMail): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                return $payment;
            }

            $payment->update(array_merge([
                'status' => 'paid',
                'paid_at' => now(),
            ], $attributes));

            $payable = $payment->payable()->lockForUpdate()->first();

            if ($payable instanceof Order) {
                $this->markOrderPaid($payable, $payment);
            }

            if ($payable instanceof RepairBooking) {
                $this->markRepairPaid($payable, $payment);
            }

            $sendMail = true;

            return $payment->fresh('payable');
        });

        if ($sendMail) {
            Mail::to($payment->payable->email)->send(new PaymentReceiptMail($payment->fresh('payable')));

            User::query()
                ->where('role', 'admin')
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
        $payable?->update(['payment_status' => $status]);

        return $payment->fresh('payable');
    }

    private function markOrderPaid(Order $order, Payment $payment): void
    {
        if (! $order->inventory_committed_at) {
            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product) {
                    $product->decrement('quantity', min($product->quantity, $item->quantity));

                    if ($product->fresh()->quantity === 0) {
                        $product->update(['status' => 'Out of Stock']);
                    }
                }
            }
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_provider' => $payment->gateway,
            'payment_gateway' => $payment->gateway,
            'payment_reference' => $payment->gateway_reference_id,
            'payment_amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at ?? now(),
            'inventory_committed_at' => $order->inventory_committed_at ?: now(),
        ]);

        $order->cart?->update(['status' => 'converted']);

        $order->statusUpdates()->create([
            'status' => $order->status,
            'note' => 'Payment confirmed through '.$payment->gatewayLabel().'.',
            'is_customer_visible' => true,
        ]);
    }

    private function markRepairPaid(RepairBooking $repair, Payment $payment): void
    {
        $repair->update([
            'payment_status' => 'paid',
            'payment_gateway' => $payment->gateway,
            'payment_amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        $repair->statusUpdates()->create([
            'status' => $repair->status,
            'note' => 'Payment confirmed through '.$payment->gatewayLabel().'.',
            'is_customer_visible' => true,
        ]);
    }
}
