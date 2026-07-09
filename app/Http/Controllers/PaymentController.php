<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RepairBooking;
use App\Services\PaymentFinalizer;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function show(Payment $payment)
    {
        $this->authorizePaymentView($payment);

        return view('payments.show', [
            'payment' => $payment->load('payable'),
        ]);
    }

    public function stripeSuccess(Payment $payment, Request $request)
    {
        $this->authorizePaymentView($payment);

        return view('payments.show', [
            'payment' => $payment->fresh('payable'),
            'statusMessage' => 'Stripe returned successfully. Payment will be confirmed by webhook before the order is marked paid.',
        ]);
    }

    public function paypalReturn(Payment $payment, PaymentGatewayService $gateways, PaymentFinalizer $finalizer)
    {
        $this->authorizePaymentView($payment);

        try {
            $payload = $gateways->capturePayPalOrder($payment);
            $capture = collect($payload['purchase_units'][0]['payments']['captures'] ?? [])->first();

            if (($capture['status'] ?? null) === 'COMPLETED') {
                $payment = $finalizer->markPaid($payment, [
                    'paypal_capture_id' => $capture['id'] ?? null,
                    'gateway_reference_id' => $capture['id'] ?? $payment->paypal_order_id,
                    'raw_response' => $payload,
                    'paid_at' => now(),
                ]);
            }
        } catch (RuntimeException $exception) {
            return view('payments.show', [
                'payment' => $payment->fresh('payable'),
                'statusMessage' => $exception->getMessage(),
            ]);
        }

        return view('payments.show', [
            'payment' => $payment->fresh('payable'),
            'statusMessage' => 'PayPal payment captured and verified.',
        ]);
    }

    public function cancel(Payment $payment, PaymentFinalizer $finalizer)
    {
        $this->authorizePaymentView($payment);

        if ($payment->status === 'pending') {
            $payment = $finalizer->markFailed($payment, 'cancelled');
        }

        return view('payments.show', [
            'payment' => $payment->fresh('payable'),
            'statusMessage' => 'Payment was cancelled. No order was marked paid.',
        ]);
    }

    private function authorizePaymentView(Payment $payment): void
    {
        $user = auth()->user();
        $payable = $payment->payable;

        abort_unless(
            $user?->isAdmin()
            || ($payable instanceof Cart && $payable->customer?->user_id === $user?->id)
            || ($payable instanceof Order && $payable->customer?->user_id === $user?->id)
            || ($payable instanceof RepairBooking && (! $payable->user_id || $payable->user_id === $user?->id)),
            403,
        );
    }
}
