@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    @php
        $subtotal = $cart->subtotal();
        $tax = round($subtotal * 0.13, 2);
        $selectedFulfillment = $shippingMethods->isEmpty() ? 'pickup' : old('fulfillment_method', 'pickup');
        $selectedShippingMethodId = (string) old('shipping_method_id', array_key_first($shippingQuotes) ?? '');
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
                                                <span class="d-block muted small">No shipping charge.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_shipping">
                                            <input class="form-check-input mt-1" id="fulfillment_shipping" name="fulfillment_method" type="radio" value="shipping" data-fulfillment-option @checked($selectedFulfillment === 'shipping') @disabled($shippingMethods->isEmpty())>
                                            <span>
                                                <strong>Shipping</strong>
                                                <span class="d-block muted small">
                                                    {{ $shippingMethods->isEmpty() ? 'No active shipping methods available.' : 'Select a delivery speed below.' }}
                                                </span>
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
                                    <h2 class="h5 fw-bold">Shipping method</h2>
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
                            <span>Shipping method</span>
                            <strong class="text-end" data-shipping-method-label>Store pickup</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Estimated delivery</span>
                            <strong class="text-end" data-shipping-delivery-label>Pickup</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Shipping base</span>
                            <strong data-shipping-base-label>$0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Shipping discount</span>
                            <strong class="text-success" data-shipping-discount-label>$0.00</strong>
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
            const pickupQuote = @json($pickupQuote);
            const shippingQuotes = @json($shippingQuotes);
            const panel = form.querySelector('[data-shipping-panel]');
            const pickupMessage = form.querySelector('[data-pickup-message]');
            const requiredFields = form.querySelectorAll('[data-shipping-required]');
            const methodInputs = form.querySelectorAll('[data-shipping-method-option]');
            const methodLabel = document.querySelector('[data-shipping-method-label]');
            const deliveryLabel = document.querySelector('[data-shipping-delivery-label]');
            const baseLabel = document.querySelector('[data-shipping-base-label]');
            const discountLabel = document.querySelector('[data-shipping-discount-label]');
            const shippingLabel = document.querySelector('[data-shipping-cost-label]');
            const totalLabel = document.querySelector('[data-grand-total-label]');
            const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

            function selectedQuote(isShipping) {
                if (!isShipping) return pickupQuote;
                let selected = form.querySelector('[data-shipping-method-option]:checked');

                if (!selected && methodInputs.length > 0) {
                    selected = methodInputs[0];
                    selected.checked = true;
                }

                return selected ? shippingQuotes[selected.value] : pickupQuote;
            }

            function syncFulfillment() {
                const method = form.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
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
                totalLabel.textContent = money.format(subtotalTax + cost);
            }

            form.querySelectorAll('[data-fulfillment-option]').forEach((option) => option.addEventListener('change', syncFulfillment));
            methodInputs.forEach((option) => option.addEventListener('change', syncFulfillment));
            syncFulfillment();
        })();
    </script>
@endsection
