@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Dashboard Overview</h1>
                </div>
            </div>

            <div class="row g-4 mb-5">
                @foreach ($metrics as $label => $metric)
                    <div class="col-sm-6 col-xl-3">
                        <a class="surface p-4 h-100 d-block text-decoration-none text-reset" href="{{ $metric['route'] }}">
                            <span class="metric-icon mb-3"><i class="bi {{ $metric['icon'] }}"></i></span>
                            <h2 class="h6 fw-bold text-uppercase">{{ $label }}</h2>
                            <p class="display-6 fw-bold mb-0">{{ $metric['value'] }}</p>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 fw-bold mb-0">Latest Repairs</h2>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.repairs.index') }}"><i class="bi bi-list"></i><span class="visually-hidden">View repairs</span></a>
                        </div>
                        @forelse ($repairs as $repair)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $repair->repair_number }}</strong>
                                    <div class="small muted">{{ $repair->customer_name }} &middot; {{ $repair->deviceLabel() }}</div>
                                </div>
                                <span class="status-pill">{{ $repair->statusLabel() }}</span>
                            </div>
                        @empty
                            <p class="muted mb-0">No repairs yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 fw-bold mb-0">Latest Orders</h2>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.index') }}"><i class="bi bi-list"></i><span class="visually-hidden">View orders</span></a>
                        </div>
                        @forelse ($orders as $order)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $order->order_number }}</strong>
                                    <div class="small muted">{{ $order->customer_name }}</div>
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
