@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                <div><p class="eyebrow">Invoice</p><h1 class="display-6 fw-bold mb-0">{{ $invoice->invoice_number }}</h1></div>
                <a class="btn btn-outline-primary" href="{{ route('invoices.print', $invoice) }}" target="_blank">Print</a>
            </div>
            @include('invoices.partials.invoice-panel', ['invoice' => $invoice])
        </div>
    </section>
@endsection
