<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Repair;
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
            'payment' => $payment->load('payable', 'invoice.items', 'transactions'),
        ]);
    }

    public function stripeSuccess(Payment $payment, Request $request)
    {
        $this->authorizePaymentView($payment);

        return view('payments.show', [
            'payment' => $payment->fresh('payable', 'invoice.items', 'transactions'),
            'statusMessage' => 'Stripe returned successfully. Payment will be confirmed by webhook before the order is marked paid.',
        ]);
    }

    public function paypalReturn(Payment $payment, PaymentGatewayService $gateways)
    {
        $this->authorizePaymentView($payment);

        try {
            if ($payment->status === 'pending' && $payment->paypal_order_id) {
                $payload = $gateways->capturePayPalOrder($payment);
                $capture = collect($payload['purchase_units'][0]['payments']['captures'] ?? [])->first();
                $payment->update([
                    'status' => 'processing',
                    'paypal_capture_id' => $capture['id'] ?? $payment->paypal_capture_id,
                    'gateway_reference_id' => $capture['id'] ?? $payment->gateway_reference_id,
                    'gateway_payment_id' => $capture['id'] ?? $payment->gateway_payment_id,
                    'gateway_reference' => $capture['id'] ?? $payment->gateway_reference,
                    'raw_response' => $payload,
                ]);
            }
        } catch (RuntimeException $exception) {
            return view('payments.show', [
                'payment' => $payment->fresh('payable', 'invoice.items', 'transactions'),
                'statusMessage' => $exception->getMessage(),
            ]);
        }

        return view('payments.show', [
            'payment' => $payment->fresh('payable', 'invoice.items', 'transactions'),
            'statusMessage' => 'PayPal returned successfully. Payment will be confirmed by server-side webhook verification before the record is marked paid.',
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

    public function receipt(Payment $payment)
    {
        $this->authorizePaymentView($payment);
        abort_unless(filled($payment->receipt_number), 404);

        return view('payments.receipt', [
            'payment' => $payment->load('invoice.items', 'invoice.payments', 'customer', 'payable.customer', 'receiver', 'verifier'),
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
            || ($payable instanceof Repair && $payable->customer?->user_id === $user?->id),
            403,
        );
    }
}
