@extends('layouts.app')

@section('title', 'About Eclise Technology Inc.')
@section('meta_description', 'Learn about Eclise Technology Inc., a technology repair, parts and product service focused on practical device support and clear customer communication.')

@section('content')
    <x-page-hero
        eyebrow="About Eclise"
        title="Technology repair, parts and product services with a practical customer workflow."
        description="Eclise Technology Inc. helps customers understand device issues, review suitable repair or replacement options, and stay informed through quote, booking and tracking tools."
        :image="asset('images/brand/logo_main2.png')"
        image-alt="Eclise Technology Inc. logo"
    >
        <x-slot:actions>
            <a class="btn btn-primary btn-lg" href="{{ route('services') }}">View Services</a>
            <a class="btn btn-outline-light btn-lg" href="{{ route('contact.create') }}">Contact Us</a>
        </x-slot:actions>
    </x-page-hero>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <p class="eyebrow">Company Overview</p>
                    <h2 class="display-6 fw-bold">Support for repair decisions, replacement parts and everyday technology needs.</h2>
                    <p class="muted fs-5">Eclise Technology Inc. provides customer-facing tools and services for device repair, diagnostics, replacement parts, phones, computers and accessories. The service flow is built to help customers submit details, review next steps and track progress when a repair is underway.</p>
                    <p class="muted mb-0">The goal is straightforward: help customers make informed choices about repairing, replacing or supporting the technology they rely on.</p>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4 p-lg-5">
                        <h3 class="h4 fw-bold">Mission</h3>
                        <p class="muted fs-5">Provide practical technology solutions through reliable service, transparent communication and access to suitable repair, parts and product options.</p>
                        <div class="feature-list mt-4">
                            @foreach ([
                                'Help customers extend the useful life of their devices',
                                'Present repair and replacement options clearly',
                                'Support communication through online quote, booking and tracking tools',
                                'Make parts and product browsing easier to understand',
                            ] as $item)
                                <div class="feature-list-item">
                                    <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <p class="eyebrow">Service Approach</p>
                    <h2 class="display-6 fw-bold mb-0">A repair process based on context, options and communication.</h2>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'bi-clipboard2-pulse', 'title' => 'Understand the issue', 'copy' => 'Device type, model, issue details and customer notes help frame the repair assessment.'],
                    ['icon' => 'bi-ui-checks', 'title' => 'Present suitable options', 'copy' => 'Repair, part and product choices are positioned as options to review, not unsupported guarantees.'],
                    ['icon' => 'bi-chat-left-text', 'title' => 'Keep communication clear', 'copy' => 'Quote requests, messages and repair updates keep the customer workflow focused on the next action.'],
                    ['icon' => 'bi-search', 'title' => 'Support tracking', 'copy' => 'Repair and order lookup tools help customers check progress using the details already in the system.'],
                ] as $item)
                    <div class="col-md-6 col-xl-3">
                        <x-feature-card :icon="$item['icon']" :title="$item['title']" :copy="$item['copy']" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <p class="eyebrow">Why Choose Eclise</p>
                    <h2 class="display-6 fw-bold">Repair, parts and products in one customer experience.</h2>
                    <p class="muted fs-5 mb-0">Customers can move from service information to quote requests, approved repair booking, parts browsing, product browsing and tracking without switching between disconnected systems.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach ([
                            'Phone, tablet, computer and laptop repair workflows',
                            'Online quote and repair-number continuation',
                            'Repair status tracking for customer-visible updates',
                            'Replacement parts browsing for availability and price visibility',
                            'New and pre-owned product options where available',
                            'Contact message handling through the admin area',
                        ] as $item)
                            <div class="col-sm-6">
                                <div class="feature-list-item surface p-3 h-100">
                                    <i class="bi bi-check2-circle mt-1" aria-hidden="true"></i>
                                    <span>{{ $item }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-cta-band eyebrow="Work With Eclise" title="Start with the service path that matches your need." copy="Review available services, request a repair quote, or send a message for help choosing the right next step.">
        <a class="btn btn-primary btn-lg" href="{{ route('services') }}">View Services</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('quotes.create') }}" @guest data-auth-required data-intended-url="{{ route('quotes.create') }}" @endguest>Get a Quote</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('contact.create') }}">Contact Us</a>
    </x-cta-band>
@endsection
