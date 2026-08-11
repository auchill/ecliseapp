@extends('layouts.admin')

@section('title', 'MobileSentrix Order '.$order->order_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <p class="eyebrow">MobileSentrix</p>
                    <h1 class="display-6 fw-bold mb-1">{{ $order->order_number }}</h1>
                    <p class="muted mb-0">
                        Created {{ $order->created_at?->format('M j, Y g:i A') }}
                        @if ($order->createdBy) by {{ $order->createdBy->name }} @endif
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge {{ $order->statusBadgeClass() }} fs-6">{{ $order->order_status }}</span>
                    @if ($order->canTransitionTo(\App\Models\MobileSentrixOrder::STATUS_RECEIVED))
                        <form method="POST" action="{{ route('admin.mobilesentrix-orders.receive', $order) }}" data-confirm-title="Mark received?" data-confirm-text="Confirm that this procurement order has arrived from MobileSentrix.">
                            @csrf @method('PATCH')
                            <button class="btn btn-success btn-sm" type="submit">Mark Received</button>
                        </form>
                    @endif
                    @if ($order->canTransitionTo(\App\Models\MobileSentrixOrder::STATUS_RETURNED))
                        <form method="POST" action="{{ route('admin.mobilesentrix-orders.return', $order) }}" data-confirm-title="Mark returned?" data-confirm-text="This records an internal return. No return request is sent to MobileSentrix." data-confirm-danger="1">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline-danger btn-sm" type="submit">Mark Returned</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->has('procurement'))
                <div class="alert alert-danger">{{ $errors->first('procurement') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="surface p-4 table-responsive mb-4">
                        <h2 class="h5 fw-bold mb-3">Items</h2>
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>SKU</th>
                                    <th>Item</th>
                                    <th>Customer</th>
                                    <th>Shop Order #</th>
                                    <th>Repair #</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Tax</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td><span class="badge {{ $item->is_device ? 'bg-info-subtle text-info-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">{{ $item->typeLabel() }}</span></td>
                                        <td class="text-break">{{ $item->source_sku }}</td>
                                        <td>{{ $item->resolved_name }}</td>
                                        <td>{{ $item->customer?->full_name ?: '—' }}</td>
                                        <td>{{ $item->order_number ?: '—' }}</td>
                                        <td>{{ $item->repair_number ?: '—' }}</td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ \App\Support\Money::format($item->mobilesentrix_price, $order->currency) }}</td>
                                        <td class="text-end">{{ \App\Support\Money::format($item->mobilesentrix_tax, $order->currency) }}</td>
                                        <td class="text-end">{{ \App\Support\Money::format($item->lineTotal(), $order->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="9" class="text-end">Subtotal</th>
                                    <th class="text-end">{{ \App\Support\Money::format($order->subtotal, $order->currency) }}</th>
                                </tr>
                                <tr>
                                    <td colspan="9" class="text-end">Tax</td>
                                    <td class="text-end">{{ \App\Support\Money::format($order->tax, $order->currency) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="text-end">Shipping</td>
                                    <td class="text-end">{{ \App\Support\Money::format($order->shipping_cost, $order->currency) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="text-end">Shipping discount</td>
                                    <td class="text-end">-{{ \App\Support\Money::format($order->shipping_discount_amount, $order->currency) }}</td>
                                </tr>
                                <tr>
                                    <th colspan="9" class="text-end">Total</th>
                                    <th class="text-end">{{ \App\Support\Money::format($order->total, $order->currency) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                        <p class="muted small mb-0">Unit prices are MobileSentrix cost snapshots taken when this order was created; they do not change if the catalogue price changes later.</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold mb-1">Supplier Order Details</h2>
                        <p class="muted small">Record what you entered on MobileSentrix. Nothing is submitted to MobileSentrix automatically.</p>

                        <form method="POST" action="{{ route('admin.mobilesentrix-orders.update', $order) }}">
                            @csrf @method('PATCH')

                            <div class="mb-3">
                                <label class="form-label" for="supplier_order_number">MobileSentrix Order Reference</label>
                                <input class="form-control" id="supplier_order_number" name="supplier_order_number" value="{{ old('supplier_order_number', $order->supplier_order_number) }}">
                            </div>

                            <div class="row g-2">
                                <div class="col-6 mb-3">
                                    <label class="form-label" for="tax">Tax</label>
                                    <input class="form-control" id="tax" name="tax" type="number" step="0.01" min="0" value="{{ old('tax', $order->tax) }}">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label" for="shipping_cost">Shipping</label>
                                    <input class="form-control" id="shipping_cost" name="shipping_cost" type="number" step="0.01" min="0" value="{{ old('shipping_cost', $order->shipping_cost) }}">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label" for="shipping_discount_amount">Shipping Discount</label>
                                    <input class="form-control" id="shipping_discount_amount" name="shipping_discount_amount" type="number" step="0.01" min="0" value="{{ old('shipping_discount_amount', $order->shipping_discount_amount) }}">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label" for="payment_amount">Payment Amount</label>
                                    <input class="form-control" id="payment_amount" name="payment_amount" type="number" step="0.01" min="0" value="{{ old('payment_amount', $order->payment_amount) }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="paid_at">Paid At</label>
                                <input class="form-control" id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', $order->paid_at?->format('Y-m-d\TH:i')) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="shipping_method_id">Shipping Method</label>
                                <select class="form-select" id="shipping_method_id" name="shipping_method_id">
                                    <option value="">Not set</option>
                                    @foreach ($shippingMethods as $method)
                                        <option value="{{ $method->id }}" @selected(old('shipping_method_id', $order->shipping_method_id) == $method->id)>{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="delivery_carrier">Delivery Carrier</label>
                                <input class="form-control" id="delivery_carrier" name="delivery_carrier" value="{{ old('delivery_carrier', $order->delivery_carrier) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="tracking_number">Tracking Number</label>
                                <input class="form-control" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="tracking_notes">Tracking Notes</label>
                                <textarea class="form-control" id="tracking_notes" name="tracking_notes" rows="2">{{ old('tracking_notes', $order->tracking_notes) }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="admin_notes">Admin Notes</label>
                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="2">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $order->notes) }}</textarea>
                            </div>

                            <button class="btn btn-primary w-100" type="submit">Save Order Details</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('form[data-confirm-title]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();

                const result = await window.EcliseAlert.confirm({
                    title: form.dataset.confirmTitle,
                    text: form.dataset.confirmText,
                    danger: form.dataset.confirmDanger === '1',
                });

                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    </script>
@endpush
