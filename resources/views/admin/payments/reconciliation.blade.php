@extends('layouts.admin')

@section('title', 'Payment Reconciliation')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Reconciliation</h1></div>
            <form class="surface p-4 mb-4" method="POST" action="{{ route('admin.payments.reconciliation.run') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3"><input class="form-control" name="provider" placeholder="Provider" value="{{ old('provider') }}"></div>
                    <div class="col-md-3"><input class="form-control" name="payment" placeholder="Payment number" value="{{ old('payment') }}"></div>
                    <div class="col-md-2"><input class="form-control" name="from" type="date" value="{{ old('from') }}"></div>
                    <div class="col-md-2"><input class="form-control" name="to" type="date" value="{{ old('to') }}"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Run Check</button></div>
                </div>
            </form>
            @if ($report)
                <div class="surface p-4 table-responsive">
                    <h2 class="h5 fw-bold">Issues: {{ $report['issue_count'] }}</h2>
                    <table class="table">
                        <thead><tr><th>Payment</th><th>Invoice</th><th>Code</th><th>Message</th><th>Recommended action</th></tr></thead>
                        <tbody>
                            @forelse ($report['issues'] as $issue)
                                <tr><td>{{ $issue['payment'] }}</td><td>{{ $issue['invoice'] }}</td><td>{{ $issue['code'] }}</td><td>{{ $issue['message'] }}</td><td>{{ $issue['recommended_action'] }}</td></tr>
                            @empty
                                <tr><td colspan="5">No discrepancies found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
