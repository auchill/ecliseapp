@extends('layouts.admin')

@section('title', 'Payment '.($payment->payment_number ?: '#'.$payment->id))

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">{{ $payment->payment_number ?: 'Payment #'.$payment->id }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $payment->gatewayLabel() }} · {{ $payment->statusLabel() }}</h1>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($payment->receipt_number)
                        <a class="btn btn-outline-primary" href="{{ route('payments.receipt', $payment) }}"><i class="bi bi-receipt me-2"></i>Receipt</a>
                    @endif
                    <a class="btn btn-outline-primary" href="{{ route('admin.payments.index') }}"><i class="bi bi-arrow-left me-2"></i>Payments</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Payment Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Payment number</th><td>{{ $payment->payment_number ?: '#'.$payment->id }}</td></tr>
                                    <tr><th scope="row">Gateway</th><td>{{ $payment->gatewayLabel() }}</td></tr>
                                    <tr><th scope="row">Source</th><td>{{ $payment->sourceLabel() }}</td></tr>
                                    <tr><th scope="row">Purpose</th><td>{{ $payment->purpose ? ucfirst(str_replace('_', ' ', $payment->purpose)) : 'N/A' }}</td></tr>
                                    <tr><th scope="row">Method</th><td>{{ $payment->method ? ucfirst(str_replace('_', ' ', $payment->method)) : 'N/A' }}</td></tr>
                                    <tr><th scope="row">Provider</th><td>{{ $payment->provider ? ucfirst(str_replace('_', ' ', $payment->provider)) : 'N/A' }}</td></tr>
                                    <tr><th scope="row">Status</th><td>{{ $payment->statusLabel() }}</td></tr>
                                    <tr><th scope="row">Amount</th><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
                                    <tr><th scope="row">Gateway reference</th><td>{{ $payment->gateway_reference ?: $payment->gateway_reference_id ?: 'Pending' }}</td></tr>
                                    <tr><th scope="row">Stripe session</th><td>{{ $payment->stripe_checkout_session_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">Stripe payment intent</th><td>{{ $payment->stripe_payment_intent_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">PayPal order</th><td>{{ $payment->paypal_order_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">PayPal capture</th><td>{{ $payment->paypal_capture_id ?: 'N/A' }}</td></tr>
                                    <tr><th scope="row">Proof</th><td>
                                        @if ($payment->proof_path)
                                            <a href="{{ route('admin.payments.proof', $payment) }}">{{ $payment->proof_original_name ?: 'Download proof' }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td></tr>
                                    <tr><th scope="row">Paid at</th><td>{{ $payment->paid_at?->format('M j, Y g:i A') ?? 'Not paid yet' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Payable</h2>
                        <p class="mb-1"><strong>Customer:</strong> {{ $payment->customer?->full_name ?? $payment->payable?->customer?->full_name ?? 'Customer unavailable' }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $payment->customer?->email ?? $payment->payable?->customer?->email ?? 'Unavailable' }}</p>
                        <p class="mb-1"><strong>Invoice:</strong>
                            @if ($payment->invoice)
                                <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>
                            @else
                                No invoice linked
                            @endif
                        </p>
                        <p class="mb-1"><strong>Payment status:</strong> {{ $payment->payable?->payment_status }}</p>
                        @if ($payment->payable instanceof \App\Models\Order)
                            <a class="btn btn-outline-primary mt-3" href="{{ route('admin.orders.show', $payment->payable) }}">View Order</a>
                        @elseif ($payment->payable instanceof \App\Models\Repair)
                            <a class="btn btn-outline-primary mt-3" href="{{ route('admin.repairs.show', $payment->payable) }}">View Repair</a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="surface p-4 mt-4">
                <h2 class="h5 fw-bold">Transactions</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Provider reference</th>
                                <th>Processed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payment->transactions as $transaction)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $transaction->status)) }}</td>
                                    <td>{{ strtoupper($transaction->currency) }} ${{ number_format($transaction->amount, 2) }}</td>
                                    <td>{{ $transaction->provider_reference ?: $transaction->provider_transaction_id ?: 'N/A' }}</td>
                                    <td>{{ $transaction->processed_at?->format('M j, Y g:i A') ?? 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No transactions recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($payment->canRequestRefund())
                <div class="surface p-4 mt-4">
                    <h2 class="h5 fw-bold">Request Refund</h2>
                    <form method="POST" action="{{ route('admin.payments.refunds.store', $payment) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="refund_amount">Amount</label>
                                <input class="form-control" id="refund_amount" name="amount" type="number" min="0.01" step="0.01" max="{{ max(0, (float) $payment->amount - (float) $payment->refunded_amount) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="reason_code">Reason code</label>
                                <input class="form-control" id="reason_code" name="reason_code" value="customer_request">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="reason">Reason</label>
                                <input class="form-control" id="reason" name="reason" required>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-danger" type="submit">Request Refund</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </section>
@endsection
