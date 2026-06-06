@extends('layouts.app')

@section('title', 'Book a Repair')

@section('content')
    @php
        $selectedFulfillment = old('fulfillment_method', 'pickup');
    @endphp

    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair Booking</p>
            <h1 class="display-5 fw-bold mb-3">Tell us what needs repair.</h1>
            <p class="fs-5 mb-0">Submit your device details and appointment preference to receive an Eclise tracking number.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="customer_name">Customer name</label>
                        <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone number</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_type">Device type</label>
                        <select class="form-select" id="device_type" name="device_type" required>
                            @foreach (['Phone', 'Laptop', 'Desktop', 'Tablet', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(old('device_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_brand">Device brand</label>
                        <input class="form-control" id="device_brand" name="device_brand" value="{{ old('device_brand') }}" placeholder="Apple, Samsung, Dell">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_model">Device model</label>
                        <input class="form-control" id="device_model" name="device_model" value="{{ old('device_model') }}" placeholder="iPhone 13, ThinkPad T14">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="issue_category">Issue category</label>
                        <input class="form-control" id="issue_category" name="issue_category" value="{{ old('issue_category') }}" placeholder="Screen, battery, charging, diagnosis" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_appointment_date">Preferred date</label>
                        <input class="form-control" id="preferred_appointment_date" name="preferred_appointment_date" type="date" value="{{ old('preferred_appointment_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_appointment_time">Preferred time</label>
                        <input class="form-control" id="preferred_appointment_time" name="preferred_appointment_time" type="time" value="{{ old('preferred_appointment_time') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="issue_description">Issue description</label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required>{{ old('issue_description') }}</textarea>
                    </div>
                    <div class="col-12" data-fulfillment-form>
                        <label class="form-label d-block">After-repair fulfillment</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_pickup">
                                    <input class="form-check-input mt-1" id="fulfillment_pickup" name="fulfillment_method" type="radio" value="pickup" data-fulfillment-option @checked($selectedFulfillment === 'pickup')>
                                    <span>
                                        <strong>Store Pickup</strong>
                                        <span class="d-block muted small">Shipping cost ${{ number_format($pickupShippingCost, 2) }}.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_shipping">
                                    <input class="form-check-input mt-1" id="fulfillment_shipping" name="fulfillment_method" type="radio" value="shipping" data-fulfillment-option @checked($selectedFulfillment === 'shipping')>
                                    <span>
                                        <strong>Shipping</strong>
                                        <span class="d-block muted small">Canada ${{ number_format($canadaShippingCost, 2) }}, outside Canada ${{ number_format($internationalShippingCost, 2) }}.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0" data-pickup-message>
                            We will notify you when your repaired device is ready for pickup.
                        </div>

                        <div class="surface p-3 mt-3" data-shipping-panel>
                            <h2 class="h5 fw-bold">Shipping address confirmation</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_full_name">Full name</label>
                                    <input class="form-control" id="shipping_full_name" name="shipping_full_name" value="{{ old('shipping_full_name', auth()->user()?->name) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_phone">Phone number</label>
                                    <input class="form-control" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone') }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_email">Email</label>
                                    <input class="form-control" id="shipping_email" name="shipping_email" type="email" value="{{ old('shipping_email', auth()->user()?->email) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_country">Country</label>
                                    <input class="form-control" id="shipping_country" name="shipping_country" value="{{ old('shipping_country', 'Canada') }}" data-country-input data-shipping-required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="shipping_address_line1">Street address</label>
                                    <input class="form-control" id="shipping_address_line1" name="shipping_address_line1" value="{{ old('shipping_address_line1') }}" data-shipping-required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="shipping_address_line2">Apartment/unit</label>
                                    <input class="form-control" id="shipping_address_line2" name="shipping_address_line2" value="{{ old('shipping_address_line2') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="shipping_city">City</label>
                                    <input class="form-control" id="shipping_city" name="shipping_city" value="{{ old('shipping_city') }}" data-shipping-required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="shipping_province">Province/state</label>
                                    <input class="form-control" id="shipping_province" name="shipping_province" value="{{ old('shipping_province') }}" data-shipping-required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="shipping_postal_code">Postal code</label>
                                    <input class="form-control" id="shipping_postal_code" name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" data-shipping-required>
                                </div>
                            </div>
                            <p class="muted mb-0 mt-3">Estimated shipping: <strong data-shipping-cost-label>$0.00</strong>. Final cost is recalculated on the server.</p>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="device_image">Device image</label>
                        <input class="form-control" id="device_image" name="device_image" type="file" accept="image/*">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" id="terms_accepted" name="terms_accepted" type="checkbox" value="1" @checked(old('terms_accepted')) required>
                            <label class="form-check-label" for="terms_accepted">I agree that Eclise Technology Inc. may review this repair request and contact me about service options.</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send me-2"></i>Submit Repair Request</button>
                        <a class="btn btn-outline-primary btn-lg" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2"></i>Track Existing Repair</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const form = document.querySelector('form[data-fulfillment-ready], form');
            const wrapper = document.querySelector('[data-fulfillment-form]');
            if (!form || !wrapper) return;

            const costs = {
                pickup: {{ json_encode($pickupShippingCost) }},
                canada: {{ json_encode($canadaShippingCost) }},
                international: {{ json_encode($internationalShippingCost) }},
            };
            const panel = wrapper.querySelector('[data-shipping-panel]');
            const pickupMessage = wrapper.querySelector('[data-pickup-message]');
            const requiredFields = wrapper.querySelectorAll('[data-shipping-required]');
            const countryInput = wrapper.querySelector('[data-country-input]');
            const shippingLabel = wrapper.querySelector('[data-shipping-cost-label]');
            const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

            function shippingCost() {
                const method = wrapper.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                if (method === 'pickup') return costs.pickup;
                const country = (countryInput?.value || '').trim().toLowerCase();
                return ['canada', 'ca', 'can'].includes(country) ? costs.canada : costs.international;
            }

            function syncFulfillment() {
                const method = wrapper.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                const isShipping = method === 'shipping';
                panel.hidden = !isShipping;
                pickupMessage.hidden = isShipping;
                requiredFields.forEach((field) => field.required = isShipping);
                shippingLabel.textContent = money.format(shippingCost());
            }

            wrapper.querySelectorAll('[data-fulfillment-option]').forEach((option) => option.addEventListener('change', syncFulfillment));
            countryInput?.addEventListener('input', syncFulfillment);
            syncFulfillment();
        })();
    </script>
@endsection
