@extends('layouts.admin')

@section('title', 'Payment Settings')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Settings</h1></div>
            <div class="surface p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Stripe Readiness</h2>
                        <p class="muted mb-0">Customer checkout shows Stripe only when all required Stripe settings are configured.</p>
                    </div>
                    <span class="status-pill text-uppercase">{{ $stripeReadiness['status'] }}</span>
                </div>

                <dl class="row mt-3 mb-0 small">
                    <dt class="col-sm-4">Stripe API credentials</dt>
                    <dd class="col-sm-8">{{ $stripeReadiness['requirements']['STRIPE_KEY'] && $stripeReadiness['requirements']['STRIPE_SECRET'] ? 'Configured' : 'Missing' }}</dd>
                    <dt class="col-sm-4">Stripe webhook secret</dt>
                    <dd class="col-sm-8">{{ $stripeReadiness['requirements']['STRIPE_WEBHOOK_SECRET'] ? 'Configured' : 'Missing' }}</dd>
                    <dt class="col-sm-4">Credential mode</dt>
                    <dd class="col-sm-8">{{ $stripeReadiness['mode'] ? ucfirst($stripeReadiness['mode']) : 'Unknown' }}</dd>
                    <dt class="col-sm-4">Currency</dt>
                    <dd class="col-sm-8">{{ strtoupper($stripeReadiness['currency']) }}</dd>
                </dl>
                <p class="muted small mt-2 mb-0">Credential values are never displayed. Configure them through environment variables only.</p>

                @if ($stripeReadiness['missing'])
                    <div class="alert alert-warning mt-3 mb-0">
                        Missing or invalid requirements:
                        <strong>{{ implode(', ', $stripeReadiness['missing']) }}</strong>
                    </div>
                @else
                    <div class="alert alert-success mt-3 mb-0">Stripe is ready for customer checkout.</div>
                @endif
            </div>
            <div class="surface p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">PayPal</h2>
                        <p class="muted mb-0">{{ $paypalReadiness['note'] }}</p>
                    </div>
                    <span class="status-pill text-uppercase">{{ $paypalReadiness['status'] }}</span>
                </div>
                <div class="alert alert-secondary mt-3 mb-0">
                    PayPal is deferred and is not part of Stage 3 acceptance. It does not affect Stripe readiness.
                </div>
            </div>
            <form class="surface p-4" method="POST" action="{{ route('admin.payments.settings.update') }}">
                @csrf
                <div class="row g-3">
                    @foreach ($settings as $key => $value)
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label" for="{{ $key }}">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                            @if (is_bool($value))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" id="{{ $key }}" name="{{ $key }}" type="checkbox" value="1" @checked($value)>
                                </div>
                            @elseif (str_contains($key, 'instructions') || str_contains($key, 'terms'))
                                <textarea class="form-control" id="{{ $key }}" name="{{ $key }}" rows="3">{{ old($key, $value) }}</textarea>
                            @else
                                <input class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $value) }}">
                            @endif
                        </div>
                    @endforeach
                    <div class="col-12"><button class="btn btn-primary">Save Settings</button></div>
                </div>
            </form>
        </div>
    </section>
@endsection
