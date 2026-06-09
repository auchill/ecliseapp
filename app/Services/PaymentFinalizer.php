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
        if ($payable instanceof RepairBooking) {
            $payable->update(['payment_status' => $payable->amount_paid > 0 ? 'partially_paid' : 'unpaid']);
        } else {
            $payable?->update(['payment_status' => $status]);
        }

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
}
