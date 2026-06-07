@extends('layouts.app')

@section('title', 'Admin Orders')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Order Management</h1>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.orders.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Order, customer, email">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
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
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Gateway</th>
                                <th>Shipping</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td><span class="status-pill">{{ $order->status }}</span></td>
                                    <td>{{ ucfirst($order->payment_status) }}</td>
                                    <td>{{ $order->latestPayment?->gatewayLabel() ?? ucfirst($order->payment_gateway ?? 'Pending') }}</td>
                                    <td>{{ $order->isShipping() ? ($order->shipping_method_name ?: 'Shipping') : 'Pickup' }}</td>
                                    <td>${{ number_format($order->total, 2) }}</td>
                                    <td>{{ $order->created_at->format('M j, Y') }}</td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.show', $order) }}"><i class="bi bi-eye"></i><span class="visually-hidden">Open</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9">No orders found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $orders->links() }}
            </div>
        </div>
    </section>
@endsection
