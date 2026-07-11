@extends('layouts.admin')

@section('title', 'Admin Repairs')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Repair Management</h1>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.repairs.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Repair number, customer, device">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="payment_status">Payment</label>
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="">All</option>
                            @foreach ($paymentStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                </div>
            </form>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Repair number</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Gateway</th>
                                <th>Shipping</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($repairs as $repair)
                                <tr>
                                    <td>{{ $repair->repair_number }}</td>
                                    <td>{{ $repair->customer_name }}</td>
                                    <td>{{ $repair->deviceLabel() }}</td>
                                    <td><span class="status-pill">{{ $repair->statusLabel() }}</span></td>
                                    <td>{{ $repair->paymentStatusLabel() }}</td>
                                    <td>{{ $repair->latestPayment?->gatewayLabel() ?? ucfirst($repair->payment_gateway ?? 'Not required') }}</td>
                                    <td>{{ $repair->isShipping() ? ($repair->shipping_method_name ?: 'Shipping') : 'Pickup' }}</td>
                                    <td>{{ $repair->created_at->format('M j, Y') }}</td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.repairs.show', $repair) }}"><i class="bi bi-pencil-square"></i><span class="visually-hidden">Open</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9">No repairs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $repairs->links() }}
            </div>
        </div>
    </section>
@endsection
