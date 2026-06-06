@extends('layouts.app')

@section('title', 'Order Confirmation')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4 p-lg-5">
                <p class="eyebrow">Order Created</p>
                <h1 class="display-6 fw-bold">Order {{ $order->order_number }}</h1>
                <p class="muted fs-5">Your order was saved with Square placeholder reference {{ $order->payment_reference }}.</p>
                @if ($order->isShipping())
                    <div class="alert alert-info">Your order will be shipped after processing. Tracking details will appear here when available.</div>
                @else
                    <div class="alert alert-info">Your order will be prepared for store pickup. You will receive an email when it is ready.</div>
                @endif
                <div class="table-responsive mt-4">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>{{ $item->product_name }}</td>
                                    <td>{{ $item->sku }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>${{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <p class="mb-1">Subtotal: <strong>${{ number_format($order->subtotal, 2) }}</strong></p>
                    <p class="mb-1">Tax: <strong>${{ number_format($order->tax, 2) }}</strong></p>
                    <p class="mb-1">Shipping: <strong>${{ number_format($order->shipping_cost, 2) }}</strong></p>
                    <p class="h4 fw-bold">Total: ${{ number_format($order->total, 2) }}</p>
                    <span class="status-pill">{{ $order->status }}</span>
                </div>
                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <h2 class="h5 fw-bold">Fulfillment</h2>
                        <p class="mb-1"><strong>Method:</strong> {{ $order->fulfillmentLabel() }}</p>
                        <p class="mb-1"><strong>Payment status:</strong> {{ $order->payment_status }}</p>
                        @if ($order->delivery_carrier)
                            <p class="mb-1"><strong>Delivery carrier:</strong> {{ $order->delivery_carrier }}</p>
                        @endif
                        @if ($order->tracking_number)
                            <p class="mb-1"><strong>Tracking number:</strong> {{ $order->tracking_number }}</p>
                        @endif
                    </div>
                    @if ($order->isShipping())
                        <div class="col-lg-6">
                            <h2 class="h5 fw-bold">Shipping Address</h2>
                            @foreach ($order->shippingAddressLines() as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="mt-4">
                    <a class="btn btn-outline-primary" href="{{ route('orders.track') }}"><i class="bi bi-search me-2"></i>Track Order</a>
                </div>
            </div>
        </div>
    </section>
@endsection
