@extends('layouts.admin')

@section('title', 'Repair '.$repair->repair_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Repair {{ $repair->repair_number }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $repair->customer?->full_name ?? 'Customer unavailable' }} &middot; {{ $repair->deviceLabel() }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.repairs.index') }}"><i class="bi bi-arrow-left me-2"></i>Repairs</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <form class="surface p-4" method="POST" action="{{ route('admin.repairs.update', $repair) }}">
                        @csrf
                        @method('PATCH')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $repair->repair_status ?: $repair->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_status">Payment status</label>
                                <select class="form-select" id="payment_status" name="payment_status" required>
                                    @foreach ($paymentStatuses as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_status', $repair->payment_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="estimated_completion_date">Estimated completion</label>
                                <input class="form-control" id="estimated_completion_date" name="estimated_completion_date" type="date" value="{{ old('estimated_completion_date', $repair->estimated_completion_date?->toDateString()) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fulfillment_method">Fulfillment</label>
                                <select class="form-select" id="fulfillment_method" name="fulfillment_method" required>
                                    @foreach ($fulfillmentMethods as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fulfillment_method', $repair->fulfillment_method) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="shipping_cost">Shipping cost</label>
                                <input class="form-control" id="shipping_cost" name="shipping_cost" type="number" min="0" step="0.01" value="{{ old('shipping_cost', $repair->shipping_cost) }}" required>
                            </div>

                            <div class="col-12"><hr><h2 class="h6 fw-bold">Shipping Address</h2></div>
                            <div class="col-md-6">
                                <label class="form-label" for="recipient_name">Full name</label>
                                <input class="form-control" id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $repair->customer?->full_name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="recipient_phone">Phone</label>
                                <input class="form-control" id="recipient_phone" name="recipient_phone" value="{{ old('recipient_phone', $repair->customer?->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="recipient_email">Email</label>
                                <input class="form-control" id="recipient_email" name="recipient_email" type="email" value="{{ old('recipient_email', $repair->customer?->email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="country">Country</label>
                                <input class="form-control" id="country" name="country" value="{{ old('country', $repair->customer?->country) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Street address</label>
                                <input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $repair->customer?->street_address) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Apartment/unit</label>
                                <input class="form-control" id="address_line2" name="address_line2" value="{{ old('address_line2', $repair->customer?->address_line_2) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="city">City</label>
                                <input class="form-control" id="city" name="city" value="{{ old('city', $repair->customer?->city) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="province">Province/state</label>
                                <input class="form-control" id="province" name="province" value="{{ old('province', $repair->customer?->province) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="postal_code">Postal code</label>
                                <input class="form-control" id="postal_code" name="postal_code" value="{{ old('postal_code', $repair->customer?->postal_code) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="delivery_carrier">Delivery carrier/agent</label>
                                <input class="form-control" id="delivery_carrier" name="delivery_carrier" value="{{ old('delivery_carrier', $repair->delivery_carrier) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="delivery_tracking_number">Carrier tracking number</label>
                                <input class="form-control" id="delivery_tracking_number" name="delivery_tracking_number" value="{{ old('delivery_tracking_number', $repair->delivery_tracking_number) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tracking_notes">Tracking notes</label>
                                <textarea class="form-control" id="tracking_notes" name="tracking_notes" rows="3">{{ old('tracking_notes', $repair->tracking_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customer_notes">Customer-visible notes</label>
                                <textarea class="form-control" id="customer_notes" name="customer_notes" rows="4">{{ old('customer_notes', $repair->customer_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="internal_notes">Internal notes</label>
                                <textarea class="form-control" id="internal_notes" name="internal_notes" rows="4">{{ old('internal_notes', $repair->internal_notes) }}</textarea>
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
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Update Repair</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4 mb-4">
                        <h2 class="h5 fw-bold">Repair Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Email</th><td>{{ $repair->customer?->email ?? 'Unavailable' }}</td></tr>
                                    <tr><th scope="row">Phone</th><td>{{ $repair->customer?->phone ?? 'Unavailable' }}</td></tr>
                                    @if ($repair->quote)
                                        <tr><th scope="row">Quote</th><td><a href="{{ route('admin.quotes.show', $repair->quote) }}">#{{ $repair->quote->id }}</a></td></tr>
                                    @endif
                                    <tr><th scope="row">Issue</th><td>{{ $repair->issueCategoryName() }}</td></tr>
                                    <tr><th scope="row">Description</th><td>{{ $repair->issue_description }}</td></tr>
                                    <tr><th scope="row">Customer remark</th><td>{{ $repair->customer_remark ?: 'No customer remark.' }}</td></tr>
                                    <tr><th scope="row">Fulfillment</th><td>{{ $repair->fulfillmentLabel() }}</td></tr>
                                    <tr><th scope="row">Payment gateway</th><td>{{ $repair->latestPayment?->gatewayLabel() ?? ucfirst($repair->payment_gateway ?? 'Not required') }}</td></tr>
                                    <tr><th scope="row">Payment status</th><td>{{ $repair->paymentStatusLabel() }}</td></tr>
                                    <tr><th scope="row">Amount paid</th><td>${{ number_format($repair->amount_paid, 2) }}</td></tr>
                                    <tr><th scope="row">Balance due</th><td>${{ number_format($repair->currentBalanceDue(), 2) }}</td></tr>
                                    @if ($repair->isShipping())
                                        <tr><th scope="row">Shipping method</th><td>{{ $repair->shipping_method_name ?: 'To be confirmed' }}</td></tr>
                                        <tr><th scope="row">Estimated delivery</th><td>{{ $repair->shipping_delivery_days ?: 'To be confirmed' }}</td></tr>
                                        <tr><th scope="row">Shipping base</th><td>${{ number_format($repair->shipping_base_cost, 2) }}</td></tr>
                                        <tr><th scope="row">Shipping discount</th><td>${{ number_format($repair->shipping_discount_amount, 2) }}</td></tr>
                                        <tr><th scope="row">Final shipping</th><td>${{ number_format($repair->shipping_cost, 2) }}</td></tr>
                                    @else
                                        <tr><th scope="row">Shipping</th><td>No charge for store pickup.</td></tr>
                                    @endif
                                    <tr><th scope="row">Repair total</th><td>${{ number_format($repair->repair_total, 2) }}</td></tr>
                                    <tr><th scope="row">Appointment</th><td>{{ $repair->preferred_appointment_date?->format('M j, Y') ?? 'Not set' }} {{ $repair->preferred_appointment_time }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="surface p-4 mb-4">
                        <h2 class="h5 fw-bold">Repair Items</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr><th>Type</th><th>Item</th><th>Qty</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($repair->repair_items ?? [] as $item)
                                        <tr>
                                            <td>{{ ucfirst($item['type'] ?? 'item') }}</td>
                                            <td>{{ $item['name'] ?? '' }}</td>
                                            <td>{{ $item['quantity'] ?? 1 }}</td>
                                            <td>${{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">No repair items recorded.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Timeline</h2>
                        <div class="timeline">
                            @forelse ($repair->statusUpdates as $update)
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
            </div>
        </div>
    </section>
@endsection
