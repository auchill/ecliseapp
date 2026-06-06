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
                <div class="col-lg-7">
                    <div class="surface p-4 mb-4">
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
                            <p class="mb-1">Shipping: <strong>${{ number_format($order->shipping_cost, 2) }}</strong></p>
                            <p class="h4 fw-bold">Total: ${{ number_format($order->total, 2) }}</p>
                        </div>
                    </div>

                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Status Timeline</h2>
                        <div class="timeline">
                            @forelse ($order->statusUpdates as $update)
                                <div class="timeline-item">
                                    <h3 class="h6 mb-1">{{ $update->status }}</h3>
                                    <p class="muted small mb-0">{{ $update->note }}</p>
                                    @if ($update->delivery_carrier || $update->tracking_number)
                                        <p class="small mb-0">{{ $update->delivery_carrier }} {{ $update->tracking_number }}</p>
                                    @endif
                                    <span class="small muted">{{ $update->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                            @empty
                                <p class="muted mb-0">No status updates yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <form class="surface p-4 mb-4" method="POST" action="{{ route('admin.orders.update', $order) }}">
                        @csrf
                        @method('PATCH')
                        <h2 class="h5 fw-bold">Update Order</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select" id="status" name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_status">Payment status</label>
                                <input class="form-control" id="payment_status" name="payment_status" value="{{ old('payment_status', $order->payment_status) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fulfillment_method">Fulfillment</label>
                                <select class="form-select" id="fulfillment_method" name="fulfillment_method" required>
                                    @foreach ($fulfillmentMethods as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fulfillment_method', $order->fulfillment_method) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_cost">Shipping cost</label>
                                <input class="form-control" id="shipping_cost" name="shipping_cost" type="number" min="0" step="0.01" value="{{ old('shipping_cost', $order->shipping_cost) }}" required>
                            </div>

                            <div class="col-12"><hr><h3 class="h6 fw-bold">Shipping Address</h3></div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_full_name">Full name</label>
                                <input class="form-control" id="shipping_full_name" name="shipping_full_name" value="{{ old('shipping_full_name', $order->shipping_full_name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_phone">Phone</label>
                                <input class="form-control" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone', $order->shipping_phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_email">Email</label>
                                <input class="form-control" id="shipping_email" name="shipping_email" type="email" value="{{ old('shipping_email', $order->shipping_email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_country">Country</label>
                                <input class="form-control" id="shipping_country" name="shipping_country" value="{{ old('shipping_country', $order->shipping_country) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="shipping_address_line1">Street address</label>
                                <input class="form-control" id="shipping_address_line1" name="shipping_address_line1" value="{{ old('shipping_address_line1', $order->shipping_address_line1) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="shipping_address_line2">Apartment/unit</label>
                                <input class="form-control" id="shipping_address_line2" name="shipping_address_line2" value="{{ old('shipping_address_line2', $order->shipping_address_line2) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="shipping_city">City</label>
                                <input class="form-control" id="shipping_city" name="shipping_city" value="{{ old('shipping_city', $order->shipping_city) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="shipping_province">Province/state</label>
                                <input class="form-control" id="shipping_province" name="shipping_province" value="{{ old('shipping_province', $order->shipping_province) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="shipping_postal_code">Postal code</label>
                                <input class="form-control" id="shipping_postal_code" name="shipping_postal_code" value="{{ old('shipping_postal_code', $order->shipping_postal_code) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="delivery_carrier">Delivery carrier/agent</label>
                                <input class="form-control" id="delivery_carrier" name="delivery_carrier" value="{{ old('delivery_carrier', $order->delivery_carrier) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tracking_number">Tracking number</label>
                                <input class="form-control" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tracking_notes">Tracking notes</label>
                                <textarea class="form-control" id="tracking_notes" name="tracking_notes" rows="3">{{ old('tracking_notes', $order->tracking_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customer_notes">Customer-visible notes</label>
                                <textarea class="form-control" id="customer_notes" name="customer_notes" rows="3">{{ old('customer_notes', $order->customer_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="admin_notes">Admin notes</label>
                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="status_note">Timeline note</label>
                                <textarea class="form-control" id="status_note" name="status_note" rows="3">{{ old('status_note') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" id="is_customer_visible" name="is_customer_visible" type="checkbox" value="1" checked>
                                    <label class="form-check-label" for="is_customer_visible">Show timeline note to customer and email customer</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save Order</button>
                            </div>
                        </div>
                    </form>

                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Customer Details</h2>
                        <p class="mb-1"><strong>Email:</strong> {{ $order->email }}</p>
                        <p class="mb-1"><strong>Phone:</strong> {{ $order->phone }}</p>
                        <p class="mb-1"><strong>Fulfillment:</strong> {{ $order->fulfillmentLabel() }}</p>
                        <p class="mb-0"><strong>Payment reference:</strong> {{ $order->payment_reference ?: 'Pending' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
