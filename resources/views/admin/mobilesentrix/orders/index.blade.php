@extends('layouts.admin')

@section('title', 'MobileSentrix Orders')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4">
                <p class="eyebrow">MobileSentrix</p>
                <h1 class="display-6 fw-bold mb-1">Procurement Orders</h1>
                <p class="muted mb-0">Internal Eclise procurement orders. Orders are placed with MobileSentrix manually at this stage.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->has('procurement'))
                <div class="alert alert-danger">{{ $errors->first('procurement') }}</div>
            @endif

            <form class="surface p-4 mb-4" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="order_status">Status</label>
                        <select class="form-select" id="order_status" name="order_status">
                            <option value="">All</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(request('order_status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="order_number">Order #</label>
                        <input class="form-control" id="order_number" name="order_number" value="{{ request('order_number') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="supplier_order_number">Supplier Order #</label>
                        <input class="form-control" id="supplier_order_number" name="supplier_order_number" value="{{ request('supplier_order_number') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="tracking_number">Tracking #</label>
                        <input class="form-control" id="tracking_number" name="tracking_number" value="{{ request('tracking_number') }}">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.mobilesentrix-orders.index') }}">Reset</a>
                    </div>
                </div>
            </form>

            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Supplier Order #</th>
                            <th>Status</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th>Tracking #</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->supplier_order_number ?: '—' }}</td>
                                <td><span class="badge {{ $order->statusBadgeClass() }}">{{ $order->order_status }}</span></td>
                                <td class="text-end">{{ $order->items_count }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($order->subtotal, $order->currency) }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($order->total, $order->currency) }}</td>
                                <td class="text-end">
                                    {{ \App\Support\Money::format($order->payment_amount, $order->currency) }}
                                    @if ($order->paid_at)
                                        <span class="muted small d-block">{{ $order->paid_at->format('M j, Y') }}</span>
                                    @endif
                                </td>
                                <td class="text-break">{{ $order->tracking_number ?: '—' }}</td>
                                <td>{{ $order->created_at?->format('M j, Y') }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.mobilesentrix-orders.show', $order) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10">No procurement orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $orders->links() }}
            </div>
        </div>
    </section>
@endsection
