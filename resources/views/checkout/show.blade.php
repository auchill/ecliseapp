@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    @php
        $subtotal = $cart->subtotal();
        $tax = round($subtotal * 0.13, 2);
        $selectedFulfillment = old('fulfillment_method', 'pickup');
    @endphp

    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Checkout</p>
            <h1 class="display-5 fw-bold mb-0">Choose pickup or shipping.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <form class="surface p-4" method="POST" action="{{ route('checkout.store') }}" data-fulfillment-form>
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="customer_name">Name</label>
                                <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone</label>
                                <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Fulfillment method</label>
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
                            </div>

                            <div class="col-12 alert alert-info mb-0" data-pickup-message>
                                Your order will be prepared for store pickup. You will receive an email when it is ready.
                            </div>

                            <div class="col-12" data-shipping-panel>
                                <div class="surface p-3">
                                    <h2 class="h5 fw-bold">Shipping address confirmation</h2>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="shipping_full_name">Full name</label>
                                            <input class="form-control" id="shipping_full_name" name="shipping_full_name" value="{{ old('shipping_full_name', auth()->user()->name) }}" data-shipping-required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="shipping_phone">Phone number</label>
                                            <input class="form-control" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone') }}" data-shipping-required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="shipping_email">Email</label>
                                            <input class="form-control" id="shipping_email" name="shipping_email" type="email" value="{{ old('shipping_email', auth()->user()->email) }}" data-shipping-required>
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
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="delivery_carrier">Delivery carrier/agent</label>
                                <input class="form-control" id="delivery_carrier" name="delivery_carrier" value="{{ old('delivery_carrier') }}" placeholder="Optional at checkout">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tracking_number">Tracking number</label>
                                <input class="form-control" id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" placeholder="Optional at checkout">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="notes">Order notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-credit-card me-2"></i>Place Order</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4">
                        <h2 class="h4 fw-bold mb-3">Order Summary</h2>
                        @foreach ($cart->items as $item)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $item->product->name }}</strong>
                                    <div class="small muted">Qty {{ $item->quantity }}</div>
                                </div>
                                <strong>${{ number_format($item->lineTotal(), 2) }}</strong>
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-between pt-3">
                            <span>Subtotal</span>
                            <strong>${{ number_format($subtotal, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Estimated tax</span>
                            <strong>${{ number_format($tax, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Shipping</span>
                            <strong data-shipping-cost-label>$0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-3 mt-3 border-top h4">
                            <span>Total</span>
                            <strong data-grand-total-label>${{ number_format($subtotal + $tax, 2) }}</strong>
                        </div>
                        <p class="muted small mb-0">Shipping is recalculated on the server when the order is placed.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        (() => {
            const form = document.querySelector('[data-fulfillment-form]');
            if (!form) return;

            const subtotalTax = {{ json_encode($subtotal + $tax) }};
            const costs = {
                pickup: {{ json_encode($pickupShippingCost) }},
                canada: {{ json_encode($canadaShippingCost) }},
                international: {{ json_encode($internationalShippingCost) }},
            };
            const panel = form.querySelector('[data-shipping-panel]');
            const pickupMessage = form.querySelector('[data-pickup-message]');
            const requiredFields = form.querySelectorAll('[data-shipping-required]');
            const countryInput = form.querySelector('[data-country-input]');
            const shippingLabel = document.querySelector('[data-shipping-cost-label]');
            const totalLabel = document.querySelector('[data-grand-total-label]');
            const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

            function shippingCost() {
                const method = form.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                if (method === 'pickup') return costs.pickup;
                const country = (countryInput?.value || '').trim().toLowerCase();
                return ['canada', 'ca', 'can'].includes(country) ? costs.canada : costs.international;
            }

            function syncFulfillment() {
                const method = form.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                const isShipping = method === 'shipping';
                panel.hidden = !isShipping;
                pickupMessage.hidden = isShipping;
                requiredFields.forEach((field) => field.required = isShipping);
                const cost = shippingCost();
                shippingLabel.textContent = money.format(cost);
                totalLabel.textContent = money.format(subtotalTax + cost);
            }

            form.querySelectorAll('[data-fulfillment-option]').forEach((option) => option.addEventListener('change', syncFulfillment));
            countryInput?.addEventListener('input', syncFulfillment);
            syncFulfillment();
        })();
    </script>
@endsection
