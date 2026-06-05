@extends('layouts.app')

@section('title', 'Track Repair')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair Tracking</p>
            <h1 class="display-5 fw-bold mb-3">Check current repair progress.</h1>
            <p class="fs-5 mb-0">Enter the repair tracking number and the email or phone number used on the booking.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 mb-5" method="POST" action="{{ route('repairs.track.submit') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label" for="tracking_number">Tracking number</label>
                        <input class="form-control" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $booking->tracking_number ?? '') }}" placeholder="ECL-REP-2026-0001" required>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="contact">Email or phone</label>
                        <input class="form-control" id="contact" name="contact" value="{{ old('contact') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-2"></i>Track</button>
                    </div>
                </div>
            </form>

            @isset($booking)
                <div class="surface p-4 p-lg-5">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <p class="eyebrow">Repair {{ $booking->tracking_number }}</p>
                            <h2 class="display-6 fw-bold mb-0">{{ $booking->deviceLabel() }}</h2>
                        </div>
                        <span class="status-pill">{{ $booking->status }}</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="table-responsive">
                                <table class="table">
                                    <tbody>
                                        <tr><th scope="row">Customer</th><td>{{ $booking->customer_name }}</td></tr>
                                        <tr><th scope="row">Issue</th><td>{{ $booking->issue_category }}</td></tr>
                                        <tr><th scope="row">Description</th><td>{{ $booking->issue_description }}</td></tr>
                                        <tr><th scope="row">Estimated completion</th><td>{{ $booking->estimated_completion_date?->format('M j, Y') ?? 'To be confirmed' }}</td></tr>
                                        <tr><th scope="row">Notes</th><td>{{ $booking->customer_notes ?: 'No customer notes yet.' }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h3 class="h5 fw-bold mb-3">Repair Timeline</h3>
                            <div class="timeline">
                                @forelse ($booking->publicStatusUpdates as $update)
                                    <div class="timeline-item">
                                        <h4 class="h6 mb-1">{{ $update->status }}</h4>
                                        <p class="muted small mb-0">{{ $update->note }}</p>
                                        <span class="small muted">{{ $update->created_at->format('M j, Y g:i A') }}</span>
                                    </div>
                                @empty
                                    <p class="muted mb-0">No timeline updates yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endisset
        </div>
    </section>
@endsection
