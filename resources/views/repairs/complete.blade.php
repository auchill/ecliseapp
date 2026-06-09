@extends('layouts.app')

@section('title', 'Complete Repair Booking')

@section('content')
    @php
        $baseTotal = (float) $booking->subtotal + (float) $booking->tax_amount;
        $selectedFulfillment = $shippingMethods->isEmpty() ? 'pickup' : old('fulfillment_method', $booking->pickup_or_shipping_option ?: 'pickup');
        $selectedShippingMethodId = (string) old('shipping_method_id', $booking->shipping_method_id ?: array_key_first($shippingQuotes) ?? '');
    @endphp

    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair {{ $booking->tracking_number }}</p>
            <h1 class="display-5 fw-bold mb-3">Review and complete booking.</h1>
            <p class="fs-5 mb-0">Choose pickup or shipping, add any customer remark, and pay the minimum required amount or the full balance.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('repairs.complete.store', $booking->tracking_number) }}" data-repair-complete-form>
                @csrf
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h2 class="h5 fw-bold">Repair Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Customer</th><td>{{ $booking->customer_name }}</td></tr>
                                    <tr><th scope="row">Device type</th><td>{{ $booking->deviceTypeName() }}</td></tr>
                                    <tr><th scope="row">Brand</th><td>{{ $booking->deviceBrandName() }}</td></tr>
                                    <tr><th scope="row">Model</th><td>{{ $booking->deviceModelName() }}</td></tr>
                                    <tr><th scope="row">Issue</th><td>{{ $booking->issueCategoryName() }}</td></tr>
                                    <tr><th scope="row">Description</th><td>{{ $booking->issue_description }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="h5 fw-bold mt-4">Repair Items</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr><th>Type</th><th>Item</th><th>Qty</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($booking->repair_items ?? [] as $item)
                                        <tr>
                                            <td>{{ ucfirst($item['type'] ?? 'item') }}</td>
                                            <td>{{ $item['name'] ?? '' }}</td>
                                            <td>{{ $item['quantity'] ?? 1 }}</td>
                                            <td>${{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">No repair items were added.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="customer_remark">Customer remark</label>
                            <textarea class="form-control" id="customer_remark" name="customer_remark" rows="4">{{ old('customer_remark', $booking->customer_remark) }}</textarea>
                        </div>

                        <h2 class="h5 fw-bold">Payment method</h2>
                        <div class="row g-3 mb-4">
                            @foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal'] as $gateway => $label)
                                <div class="col-md-6">
                                    <label class="surface p-3 d-flex gap-3 h-100" for="payment_{{ $gateway }}">
                                        <input class="form-check-input mt-1" id="payment_{{ $gateway }}" name="payment_gateway" type="radio" value="{{ $gateway }}" @checked(old('payment_gateway', $booking->payment_gateway ?: 'stripe') === $gateway)>
                                        <span><strong>{{ $label }}</strong></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-5" data-fulfillment-form>
                        <h2 class="h5 fw-bold">Pickup or Shipping</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_pickup">
                                    <input class="form-check-input mt-1" id="fulfillment_pickup" name="fulfillment_method" type="radio" value="pickup" data-fulfillment-option @checked($selectedFulfillment === 'pickup')>
                                    <span><strong>Store pickup/drop-off</strong><span class="d-block muted small">No shipping charge.</span></span>
                                </label>
                            </div>
                            <div class="col-12">
                                <label class="surface p-3 d-flex gap-3 h-100" for="fulfillment_shipping">
                                    <input class="form-check-input mt-1" id="fulfillment_shipping" name="fulfillment_method" type="radio" value="shipping" data-fulfillment-option @checked($selectedFulfillment === 'shipping') @disabled($shippingMethods->isEmpty())>
                                    <span><strong>Shipping</strong><span class="d-block muted small">{{ $shippingMethods->isEmpty() ? 'No active shipping methods available.' : 'Ship the repaired device back to me.' }}</span></span>
                                </label>
                            </div>
                        </div>

                        <div class="surface p-3 mt-3" data-shipping-panel>
                            <h3 class="h6 fw-bold">Shipping method</h3>
                            @foreach ($shippingMethods as $method)
                                @php $quote = $shippingQuotes[(string) $method->id] ?? null; @endphp
                                <label class="surface p-3 d-flex gap-3 mb-2" for="shipping_method_{{ $method->id }}">
                                    <input class="form-check-input mt-1" id="shipping_method_{{ $method->id }}" name="shipping_method_id" type="radio" value="{{ $method->id }}" data-shipping-method-option @checked($selectedShippingMethodId === (string) $method->id)>
                                    <span><strong>{{ $method->name }}</strong><span class="d-block muted small">{{ $method->deliveryDaysLabel() }} &middot; ${{ number_format($quote['shipping_cost'] ?? $method->base_cost, 2) }}</span></span>
                                </label>
                            @endforeach

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_full_name">Full name</label>
                                    <input class="form-control" id="shipping_full_name" name="shipping_full_name" value="{{ old('shipping_full_name', $booking->shipping_full_name ?: auth()->user()?->name) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_phone">Phone</label>
                                    <input class="form-control" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone', $booking->shipping_phone ?: $booking->phone) }}" data-shipping-required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="shipping_email">Email</label>
                                    <input class="form-control" id="shipping_email" name="shipping_email" type="email" value="{{ old('shipping_email', $booking->shipping_email ?: $booking->email) }}" data-shipping-required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="shipping_address_line1">Street address</label>
                                    <input class="form-control" id="shipping_address_line1" name="shipping_address_line1" value="{{ old('shipping_address_line1', $booking->shipping_address_line1) }}" data-shipping-required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="shipping_address_line2">Apartment/unit</label>
                                    <input class="form-control" id="shipping_address_line2" name="shipping_address_line2" value="{{ old('shipping_address_line2', $booking->shipping_address_line2) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_city">City</label>
                                    <input class="form-control" id="shipping_city" name="shipping_city" value="{{ old('shipping_city', $booking->shipping_city) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_province">Province/state</label>
                                    <input class="form-control" id="shipping_province" name="shipping_province" value="{{ old('shipping_province', $booking->shipping_province) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_postal_code">Postal code</label>
                                    <input class="form-control" id="shipping_postal_code" name="shipping_postal_code" value="{{ old('shipping_postal_code', $booking->shipping_postal_code) }}" data-shipping-required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="shipping_country">Country</label>
                                    <input class="form-control" id="shipping_country" name="shipping_country" value="{{ old('shipping_country', $booking->shipping_country ?: 'Canada') }}" data-shipping-required>
                                </div>
                            </div>
                        </div>

                        <div class="surface p-3 mt-3">
                            <h3 class="h6 fw-bold">Payment Summary</h3>
                            <table class="table mb-3">
                                <tbody>
                                    <tr><th scope="row">Parts total</th><td>${{ number_format($booking->partsTotal(), 2) }}</td></tr>
                                    <tr><th scope="row">Subtotal</th><td>${{ number_format($booking->subtotal, 2) }}</td></tr>
                                    <tr><th scope="row">Tax</th><td>${{ number_format($booking->tax_amount, 2) }}</td></tr>
                                    <tr><th scope="row">Shipping</th><td data-shipping-cost-label>${{ number_format($booking->shipping_amount, 2) }}</td></tr>
                                    <tr><th scope="row">Total</th><td class="fw-bold" data-grand-total-label>${{ number_format($booking->total_amount ?: $booking->repair_total, 2) }}</td></tr>
                                    <tr><th scope="row">Paid</th><td>${{ number_format($booking->amount_paid, 2) }}</td></tr>
                                    <tr><th scope="row">Minimum due</th><td data-minimum-payment-label>${{ number_format($booking->minimumPaymentDue(), 2) }}</td></tr>
                                    <tr><th scope="row">Balance due</th><td data-balance-due-label>${{ number_format($booking->currentBalanceDue(), 2) }}</td></tr>
                                </tbody>
                            </table>
                            <label class="surface p-3 d-flex gap-3 mb-2" for="pay_minimum">
                                <input class="form-check-input mt-1" id="pay_minimum" name="payment_amount_option" type="radio" value="minimum" checked>
                                <span><strong>Pay minimum required</strong><span class="d-block muted small" data-minimum-payment-help></span></span>
                            </label>
                            <label class="surface p-3 d-flex gap-3" for="pay_full">
                                <input class="form-check-input mt-1" id="pay_full" name="payment_amount_option" type="radio" value="full">
                                <span><strong>Pay full balance</strong><span class="d-block muted small" data-balance-payment-help></span></span>
                            </label>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" id="terms_accepted" name="terms_accepted" type="checkbox" value="1" required>
                            <label class="form-check-label" for="terms_accepted">I agree to proceed with this repair booking and payment.</label>
                        </div>
                        <button class="btn btn-primary btn-lg w-100 mt-3" type="submit"><i class="bi bi-credit-card me-2"></i>Continue to Payment</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const form = document.querySelector('[data-repair-complete-form]');
            const wrapper = document.querySelector('[data-fulfillment-form]');
            if (!form || !wrapper) return;

            const baseTotal = {{ json_encode($baseTotal) }};
            const subtotal = {{ json_encode((float) $booking->subtotal) }};
            const taxAmount = {{ json_encode((float) $booking->tax_amount) }};
            const partsTotal = {{ json_encode($booking->partsTotal()) }};
            const amountPaid = {{ json_encode((float) $booking->amount_paid) }};
            const pickupQuote = @json($pickupQuote);
            const shippingQuotes = @json($shippingQuotes);
            const panel = wrapper.querySelector('[data-shipping-panel]');
            const requiredFields = wrapper.querySelectorAll('[data-shipping-required]');
            const methodInputs = wrapper.querySelectorAll('[data-shipping-method-option]');
            const shippingLabel = wrapper.querySelector('[data-shipping-cost-label]');
            const totalLabel = wrapper.querySelector('[data-grand-total-label]');
            const minimumLabel = wrapper.querySelector('[data-minimum-payment-label]');
            const balanceLabel = wrapper.querySelector('[data-balance-due-label]');
            const minimumHelp = wrapper.querySelector('[data-minimum-payment-help]');
            const balanceHelp = wrapper.querySelector('[data-balance-payment-help]');
            const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

            function quote(isShipping) {
                if (!isShipping) return pickupQuote;
                let selected = wrapper.querySelector('[data-shipping-method-option]:checked');
                if (!selected && methodInputs.length > 0) {
                    selected = methodInputs[0];
                    selected.checked = true;
                }
                return selected ? shippingQuotes[selected.value] : pickupQuote;
            }

            function sync() {
                const method = wrapper.querySelector('[data-fulfillment-option]:checked')?.value || 'pickup';
                const isShipping = method === 'shipping';
                const selectedQuote = quote(isShipping) || pickupQuote;
                const shipping = Number(selectedQuote.shipping_cost || 0);
                const total = subtotal + taxAmount + shipping;
                const minimum = partsTotal > 0 ? partsTotal + (0.5 * Math.max(0, total - partsTotal)) : total * 0.5;
                const minimumDue = Math.max(0, minimum - amountPaid);
                const balanceDue = Math.max(0, total - amountPaid);

                panel.hidden = !isShipping;
                requiredFields.forEach((field) => field.required = isShipping);
                methodInputs.forEach((field) => field.required = isShipping);
                shippingLabel.textContent = money.format(shipping);
                totalLabel.textContent = money.format(total);
                minimumLabel.textContent = money.format(minimumDue);
                balanceLabel.textContent = money.format(balanceDue);
                minimumHelp.textContent = money.format(minimumDue);
                balanceHelp.textContent = money.format(balanceDue);
            }

            wrapper.querySelectorAll('[data-fulfillment-option]').forEach((option) => option.addEventListener('change', sync));
            methodInputs.forEach((option) => option.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
