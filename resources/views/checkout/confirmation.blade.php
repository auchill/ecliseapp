@extends('layouts.app')

@section('title', 'Order Confirmation')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4 p-lg-5">
                <p class="eyebrow">Order Created</p>
                <h1 class="display-6 fw-bold">Order {{ $order->order_number }}</h1>
                <p class="muted fs-5">Your order was saved with Square placeholder reference {{ $order->payment_reference }}.</p>
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
                    <p class="h4 fw-bold">Total: ${{ number_format($order->total, 2) }}</p>
                    <span class="status-pill">{{ $order->status }}</span>
                </div>
            </div>
        </div>
    </section>
@endsection
