@extends('layouts.app')

@section('title', 'Track Order')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Order Tracking</p>
            <h1 class="display-5 fw-bold mb-3">Track a shop order.</h1>
            <p class="fs-5 mb-0">Enter the order number and the email or phone number used at checkout.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 mb-5" method="POST" action="{{ route('orders.track.result') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label" for="order_number">Order number</label>
                        <input class="form-control" id="order_number" name="order_number" value="{{ old('order_number', $order->order_number ?? '') }}" placeholder="ECL-ORD-2026-0001" required>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="contact">Email or phone</label>
                        <input class="form-control" id="contact" name="contact" value="{{ old('contact') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-2"></i>Track</button>
                    </div>
                </div>
            </form>

            @isset($order)
                <div class="surface p-4 p-lg-5">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <p class="eyebrow">Order {{ $order->order_number }}</p>
                            <h2 class="display-6 fw-bold mb-0">{{ $order->customer_name }}</h2>
                        </div>
                        <span class="status-pill">{{ $order->status }}</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="table-responsive">
                                <table class="table">
                                    <tbody>
                                        <tr><th scope="row">Fulfillment</th><td>{{ $order->fulfillmentLabel() }}</td></tr>
                                        <tr><th scope="row">Order status</th><td>{{ $order->status }}</td></tr>
                                        <tr><th scope="row">Payment status</th><td>{{ $order->payment_status }}</td></tr>
                                        <tr><th scope="row">Shipping cost</th><td>${{ number_format($order->shipping_cost, 2) }}</td></tr>
                                        <tr><th scope="row">Delivery carrier</th><td>{{ $order->delivery_carrier ?: 'Not available yet' }}</td></tr>
                                        <tr><th scope="row">Tracking number</th><td>{{ $order->tracking_number ?: 'Not available yet' }}</td></tr>
                                        <tr><th scope="row">Tracking notes</th><td>{{ $order->tracking_notes ?: $order->customer_notes ?: 'No tracking notes yet.' }}</td></tr>
                                        <tr><th scope="row">Total</th><td>${{ number_format($order->total, 2) }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            @if ($order->isShipping())
                                <h3 class="h5 fw-bold mt-4">Shipping Address</h3>
                                @foreach ($order->shippingAddressLines() as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                            @else
                                <div class="alert alert-info mt-4">Your order will be prepared for store pickup. You will receive an email when it is ready.</div>
                            @endif
                        </div>
                        <div class="col-lg-6">
                            <h3 class="h5 fw-bold mb-3">Order Items</h3>
                            <div class="table-responsive mb-4">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($order->items as $item)
                                            <tr>
                                                <td>{{ $item->product_name }}</td>
                                                <td>{{ $item->quantity }}</td>
                                                <td>${{ number_format($item->line_total, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <h3 class="h5 fw-bold mb-3">Status Timeline</h3>
                            <div class="timeline">
                                @forelse ($order->publicStatusUpdates as $update)
                                    <div class="timeline-item">
                                        <h4 class="h6 mb-1">{{ $update->status }}</h4>
                                        <p class="muted small mb-0">{{ $update->note }}</p>
                                        @if ($update->delivery_carrier || $update->tracking_number)
                                            <p class="small mb-0">{{ $update->delivery_carrier }} {{ $update->tracking_number }}</p>
                                        @endif
                                        <span class="small muted">{{ $update->created_at->format('M j, Y g:i A') }}</span>
                                    </div>
                                @empty
                                    <p class="muted mb-0">No timeline updates yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endisset
        </div>
    </section>
@endsection
