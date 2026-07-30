@extends('layouts.admin')

@section('title', 'Pending Verification')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4">
                <p class="eyebrow">Payments</p>
                <h1 class="display-6 fw-bold mb-0">Pending Verification</h1>
            </div>
            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Payment</th><th>Invoice</th><th>Customer</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_number }}</td>
                                <td>{{ $payment->invoice?->invoice_number }}</td>
                                <td>{{ $payment->customer?->full_name }}</td>
                                <td>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                                <td>{{ $payment->manual_reference ?: 'N/A' }}</td>
                                <td class="text-end">
                                    <form class="d-inline" method="POST" action="{{ route('admin.payments.verify-interac', $payment) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm">Verify</button></form>
                                    <form class="d-inline" method="POST" action="{{ route('admin.payments.reject-interac', $payment) }}">@csrf @method('PATCH')<input type="hidden" name="reason" value="Payment could not be verified."><button class="btn btn-outline-danger btn-sm">Reject</button></form>
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.payments.show', $payment) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No payments are pending verification.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $payments->links() }}
            </div>
        </div>
    </section>
@endsection
