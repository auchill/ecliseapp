@extends('layouts.admin')

@section('title', 'Refunds')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Refunds</h1></div>
            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Refund</th><th>Payment</th><th>Customer</th><th>Status</th><th>Amount</th><th>Requested</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($refunds as $refund)
                            <tr>
                                <td>{{ $refund->refund_number }}</td>
                                <td>{{ $refund->payment?->payment_number }}</td>
                                <td>{{ $refund->payment?->customer?->full_name }}</td>
                                <td><span class="status-pill">{{ ucfirst(str_replace('_', ' ', $refund->status)) }}</span></td>
                                <td>{{ \App\Support\Money::format($refund->amount, $refund->currency) }}</td>
                                <td>{{ $refund->requested_at?->format('M j, Y') }}</td>
                                <td class="text-end">
                                    @if ($refund->status === 'pending')
                                        <form class="d-inline" method="POST" action="{{ route('admin.refunds.approve', $refund) }}">@csrf @method('PATCH')<button class="btn btn-outline-primary btn-sm">Approve</button></form>
                                    @endif
                                    @if (in_array($refund->status, ['pending', 'approved', 'processing'], true))
                                        <form class="d-inline" method="POST" action="{{ route('admin.refunds.process', $refund) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm">Process</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No refunds found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $refunds->links() }}
            </div>
        </div>
    </section>
@endsection
