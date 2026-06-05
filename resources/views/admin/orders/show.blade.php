@extends('layouts.app')

@section('title', 'Order '.$order->order_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Order {{ $order->order_number }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $order->customer_name }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.orders.index') }}"><i class="bi bi-arrow-left me-2"></i>Orders</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="surface p-4">
                        <h2 class="h4 fw-bold mb-3">Order Items</h2>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td>{{ $item->product_name }}</td>
                                            <td>{{ $item->sku }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format($item->unit_price, 2) }}</td>
                                            <td>${{ number_format($item->line_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <p class="mb-1">Subtotal: <strong>${{ number_format($order->subtotal, 2) }}</strong></p>
                            <p class="mb-1">Tax: <strong>${{ number_format($order->tax, 2) }}</strong></p>
                            <p class="h4 fw-bold">Total: ${{ number_format($order->total, 2) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <form class="surface p-4 mb-4" method="POST" action="{{ route('admin.orders.update', $order) }}">
                        @csrf
                        @method('PATCH')
                        <h2 class="h5 fw-bold">Update Order</h2>
                        <div class="mb-3">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $order->notes) }}</textarea>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save Order</button>
                    </form>

                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Customer Details</h2>
                        <p class="mb-1"><strong>Email:</strong> {{ $order->email }}</p>
                        <p class="mb-1"><strong>Phone:</strong> {{ $order->phone }}</p>
                        <p class="mb-1"><strong>Address:</strong> {{ $order->address ?: 'Not provided' }}</p>
                        <p class="mb-0"><strong>Payment:</strong> {{ $order->payment_reference ?: 'Pending' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
