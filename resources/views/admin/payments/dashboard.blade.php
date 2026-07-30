@extends('layouts.admin')

@section('title', 'Payment Dashboard')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Payments</p>
                    <h1 class="display-6 fw-bold mb-0">Payment Dashboard</h1>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.payments.manual.create') }}"><i class="bi bi-cash-coin me-2"></i>Record Payment</a>
            </div>

            <div class="row g-3">
                @foreach ($cards as $label => $card)
                    <div class="col-md-6 col-xl-3">
                        <a class="surface p-4 d-block h-100 text-decoration-none" href="{{ $card['route'] }}">
                            <span class="muted small">{{ $label }}</span>
                            <strong class="d-block fs-3 mt-2">
                                {{ ($card['money'] ?? false) ? \App\Support\Money::format($card['value']) : $card['value'] }}
                            </strong>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
