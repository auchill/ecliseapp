@extends('layouts.app')

@section('title', 'Book a Repair')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair</p>
            <h1 class="display-5 fw-bold mb-3">Continue with your repair number.</h1>
            <p class="fs-5 mb-0">After your quote is approved, enter the repair number Eclise sent to your email.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <form class="surface p-4 p-lg-5" method="POST" action="{{ route('repairs.store') }}">
                        @csrf
                        <label class="form-label" for="repair_number">Repair number</label>
                        <div class="input-group input-group-lg">
                            <input class="form-control" id="repair_number" name="repair_number" value="{{ old('repair_number') }}" placeholder="ECL-REP-2026-0000001" required>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-right-circle me-2"></i>Continue</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Need a price first?</h2>
                        <p class="muted">Start with a quote request. Eclise will review the issue and contact you before creating the repair.</p>
                        <a class="btn btn-outline-primary" href="{{ route('quotes.create') }}"><i class="bi bi-chat-square-text me-2"></i>Get a Quote</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
