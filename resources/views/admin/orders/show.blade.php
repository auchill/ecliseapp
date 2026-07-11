@extends('layouts.admin')

@section('title', 'Order '.$order->order_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Order {{ $order->order_number }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $order->customer?->full_name ?? 'Customer unavailable' }}</h1>
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
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="{{ $item->displayImageUrl() }}" alt="" width="44" height="44" style="object-fit: contain;" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                                    <span>{{ $item->display_name }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $item->source_sku }}</td>
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
                            <p class="mb-1">Shipping base: <strong>${{ number_format($order->shipping_base_cost, 2) }}</strong></p>
                            <p class="mb-1">Shipping discount: <strong>${{ number_format($order->shipping_discount_amount, 2) }}</strong></p>
                            <p class="mb-1">Final shipping: <strong>${{ number_format($order->shipping_cost, 2) }}</strong></p>
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
                                <label class="form-label" for="recipient_name">Full name</label>
                                <input class="form-control" id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $order->customer?->full_name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="recipient_phone">Phone</label>
                                <input class="form-control" id="recipient_phone" name="recipient_phone" value="{{ old('recipient_phone', $order->customer?->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="recipient_email">Email</label>
                                <input class="form-control" id="recipient_email" name="recipient_email" type="email" value="{{ old('recipient_email', $order->customer?->email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="country">Country</label>
                                <input class="form-control" id="country" name="country" value="{{ old('country', $order->customer?->country) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Street address</label>
                                <input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $order->customer?->street_address) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Apartment/unit</label>
                                <input class="form-control" id="address_line2" name="address_line2" value="{{ old('address_line2', $order->customer?->address_line_2) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="city">City</label>
                                <input class="form-control" id="city" name="city" value="{{ old('city', $order->customer?->city) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="province">Province/state</label>
                                <input class="form-control" id="province" name="province" value="{{ old('province', $order->customer?->province) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="postal_code">Postal code</label>
                                <input class="form-control" id="postal_code" name="postal_code" value="{{ old('postal_code', $order->customer?->postal_code) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="delivery_carrier">Delivery carrier/agent</label>
                                <input class="form-control" id="delivery_carrier" name="delivery_carrier" value="{{ old('delivery_carrier', $order->delivery_carrier) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="carrier_tracking_number">Carrier tracking number</label>
                                <input class="form-control" id="carrier_tracking_number" name="carrier_tracking_number" value="{{ old('carrier_tracking_number', $order->tracking_number) }}">
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
                        <p class="mb-1"><strong>Email:</strong> {{ $order->customer?->email ?? 'Unavailable' }}</p>
                        <p class="mb-1"><strong>Phone:</strong> {{ $order->customer?->phone ?? 'Unavailable' }}</p>
                        <p class="mb-1"><strong>Fulfillment:</strong> {{ $order->fulfillmentLabel() }}</p>
                        <p class="mb-1"><strong>Payment gateway:</strong> {{ $order->latestPayment?->gatewayLabel() ?? ucfirst($order->payment_gateway ?? $order->payment_provider ?? 'Pending') }}</p>
                        <p class="mb-1"><strong>Payment status:</strong> {{ ucfirst($order->payment_status) }}</p>
                        @if ($order->isShipping())
                            <p class="mb-1"><strong>Shipping method:</strong> {{ $order->shipping_method_name ?: 'To be confirmed' }}</p>
                            <p class="mb-1"><strong>Estimated delivery:</strong> {{ $order->shipping_delivery_days ?: 'To be confirmed' }}</p>
                            <p class="mb-1"><strong>Shipping base:</strong> ${{ number_format($order->shipping_base_cost, 2) }}</p>
                            <p class="mb-1"><strong>Shipping discount:</strong> ${{ number_format($order->shipping_discount_amount, 2) }}</p>
                            <p class="mb-1"><strong>Final shipping:</strong> ${{ number_format($order->shipping_cost, 2) }}</p>
                        @else
                            <p class="mb-1"><strong>Shipping:</strong> No charge for store pickup.</p>
                        @endif
                        <p class="mb-0"><strong>Payment reference:</strong> {{ $order->payment_reference ?: 'Pending' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
