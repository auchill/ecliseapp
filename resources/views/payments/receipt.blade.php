@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', $payment->receipt_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                    <div>
                        <p class="eyebrow">Receipt</p>
                        <h1 class="display-6 fw-bold mb-0">{{ $payment->receipt_number }}</h1>
                    </div>
                    <button class="btn btn-outline-primary" onclick="window.print()">Print</button>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <h2 class="h5 fw-bold">Eclise Technology Inc.</h2>
                        <p class="mb-1"><strong>Payment:</strong> {{ $payment->payment_number }}</p>
                        <p class="mb-1"><strong>Invoice:</strong> {{ $payment->invoice?->invoice_number ?? 'N/A' }}</p>
                        <p class="mb-1"><strong>Customer:</strong> {{ $payment->customer?->full_name ?? $payment->payable?->customer?->full_name }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Method:</strong> {{ ucfirst(str_replace('_', ' ', $payment->method ?: $payment->gateway)) }}</p>
                        <p class="mb-1"><strong>Provider:</strong> {{ ucfirst(str_replace('_', ' ', $payment->provider ?: $payment->gateway)) }}</p>
                        <p class="mb-1"><strong>Reference:</strong> {{ $payment->manual_reference ?: $payment->gateway_reference ?: $payment->gateway_reference_id }}</p>
                        <p class="mb-1"><strong>Paid:</strong> {{ $payment->paid_at?->format('M j, Y g:i A') }}</p>
                    </div>
                </div>
                <hr>
                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between"><span>Invoice total</span><strong>{{ \App\Support\Money::format($payment->invoice?->total ?? $payment->amount, $payment->currency) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>This payment</span><strong>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>Total paid</span><strong>{{ \App\Support\Money::format($payment->invoice?->amount_paid ?? $payment->amount, $payment->currency) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>Refunded</span><strong>{{ \App\Support\Money::format($payment->invoice?->refunded_amount ?? $payment->refunded_amount, $payment->currency) }}</strong></div>
                        <div class="d-flex justify-content-between fs-5"><span>Balance</span><strong>{{ \App\Support\Money::format($payment->invoice?->balance_due ?? 0, $payment->currency) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
