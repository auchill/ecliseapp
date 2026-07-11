@extends('layouts.admin')

@section('title', 'Quote #'.$quote->id)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Quote #{{ $quote->id }}</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $quote->customer?->full_name ?? 'Customer unavailable' }} &middot; {{ $quote->deviceLabel() }}</h1>
                </div>
                <div class="d-flex gap-2">
                    @unless ($quote->converted_to_repair || $quote->status === 'rejected')
                        <a class="btn btn-primary" href="{{ route('admin.quotes.convert.create', $quote) }}"><i class="bi bi-tools me-2"></i>Create Repair</a>
                    @endunless
                    <a class="btn btn-outline-primary" href="{{ route('admin.quotes.index') }}"><i class="bi bi-arrow-left me-2"></i>Quotes</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="surface p-4 mb-4">
                        <h2 class="h5 fw-bold">Request Details</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr><th scope="row">Email</th><td>{{ $quote->customer?->email ?? 'Unavailable' }}</td></tr>
                                    <tr><th scope="row">Phone</th><td>{{ $quote->customer?->phone ?? 'Unavailable' }}</td></tr>
                                    <tr><th scope="row">Device type</th><td>{{ $quote->deviceType?->name }}</td></tr>
                                    <tr><th scope="row">Brand</th><td>{{ $quote->deviceBrand?->name }}</td></tr>
                                    <tr><th scope="row">Model</th><td>{{ $quote->deviceModelName() }}</td></tr>
                                    <tr><th scope="row">Issue</th><td>{{ $quote->issueCategory?->name }}</td></tr>
                                    <tr><th scope="row">Preferred date</th><td>{{ $quote->preferred_date?->format('M j, Y') ?? 'Not set' }} {{ $quote->preferred_time }}</td></tr>
                                    <tr><th scope="row">Description</th><td>{{ $quote->issue_description }}</td></tr>
                                    @if ($quote->repair)
                                        <tr><th scope="row">Repair</th><td><a href="{{ route('admin.repairs.show', $quote->repair) }}">{{ $quote->repair->repair_number }}</a></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        @if ($quote->device_image)
                            <a class="btn btn-outline-primary btn-sm" href="{{ asset('storage/'.$quote->device_image) }}" target="_blank" rel="noopener">View device image</a>
                        @endif
                    </div>
                </div>
                <div class="col-lg-5">
                    <form class="surface p-4" method="POST" action="{{ route('admin.quotes.update', $quote) }}">
                        @csrf
                        @method('PATCH')
                        <h2 class="h5 fw-bold">Review</h2>
                        <div class="mb-3">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $quote->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin_note">Admin note</label>
                            <textarea class="form-control" id="admin_note" name="admin_note" rows="6">{{ old('admin_note', $quote->admin_note) }}</textarea>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save Quote</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
