@extends('layouts.admin')

@section('title', $customer->name)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Customer</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $customer->name }}</h1>
                    <p class="muted mb-0">{{ $customer->email }}</p>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.customers.index') }}"><i class="bi bi-arrow-left me-2"></i>Customers</a>
            </div>

            <div class="row g-4">
                @if ($customer->customer)
                    <div class="col-12">
                        <div class="surface p-4">
                            <h2 class="h4 fw-bold mb-3">Customer Profile</h2>
                            <div class="row g-3">
                                <div class="col-md-4"><strong>Phone</strong><div>{{ $customer->customer->phone ?: 'Not provided' }}</div></div>
                                <div class="col-md-4"><strong>Status</strong><div>{{ ucfirst($customer->customer->status) }}</div></div>
                                <div class="col-md-4"><strong>Customer since</strong><div>{{ $customer->customer->customer_since?->format('M j, Y') }}</div></div>
                                <div class="col-12">
                                    <strong>Address</strong>
                                    <div>{{ collect([$customer->customer->street_address, $customer->customer->address_line_2, $customer->customer->city, $customer->customer->province, $customer->customer->postal_code, $customer->customer->country])->filter()->implode(', ') ?: 'Not provided' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <h2 class="h4 fw-bold mb-3">Repairs</h2>
                        @forelse ($customer->repairBookings as $repair)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $repair->tracking_number }}</strong>
                                    <div class="small muted">{{ $repair->deviceLabel() }}</div>
                                </div>
                                <span class="status-pill">{{ $repair->statusLabel() }}</span>
                            </div>
                        @empty
                            <p class="muted mb-0">No repairs found.</p>
                        @endforelse
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <h2 class="h4 fw-bold mb-3">Orders</h2>
                        @forelse ($customer->orders as $order)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $order->order_number }}</strong>
                                    <div class="small muted">{{ $order->created_at->format('M j, Y') }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="status-pill">{{ $order->status }}</span>
                                    <div class="small fw-bold mt-1">${{ number_format($order->total, 2) }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="muted mb-0">No orders found.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
