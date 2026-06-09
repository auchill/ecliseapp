@extends('layouts.app')

@section('title', 'Quotes')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Quote Requests</h1>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.quotes.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Quote, customer, email, phone">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                </div>
            </form>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Quote</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Issue</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($quotes as $quote)
                                <tr>
                                    <td>{{ $quote->quote_number }}</td>
                                    <td>{{ $quote->customer_name }}<div class="small muted">{{ $quote->email }}</div></td>
                                    <td>{{ $quote->deviceLabel() }}</td>
                                    <td>{{ $quote->issueCategory?->name }}</td>
                                    <td><span class="status-pill">{{ $quote->statusLabel() }}</span></td>
                                    <td>{{ $quote->created_at->format('M j, Y') }}</td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.quotes.show', $quote) }}"><i class="bi bi-eye"></i><span class="visually-hidden">Open</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7">No quote requests found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $quotes->links() }}
            </div>
        </div>
    </section>
@endsection
