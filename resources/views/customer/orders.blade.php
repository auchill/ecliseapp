@extends('layouts.app')

@section('title', 'My Orders')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">My Orders</p>
            <h1 class="display-5 fw-bold mb-0">Order history and status.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->created_at->format('M j, Y') }}</td>
                                    <td>{{ $order->items->sum('quantity') }}</td>
                                    <td><span class="status-pill">{{ $order->status }}</span></td>
                                    <td>${{ number_format($order->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No orders found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $orders->links() }}
            </div>
        </div>
    </section>
@endsection
