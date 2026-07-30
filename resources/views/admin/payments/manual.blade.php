@extends('layouts.admin')

@section('title', 'Record Manual Payment')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4">
                <p class="eyebrow">Payments</p>
                <h1 class="display-6 fw-bold mb-0">Record Manual Payment</h1>
            </div>

            <form class="surface p-4" method="POST" action="{{ route('admin.payments.manual.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_id">Invoice</label>
                        <select class="form-select" id="invoice_id" name="invoice_id" required>
                            <option value="">Select invoice</option>
                            @foreach ($invoices as $option)
                                <option value="{{ $option->id }}" @selected(old('invoice_id', $invoice?->id) == $option->id)>
                                    {{ $option->invoice_number }} · {{ $option->customer?->full_name }} · {{ \App\Support\Money::format($option->balance_due, $option->currency) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="method">Method</label>
                        <select class="form-select" id="method" name="method" required>
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="purpose">Purpose</label>
                        <select class="form-select" id="purpose" name="purpose">
                            @foreach (['deposit' => 'Deposit', 'balance' => 'Balance', 'full_payment' => 'Full payment', 'shop_order' => 'Shop order', 'additional_charge' => 'Additional charge'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('purpose') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $invoice?->balance_due) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="currency">Currency</label>
                        <input class="form-control" id="currency" name="currency" value="{{ old('currency', $invoice?->currency ?: 'cad') }}" maxlength="3" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_date">Payment date</label>
                        <input class="form-control" id="payment_date" name="payment_date" type="datetime-local" value="{{ old('payment_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="manual_reference">Reference</label>
                        <input class="form-control" id="manual_reference" name="manual_reference" value="{{ old('manual_reference') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="proof">Proof attachment</label>
                        <input class="form-control" id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf,.heic,.webp">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="customer_note">Customer note</label>
                        <textarea class="form-control" id="customer_note" name="customer_note" rows="3">{{ old('customer_note') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_note">Internal note</label>
                        <textarea class="form-control" id="admin_note" name="admin_note" rows="3">{{ old('admin_note') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-2"></i>Record Payment</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
