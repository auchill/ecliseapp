@extends('layouts.app')

@section('title', 'Book a Repair')

@section('content')
    @php
        $repairSubtotal = 0.00;
        $selectedFulfillment = $shippingMethods->isEmpty() ? 'pickup' : old('fulfillment_method', 'pickup');
        $selectedShippingMethodId = (string) old('shipping_method_id', array_key_first($shippingQuotes) ?? '');
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
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data" data-repair-form>
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
                                        <span class="d-block muted small">No return shipping charge.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_shipping">
                                    <input class="form-check-input mt-1" id="fulfillment_shipping" name="fulfillment_method" type="radio" value="shipping" data-fulfillment-option @checked($selectedFulfillment === 'shipping') @disabled($shippingMethods->isEmpty())>
                                    <span>
                                        <strong>Shipping</strong>
                                        <span class="d-block muted small">
                                            {{ $shippingMethods->isEmpty() ? 'No active shipping methods available.' : 'Select return delivery below.' }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0" data-pickup-message>
                            We will notify you when your repaired device is ready for pickup.
                        </div>

                        <div class="surface p-3 mt-3" data-shipping-panel>
                            <h2 class="h5 fw-bold">Return shipping method</h2>
                            <div class="row g-3 mb-3">
                                @forelse ($shippingMethods as $method)
                                    @php $quote = $shippingQuotes[(string) $method->id] ?? null; @endphp
                                    <div class="col-md-6">
                                        <label class="surface p-3 d-flex gap-3 h-100" for="shipping_method_{{ $method->id }}">
                                            <input class="form-check-input mt-1" id="shipping_method_{{ $method->id }}" name="shipping_method_id" type="radio" value="{{ $method->id }}" data-shipping-method-option @checked($selectedShippingMethodId === (string) $method->id)>
                                            <span>
                                                <strong>{{ $method->name }}</strong>
                                                <span class="d-block muted small">{{ $method->deliveryDaysLabel() }} &middot; ${{ number_format($quote['shipping_cost'] ?? $method->base_cost, 2) }}</span>
                                                @if (($quote['shipping_discount_amount'] ?? 0) > 0)
                                                    <span class="d-block text-success small">Discount: ${{ number_format($quote['shipping_discount_amount'], 2) }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="alert alert-warning mb-0">Shipping is unavailable until an active shipping method is added.</div>
                                    </div>
                                @endforelse
                            </div>
                            @error('shipping_method_id')
                                <div class="text-danger small mb-3">{{ $message }}</div>
                            @enderror

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
                                    <input class="form-control" id="shipping_country" name="shipping_country" value="{{ old('shipping_country', 'Canada') }}" data-shipping-required>
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

                            <div class="table-responsive mt-4">
                                <table class="table mb-0">
                                    <tbody>
                                        <tr><th scope="row">Repair subtotal</th><td data-repair-subtotal-label>$0.00</td></tr>
                                        <tr><th scope="row">Shipping method</th><td data-shipping-method-label>Store pickup</td></tr>
                                        <tr><th scope="row">Estimated delivery</th><td data-shipping-delivery-label>Pickup</td></tr>
                                        <tr><th scope="row">Shipping base</th><td data-shipping-base-label>$0.00</td></tr>
                                        <tr><th scope="row">Shipping discount</th><td class="text-success" data-shipping-discount-label>$0.00</td></tr>
                                        <tr><th scope="row">Return shipping</th><td data-shipping-cost-label>$0.00</td></tr>
                                        <tr><th scope="row">Booking total</th><td class="fw-bold" data-grand-total-label>$0.00</td></tr>
                                    </tbody>
                                </table>
                            </div>
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
            const form = document.querySelector('[data-repair-form]');
            const wrapper = document.querySelector('[data-fulfillment-form]');
            if (!form || !wrapper) return;

            const repairSubtotal = {{ json_encode($repairSubtotal) }};
            const pickupQuote = @json($pickupQuote);
            const shippingQuotes = @json($shippingQuotes);
            const panel = wrapper.querySelector('[data-shipping-panel]');
            const pickupMessage = wrapper.querySelector('[data-pickup-message]');
            const requiredFields = wrapper.querySelectorAll('[data-shipping-required]');
            const methodInputs = wrapper.querySelectorAll('[data-shipping-method-option]');
            const methodLabel = wrapper.querySelector('[data-shipping-method-label]');
            const deliveryLabel = wrapper.querySelector('[data-shipping-delivery-label]');
            const baseLabel = wrapper.querySelector('[data-shipping-base-label]');
            const discountLabel = wrapper.querySelector('[data-shipping-discount-label]');
            const shippingLabel = wrapper.querySelector('[data-shipping-cost-label]');
            const totalLabel = wrapper.querySelector('[data-grand-total-label]');
            const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

            function selectedQuote(isShipping) {
                if (!isShipping) return pickupQuote;
                let selected = wrapper.querySelector('[data-shipping-method-option]:checked');

                if (!selected && methodInputs.length > 0) {
                    selected = methodInputs[0];
                    selected.checked = true;
                }

                return selected ? shippingQuotes[selected.value] : pickupQuote;
            }

            function syncFulfillment() {
                const method = wrapper.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                const isShipping = method === 'shipping';
                const quote = selectedQuote(isShipping) || pickupQuote;
                const baseCost = Number(quote.shipping_base_cost || 0);
                const discount = Number(quote.shipping_discount_amount || 0);
                const cost = Number(quote.shipping_cost || 0);

                panel.hidden = !isShipping;
                pickupMessage.hidden = isShipping;
                requiredFields.forEach((field) => field.required = isShipping);
                methodInputs.forEach((field) => field.required = isShipping);

                methodLabel.textContent = quote.shipping_method_name || (isShipping ? 'Select a method' : 'Store pickup');
                deliveryLabel.textContent = quote.shipping_delivery_days || (isShipping ? 'To be confirmed' : 'Pickup');
                baseLabel.textContent = money.format(baseCost);
                discountLabel.textContent = money.format(discount);
                shippingLabel.textContent = money.format(cost);
                totalLabel.textContent = money.format(repairSubtotal + cost);
            }

            wrapper.querySelectorAll('[data-fulfillment-option]').forEach((option) => option.addEventListener('change', syncFulfillment));
            methodInputs.forEach((option) => option.addEventListener('change', syncFulfillment));
            syncFulfillment();
        })();
    </script>
@endsection
