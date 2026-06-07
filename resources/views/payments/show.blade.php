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
                            <tr><th scope="row">Gateway</th><td>{{ $payment->gatewayLabel() }}</td></tr>
                            <tr><th scope="row">Amount</th><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
                            <tr><th scope="row">Reference</th><td>{{ $payment->gateway_reference_id ?: 'Not available yet' }}</td></tr>
                            <tr><th scope="row">Paid at</th><td>{{ $payment->paid_at?->format('M j, Y g:i A') ?? 'Not paid yet' }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if ($payment->payable instanceof \App\Models\Order)
                        <a class="btn btn-primary" href="{{ route('checkout.confirmation', $payment->payable) }}">View Order</a>
                    @elseif ($payment->payable instanceof \App\Models\RepairBooking)
                        <a class="btn btn-primary" href="{{ route('repairs.confirmation', $payment->payable) }}">View Repair</a>
                    @endif
                    <a class="btn btn-outline-primary" href="{{ route('home') }}">Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
