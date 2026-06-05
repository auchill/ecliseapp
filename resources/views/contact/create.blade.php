@extends('layouts.app')

@section('title', 'Contact Us')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Contact</p>
            <h1 class="display-5 fw-bold mb-3">Reach Eclise Technology Inc.</h1>
            <p class="fs-5 mb-0">Send a message about repairs, parts, device sales, or accessories.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="surface p-4 h-100">
                        <h2 class="h4 fw-bold">Service Desk</h2>
                        <p class="muted">Use the form for general questions. For repair tracking, use the tracking page with your Eclise tracking number.</p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2"></i>Track Repair</a>
                            <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-tools me-2"></i>Book Repair</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <form class="surface p-4" method="POST" action="{{ route('contact.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone</label>
                                <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="subject">Subject</label>
                                <input class="form-control" id="subject" name="subject" value="{{ old('subject') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send me-2"></i>Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
