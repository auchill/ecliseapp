@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Payments</h1>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.payments.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="gateway">Gateway</label>
                        <select class="form-select" id="gateway" name="gateway">
                            <option value="">All gateways</option>
                            @foreach ($gateways as $value => $label)
                                <option value="{{ $value }}" @selected(request('gateway') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button>
                    </div>
                </div>
            </form>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th>Gateway</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Customer</th>
                                <th>Reference</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>#{{ $payment->id }}</td>
                                    <td>{{ $payment->gatewayLabel() }}</td>
                                    <td><span class="status-pill">{{ $payment->statusLabel() }}</span></td>
                                    <td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->payable?->customer_name }}</td>
                                    <td>{{ $payment->gateway_reference_id ?: 'Pending' }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.payments.show', $payment) }}"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7">No payments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $payments->links() }}
            </div>
        </div>
    </section>
@endsection
