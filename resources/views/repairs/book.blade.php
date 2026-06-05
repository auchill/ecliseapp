@extends('layouts.app')

@section('title', 'Book a Repair')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair Booking</p>
            <h1 class="display-5 fw-bold mb-3">Tell us what needs repair.</h1>
            <p class="fs-5 mb-0">Submit your device details and appointment preference to receive an Eclise tracking number.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="customer_name">Customer name</label>
                        <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone number</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_type">Device type</label>
                        <select class="form-select" id="device_type" name="device_type" required>
                            @foreach (['Phone', 'Laptop', 'Desktop', 'Tablet', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(old('device_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_brand">Device brand</label>
                        <input class="form-control" id="device_brand" name="device_brand" value="{{ old('device_brand') }}" placeholder="Apple, Samsung, Dell">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_model">Device model</label>
                        <input class="form-control" id="device_model" name="device_model" value="{{ old('device_model') }}" placeholder="iPhone 13, ThinkPad T14">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="issue_category">Issue category</label>
                        <input class="form-control" id="issue_category" name="issue_category" value="{{ old('issue_category') }}" placeholder="Screen, battery, charging, diagnosis" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_appointment_date">Preferred date</label>
                        <input class="form-control" id="preferred_appointment_date" name="preferred_appointment_date" type="date" value="{{ old('preferred_appointment_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_appointment_time">Preferred time</label>
                        <input class="form-control" id="preferred_appointment_time" name="preferred_appointment_time" type="time" value="{{ old('preferred_appointment_time') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="issue_description">Issue description</label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required>{{ old('issue_description') }}</textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="device_image">Device image</label>
                        <input class="form-control" id="device_image" name="device_image" type="file" accept="image/*">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" id="terms_accepted" name="terms_accepted" type="checkbox" value="1" @checked(old('terms_accepted')) required>
                            <label class="form-check-label" for="terms_accepted">I agree that Eclise Technology Inc. may review this repair request and contact me about service options.</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send me-2"></i>Submit Repair Request</button>
                        <a class="btn btn-outline-primary btn-lg" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2"></i>Track Existing Repair</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
