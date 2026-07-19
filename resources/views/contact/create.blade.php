@extends('layouts.app')

@section('title', 'Contact Eclise Technology Inc.')
@section('meta_description', 'Contact Eclise Technology Inc. for repair questions, quote help, booking assistance, parts enquiries, product questions and existing repair or order support.')

@section('content')
    @php
        $contact = config('eclise.contact', []);
        $enquiryTypes = config('eclise.enquiry_types', []);
        $hasContactDetails = filled($contact['email'] ?? null)
            || filled($contact['phone'] ?? null)
            || filled($contact['address'] ?? null)
            || filled($contact['hours'] ?? null);
    @endphp

    <x-page-hero
        eyebrow="Contact"
        title="Reach Eclise Technology Inc."
        description="Send a message about repair questions, quote assistance, booking help, parts enquiries, product questions, existing repairs, orders or general enquiries."
    >
        <x-slot:actions>
            <a class="btn btn-primary btn-lg" href="{{ route('repairs.track') }}">Track Repair</a>
            <a class="btn btn-outline-light btn-lg" href="{{ route('orders.track') }}">Track Order</a>
        </x-slot:actions>
    </x-page-hero>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="surface p-4 p-lg-5 h-100">
                        <p class="eyebrow">Contact Options</p>
                        <h2 class="h4 fw-bold">Start with the form or use a self-service link.</h2>
                        <p class="muted">Use the form for repair questions, quote assistance, booking help, parts enquiries, product questions and existing repair or order questions.</p>

                        <div class="contact-method-list mb-4">
                            @if ($hasContactDetails)
                                @if (filled($contact['email'] ?? null))
                                    <div class="contact-method">
                                        <i class="bi bi-envelope" aria-hidden="true"></i>
                                        <div>
                                            <strong>Email</strong>
                                            <div><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div>
                                        </div>
                                    </div>
                                @endif
                                @if (filled($contact['phone'] ?? null))
                                    <div class="contact-method">
                                        <i class="bi bi-telephone" aria-hidden="true"></i>
                                        <div>
                                            <strong>Phone</strong>
                                            <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></div>
                                        </div>
                                    </div>
                                @endif
                                @if (filled($contact['address'] ?? null))
                                    <div class="contact-method">
                                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                        <div>
                                            <strong>Address</strong>
                                            <div>{{ $contact['address'] }}</div>
                                        </div>
                                    </div>
                                @endif
                                @if (filled($contact['hours'] ?? null))
                                    <div class="contact-method">
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <div>
                                            <strong>Hours</strong>
                                            <div>{{ $contact['hours'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-light border mb-0">
                                    Verified phone, email, address and hours have not been configured yet. Use the contact form below for documented enquiries.
                                </div>
                            @endif
                        </div>

                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary" href="{{ route('quotes.create') }}" @guest data-auth-required data-intended-url="{{ route('quotes.create') }}" @endguest><i class="bi bi-chat-square-text me-2" aria-hidden="true"></i>Get a Quote</a>
                            <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-tools me-2" aria-hidden="true"></i>Book Repair</a>
                            <a class="btn btn-outline-primary" href="{{ route('parts.index') }}"><i class="bi bi-cpu me-2" aria-hidden="true"></i>Browse Parts</a>
                            <a class="btn btn-outline-primary" href="{{ route('shop.index') }}"><i class="bi bi-bag me-2" aria-hidden="true"></i>Visit Shop</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <form class="surface p-4 p-lg-5" method="POST" action="{{ route('contact.store') }}" data-eclise-contact-form novalidate>
                        @csrf
                        <div class="mb-4">
                            <p class="eyebrow mb-2">Message Eclise</p>
                            <h2 class="h4 fw-bold mb-0">Tell us what you need help with.</h2>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Full Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email Address <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number <span class="text-muted">(optional)</span></label>
                                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="enquiry_type">Enquiry Type <span class="text-danger" aria-hidden="true">*</span></label>
                                <select class="form-select @error('enquiry_type') is-invalid @enderror" id="enquiry_type" name="enquiry_type" required>
                                    <option value="">Choose enquiry type</option>
                                    @foreach ($enquiryTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('enquiry_type', 'general') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('enquiry_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="subject">Subject <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Message <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                                @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send me-2" aria-hidden="true"></i>Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
