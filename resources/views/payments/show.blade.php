@extends('layouts.app')

@section('title', 'Payment Status')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4 p-lg-5">
                <p class="eyebrow">Payment</p>
                <h1 class="display-6 fw-bold">{{ $payment->statusLabel() }}</h1>
                @isset($statusMessage)
                    <div class="alert alert-info">{{ $statusMessage }}</div>
                @endisset
                @if ($payment->status === 'pending')
                    <div class="alert alert-warning">Payment is pending. Orders are marked paid only after server-side gateway confirmation.</div>
                @elseif ($payment->status === 'paid')
                    <div class="alert alert-success">Payment has been confirmed.</div>
                @else
                    <div class="alert alert-danger">Payment status is {{ $payment->statusLabel() }}.</div>
                @endif

                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <tr><th scope="row">Payment</th><td>{{ $payment->payment_number ?: '#'.$payment->id }}</td></tr>
                            <tr><th scope="row">Invoice</th><td>
                                @if ($payment->invoice)
                                    <a href="{{ route('invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>
                                @else
                                    Not available yet
                                @endif
                            </td></tr>
                            <tr><th scope="row">Gateway</th><td>{{ $payment->gatewayLabel() }}</td></tr>
                            <tr><th scope="row">Amount</th><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
                            <tr><th scope="row">Reference</th><td>{{ $payment->gateway_reference_id ?: 'Not available yet' }}</td></tr>
                            <tr><th scope="row">Paid at</th><td>{{ $payment->paid_at?->format('M j, Y g:i A') ?? 'Not paid yet' }}</td></tr>
                        </tbody>
                    </table>
                </div>

                @if ($payment->status === 'pending_verification' && $payment->method === 'interac')
                    @php $settings = app(\App\Services\Payments\PaymentSettingsService::class); @endphp
                    <div class="alert alert-info">
                        <strong>Interac e-Transfer instructions</strong>
                        <p class="mb-1">Send exactly {{ \App\Support\Money::format($payment->amount, $payment->currency) }} and include invoice {{ $payment->invoice?->invoice_number }} in the message.</p>
                        <p class="mb-1">Recipient: {{ $settings->get('interac_recipient_name') ?: 'Configured by Eclise' }} {{ $settings->get('interac_recipient_email') ? '<'.$settings->get('interac_recipient_email').'>' : '' }}</p>
                        <p class="mb-0">{{ $settings->get('interac_instructions') }}</p>
                    </div>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @if ($payment->receipt_number)
                        <a class="btn btn-outline-primary" href="{{ route('payments.receipt', $payment) }}">View Receipt</a>
                    @endif
                    @if ($payment->payable instanceof \App\Models\Order)
                        <a class="btn btn-primary" href="{{ route('checkout.confirmation', $payment->payable) }}">View Order</a>
                    @elseif ($payment->payable instanceof \App\Models\Repair)
                        <a class="btn btn-primary" href="{{ route('repairs.confirmation', $payment->payable) }}">View Repair</a>
                    @endif
                    <a class="btn btn-outline-primary" href="{{ route('home') }}">Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
