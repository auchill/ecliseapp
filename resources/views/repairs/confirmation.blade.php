@extends('layouts.app')

@section('title', 'Repair Confirmation')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4 p-lg-5">
                <p class="eyebrow">Repair Submitted</p>
                <h1 class="display-6 fw-bold">Tracking number: {{ $booking->tracking_number }}</h1>
                <p class="muted fs-5">Use this number with {{ $booking->email }} or {{ $booking->phone }} to track your repair status.</p>
                @if ($booking->isShipping())
                    <div class="alert alert-info">Your repaired device will be shipped after service. Tracking details will appear when available.</div>
                @else
                    <div class="alert alert-info">We will notify you when your repaired device is ready for pickup.</div>
                @endif

                <div class="row g-4 mt-2">
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Customer</th><td>{{ $booking->customer_name }}</td></tr>
                                    <tr><th scope="row">Device</th><td>{{ $booking->deviceLabel() }}</td></tr>
                                    <tr><th scope="row">Issue</th><td>{{ $booking->issue_category }}</td></tr>
                                    <tr><th scope="row">Status</th><td><span class="status-pill">{{ $booking->status }}</span></td></tr>
                                    <tr><th scope="row">Fulfillment</th><td>{{ $booking->fulfillmentLabel() }}</td></tr>
                                    <tr><th scope="row">Shipping cost</th><td>${{ number_format($booking->shipping_cost, 2) }}</td></tr>
                                    <tr><th scope="row">Delivery carrier</th><td>{{ $booking->delivery_carrier ?: 'Not available yet' }}</td></tr>
                                    <tr><th scope="row">Delivery tracking</th><td>{{ $booking->delivery_tracking_number ?: 'Not available yet' }}</td></tr>
                                    <tr><th scope="row">Appointment</th><td>{{ $booking->preferred_appointment_date?->format('M j, Y') ?? 'Not set' }} {{ $booking->preferred_appointment_time }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        @if ($booking->isShipping())
                            <h2 class="h5 fw-bold mt-4">Shipping Address</h2>
                            @foreach ($booking->shippingAddressLines() as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        @endif
                    </div>
                    <div class="col-lg-5">
                        <div class="timeline">
                            @foreach ($booking->publicStatusUpdates as $update)
                                <div class="timeline-item">
                                    <h2 class="h6 mb-1">{{ $update->status }}</h2>
                                    <p class="muted small mb-0">{{ $update->note }}</p>
                                    <span class="small muted">{{ $update->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2"></i>Track Repair</a>
                    <a class="btn btn-outline-primary" href="{{ route('home') }}"><i class="bi bi-house me-2"></i>Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
