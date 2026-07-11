@extends('layouts.admin')

@section('title', 'Payment #'.$payment->id)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Payment #{{ $payment->id }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $payment->gatewayLabel() }} · {{ $payment->statusLabel() }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.payments.index') }}"><i class="bi bi-arrow-left me-2"></i>Payments</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Payment Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Gateway</th><td>{{ $payment->gatewayLabel() }}</td></tr>
                                    <tr><th scope="row">Source</th><td>{{ $payment->sourceLabel() }}</td></tr>
                                    <tr><th scope="row">Status</th><td>{{ $payment->statusLabel() }}</td></tr>
                                    <tr><th scope="row">Amount</th><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
                                    <tr><th scope="row">Gateway reference</th><td>{{ $payment->gateway_reference_id ?: 'Pending' }}</td></tr>
                                    <tr><th scope="row">Stripe session</th><td>{{ $payment->stripe_checkout_session_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">Stripe payment intent</th><td>{{ $payment->stripe_payment_intent_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">PayPal order</th><td>{{ $payment->paypal_order_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">PayPal capture</th><td>{{ $payment->paypal_capture_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">Paid at</th><td>{{ $payment->paid_at?->format('M j, Y g:i A') ?? 'Not paid yet' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Payable</h2>
                        <p class="mb-1"><strong>Customer:</strong> {{ $payment->payable?->customer?->full_name ?? 'Customer unavailable' }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $payment->payable?->customer?->email ?? 'Unavailable' }}</p>
                        <p class="mb-1"><strong>Payment status:</strong> {{ $payment->payable?->payment_status }}</p>
                        @if ($payment->payable instanceof \App\Models\Order)
                            <a class="btn btn-outline-primary mt-3" href="{{ route('admin.orders.show', $payment->payable) }}">View Order</a>
                        @elseif ($payment->payable instanceof \App\Models\Repair)
                            <a class="btn btn-outline-primary mt-3" href="{{ route('admin.repairs.show', $payment->payable) }}">View Repair</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
