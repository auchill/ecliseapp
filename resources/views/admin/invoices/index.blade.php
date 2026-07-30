@extends('layouts.admin')

@section('title', 'Invoices')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Invoices</h1></div>
            <form class="surface p-4 mb-4" method="GET">
                <div class="row g-3">
                    <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Invoice, customer, email"></div>
                    <div class="col-md-3"><input class="form-control" name="status" value="{{ request('status') }}" placeholder="Status"></div>
                    <div class="col-md-3"><input class="form-control" name="type" value="{{ request('type') }}" placeholder="Type"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
                </div>
            </form>
            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Type</th><th>Status</th><th>Total</th><th>Balance</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->customer?->full_name }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $invoice->type)) }}</td>
                                <td><span class="status-pill">{{ $invoice->statusLabel() }}</span></td>
                                <td>{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</td>
                                <td>{{ \App\Support\Money::format($invoice->balance_due, $invoice->currency) }}</td>
                                <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.invoices.show', $invoice) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No invoices found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $invoices->links() }}
            </div>
        </div>
    </section>
@endsection
