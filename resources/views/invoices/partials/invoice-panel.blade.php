<div class="surface p-4">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <h2 class="h5 fw-bold">Eclise Technology Inc.</h2>
            <p class="muted mb-0">Repair. Reuse. Reconnect.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Status:</strong> {{ $invoice->statusLabel() }}</p>
            <p class="mb-1"><strong>Issued:</strong> {{ $invoice->issued_at?->format('M j, Y') }}</p>
            <p class="mb-0"><strong>Due:</strong> {{ $invoice->due_at?->format('M j, Y') }}</p>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <h3 class="h6 fw-bold">Bill To</h3>
            <p class="mb-0">{{ $invoice->billing_snapshot['full_name'] ?? $invoice->customer?->full_name }}</p>
            <p class="mb-0">{{ $invoice->billing_snapshot['email'] ?? $invoice->customer?->email }}</p>
            <p class="mb-0">{{ $invoice->billing_snapshot['phone'] ?? $invoice->customer?->phone }}</p>
        </div>
        <div class="col-md-6 text-md-end">
            <h3 class="h6 fw-bold">Invoice</h3>
            <p class="mb-0">{{ $invoice->invoice_number }}</p>
            <p class="mb-0">{{ ucfirst(str_replace('_', ' ', $invoice->type)) }}</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Item</th><th>Qty</th><th class="text-end">Unit</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td><strong>{{ $item->name }}</strong><div class="small muted">{{ $item->description }}</div></td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td class="text-end">{{ \App\Support\Money::format($item->unit_price, $invoice->currency) }}</td>
                        <td class="text-end">{{ \App\Support\Money::format($item->line_total, $invoice->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="row justify-content-end">
        <div class="col-md-5">
            <div class="d-flex justify-content-between"><span>Subtotal</span><strong>{{ \App\Support\Money::format($invoice->subtotal, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Tax</span><strong>{{ \App\Support\Money::format($invoice->tax_amount, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Shipping</span><strong>{{ \App\Support\Money::format($invoice->shipping_amount, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between fs-5 border-top pt-2 mt-2"><span>Total</span><strong>{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Paid</span><strong>{{ \App\Support\Money::format($invoice->amount_paid, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Refunded</span><strong>{{ \App\Support\Money::format($invoice->refunded_amount, $invoice->currency) }}</strong></div>
            <div class="d-flex justify-content-between fs-5"><span>Balance</span><strong>{{ \App\Support\Money::format($invoice->balance_due, $invoice->currency) }}</strong></div>
        </div>
    </div>
</div>
