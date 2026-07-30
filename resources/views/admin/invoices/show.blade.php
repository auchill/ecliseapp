@extends('layouts.admin')

@section('title', $invoice->invoice_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                <div><p class="eyebrow">Invoice</p><h1 class="display-6 fw-bold mb-0">{{ $invoice->invoice_number }}</h1></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary" href="{{ route('admin.invoices.print', $invoice) }}" target="_blank">Print</a>
                    @if ((float) $invoice->balance_due > 0)
                        <a class="btn btn-primary" href="{{ route('admin.payments.manual.create', ['invoice' => $invoice->id]) }}">Record Payment</a>
                    @endif
                </div>
            </div>
            @include('invoices.partials.invoice-panel', ['invoice' => $invoice])
        </div>
    </section>
@endsection
