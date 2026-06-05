@extends('layouts.app')

@section('title', 'Repair '.$repair->tracking_number)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Repair {{ $repair->tracking_number }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $repair->customer_name }} &middot; {{ $repair->deviceLabel() }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.repairs.index') }}"><i class="bi bi-arrow-left me-2"></i>Repairs</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <form class="surface p-4" method="POST" action="{{ route('admin.repairs.update', $repair) }}">
                        @csrf
                        @method('PATCH')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(old('status', $repair->status) === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="estimated_completion_date">Estimated completion</label>
                                <input class="form-control" id="estimated_completion_date" name="estimated_completion_date" type="date" value="{{ old('estimated_completion_date', $repair->estimated_completion_date?->toDateString()) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customer_notes">Customer-visible notes</label>
                                <textarea class="form-control" id="customer_notes" name="customer_notes" rows="4">{{ old('customer_notes', $repair->customer_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="internal_notes">Internal notes</label>
                                <textarea class="form-control" id="internal_notes" name="internal_notes" rows="4">{{ old('internal_notes', $repair->internal_notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="status_note">Timeline note</label>
                                <textarea class="form-control" id="status_note" name="status_note" rows="3">{{ old('status_note') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" id="is_customer_visible" name="is_customer_visible" type="checkbox" value="1" checked>
                                    <label class="form-check-label" for="is_customer_visible">Show timeline note to customer</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Update Repair</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4 mb-4">
                        <h2 class="h5 fw-bold">Repair Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Email</th><td>{{ $repair->email }}</td></tr>
                                    <tr><th scope="row">Phone</th><td>{{ $repair->phone }}</td></tr>
                                    <tr><th scope="row">Issue</th><td>{{ $repair->issue_category }}</td></tr>
                                    <tr><th scope="row">Description</th><td>{{ $repair->issue_description }}</td></tr>
                                    <tr><th scope="row">Appointment</th><td>{{ $repair->preferred_appointment_date?->format('M j, Y') ?? 'Not set' }} {{ $repair->preferred_appointment_time }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Timeline</h2>
                        <div class="timeline">
                            @foreach ($repair->statusUpdates as $update)
                                <div class="timeline-item">
                                    <h3 class="h6 mb-1">{{ $update->status }}</h3>
                                    <p class="muted small mb-0">{{ $update->note }}</p>
                                    <span class="small muted">{{ $update->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
