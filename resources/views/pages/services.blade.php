@extends('layouts.app')

@section('title', 'Repair and Technology Services')
@section('meta_description', 'Explore Eclise Technology Inc. services for phone repair, tablet repair, computer repair, diagnostics, replacement parts, products and repair tracking.')

@section('content')
    <x-page-hero
        eyebrow="Services"
        title="Repair, diagnostics, parts and product services for everyday devices."
        description="Choose the service area that fits your device issue, parts enquiry, product need or existing repair question."
    >
        <x-slot:actions>
            <a class="btn btn-primary btn-lg" href="{{ route('repairs.create') }}">Book a Repair</a>
            <a class="btn btn-outline-light btn-lg" href="{{ route('parts.index') }}">Browse Parts</a>
            <a class="btn btn-outline-light btn-lg" href="{{ route('shop.index') }}">Visit Shop</a>
        </x-slot:actions>
    </x-page-hero>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                @foreach ([
                    [
                        'icon' => 'bi-phone',
                        'title' => 'Phone and Tablet Repair',
                        'copy' => 'Repair assessment for common device problems, including screen-related issues, batteries, charging problems, cameras, speakers and other hardware concerns.',
                        'items' => ['Screen-related repairs', 'Battery-related issues', 'Charging concerns', 'Camera or speaker issues', 'Device diagnostics'],
                        'href' => route('repairs.create'),
                        'action' => 'Book Repair',
                    ],
                    [
                        'icon' => 'bi-pc-display',
                        'title' => 'Computer and Laptop Services',
                        'copy' => 'Support for laptop and desktop concerns where inspection, parts and repair options can determine the best next step.',
                        'items' => ['Hardware diagnostics', 'Storage or memory-related assistance', 'Performance troubleshooting', 'Component replacement assessment', 'Laptop and desktop repair review'],
                        'href' => route('quotes.create'),
                        'action' => 'Request Quote',
                        'requiresAuth' => true,
                    ],
                    [
                        'icon' => 'bi-clipboard2-pulse',
                        'title' => 'Device Diagnostics',
                        'copy' => 'Diagnostics help identify likely causes and the most suitable repair, part or replacement path before work proceeds.',
                        'items' => ['Issue review', 'Device and model confirmation', 'Repair path recommendations', 'Part availability checks', 'Customer communication'],
                        'href' => route('contact.create'),
                        'action' => 'Ask a Question',
                    ],
                    [
                        'icon' => 'bi-cpu',
                        'title' => 'Replacement Parts',
                        'copy' => 'Browse replacement parts for pricing visibility and compatibility discussions before purchase or installation.',
                        'items' => ['Parts browsing', 'Price visibility', 'Compatibility review', 'Repair option planning', 'Availability confirmation'],
                        'href' => route('parts.index'),
                        'action' => 'Browse Parts',
                    ],
                    [
                        'icon' => 'bi-bag-check',
                        'title' => 'Phones, Computers and Accessories',
                        'copy' => 'Shop available technology products, including new products, pre-owned devices and accessories where listed in the catalog.',
                        'items' => ['New products', 'Pre-owned devices', 'Certified pre-owned device listings', 'Accessories', 'Order tracking'],
                        'href' => route('shop.index'),
                        'action' => 'Visit Shop',
                    ],
                    [
                        'icon' => 'bi-search',
                        'title' => 'Repair and Order Tracking',
                        'copy' => 'Use existing repair or order details to check customer-visible progress and next-step information.',
                        'items' => ['Repair number lookup', 'Order tracking', 'Status updates', 'Customer-visible notes', 'Payment or completion guidance'],
                        'href' => route('repairs.track'),
                        'action' => 'Track Repair',
                    ],
                ] as $service)
                    <div class="col-md-6 col-xl-4">
                        <x-feature-card :icon="$service['icon']" :title="$service['title']" :copy="$service['copy']" :href="$service['href']" :action="$service['action']" :requires-auth="$service['requiresAuth'] ?? false">
                            <ul class="list-unstyled mb-0">
                                @foreach ($service['items'] as $item)
                                    <li class="mb-2"><i class="bi bi-check2 text-primary me-2" aria-hidden="true"></i>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </x-feature-card>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="surface service-notice p-4 p-lg-5">
                        <p class="eyebrow">Service Notice</p>
                        <h2 class="h3 fw-bold">Repair options depend on the device, issue and available parts.</h2>
                        <p class="muted fs-5 mb-0">Pricing and repair availability may vary based on the device model, inspection findings, selected replacement part and current inventory. Request a quote or contact Eclise to confirm the best next step before proceeding.</p>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4 p-lg-5 h-100">
                        <p class="eyebrow">Useful Links</p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary" href="{{ route('quotes.create') }}" @guest data-auth-required data-intended-url="{{ route('quotes.create') }}" @endguest><i class="bi bi-chat-square-text me-2" aria-hidden="true"></i>Get a Quote</a>
                            <a class="btn btn-outline-primary" href="{{ route('repairs.create') }}"><i class="bi bi-tools me-2" aria-hidden="true"></i>Book a Repair</a>
                            <a class="btn btn-outline-primary" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2" aria-hidden="true"></i>Track Repair</a>
                            <a class="btn btn-outline-primary" href="{{ route('orders.track') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Track Order</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <p class="eyebrow">How Service Moves Forward</p>
                    <h2 class="display-6 fw-bold mb-0">From device details to a confirmed next step.</h2>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['title' => 'Select the service path', 'copy' => 'Choose quote, repair booking, parts, shop or tracking based on what you need.'],
                    ['title' => 'Provide issue details', 'copy' => 'Share the device type, model and issue details needed for assessment.'],
                    ['title' => 'Review available options', 'copy' => 'Eclise can review repair, part or replacement choices before the customer commits.'],
                    ['title' => 'Book or approve the repair', 'copy' => 'Continue with a repair number or approve a proposal when the repair is ready for that step.'],
                    ['title' => 'Track progress', 'copy' => 'Use customer-facing lookup tools to follow repair or order progress.'],
                ] as $index => $step)
                    <div class="col-md-6 col-xl">
                        <div class="surface process-step p-4">
                            <span class="process-step-number mb-3">{{ $index + 1 }}</span>
                            <h3 class="h5 fw-bold">{{ $step['title'] }}</h3>
                            <p class="muted mb-0">{{ $step['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-cta-band eyebrow="Need Help" title="Tell Eclise what you are trying to solve." copy="Use the contact form for repair questions, parts enquiries, product questions or support with an existing repair or order.">
        <a class="btn btn-primary btn-lg" href="{{ route('contact.create') }}">Contact Us</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('parts.index') }}">Browse Parts</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('shop.index') }}">Visit Shop</a>
    </x-cta-band>
@endsection
