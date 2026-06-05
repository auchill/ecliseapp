@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Customer Dashboard</p>
            <h1 class="display-5 fw-bold mb-0">Welcome, {{ auth()->user()->name }}.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="surface p-4 h-100">
                        <span class="metric-icon mb-3"><i class="bi bi-tools"></i></span>
                        <h2 class="h5 fw-bold">Repairs</h2>
                        <p class="display-6 fw-bold mb-0">{{ $repairs->count() }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="surface p-4 h-100">
                        <span class="metric-icon mb-3"><i class="bi bi-receipt"></i></span>
                        <h2 class="h5 fw-bold">Orders</h2>
                        <p class="display-6 fw-bold mb-0">{{ $orders->count() }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="surface p-4 h-100">
                        <span class="metric-icon mb-3"><i class="bi bi-bag"></i></span>
                        <h2 class="h5 fw-bold">Cart Items</h2>
                        <p class="display-6 fw-bold mb-0">{{ $cart?->items?->sum('quantity') ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 fw-bold mb-0">Recent Repairs</h2>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('customer.repairs') }}"><i class="bi bi-list"></i><span class="visually-hidden">View repairs</span></a>
                        </div>
                        @forelse ($repairs as $repair)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $repair->tracking_number }}</strong>
                                    <div class="small muted">{{ $repair->deviceLabel() }}</div>
                                </div>
                                <span class="status-pill">{{ $repair->status }}</span>
                            </div>
                        @empty
                            <p class="muted mb-0">No repairs yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 fw-bold mb-0">Recent Orders</h2>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('customer.orders') }}"><i class="bi bi-list"></i><span class="visually-hidden">View orders</span></a>
                        </div>
                        @forelse ($orders as $order)
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
                            <p class="muted mb-0">No orders yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
