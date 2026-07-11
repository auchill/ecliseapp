@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <section class="hero">
        <div class="container py-5">
            <p class="eyebrow mb-3">Repair. Reuse. Reconnect.</p>
            <h1 class="fw-black mb-4">Your Tech. Our Expertise. Your Peace of Mind.</h1>
            <p class="fs-5 mb-4">Professional phone and computer repairs, quality used devices, accessories, and trusted tech solutions.</p>
            <div class="d-flex flex-wrap gap-3">
                <a class="btn btn-primary btn-lg px-4" href="{{ route('repairs.create') }}"><i class="bi bi-tools me-2"></i>Book a Repair</a>
                <a class="btn btn-outline-light btn-lg px-4" href="{{ route('repairs.track') }}"><i class="bi bi-search me-2"></i>Track Repair</a>
                <a class="btn btn-light btn-lg px-4" href="{{ route('shop.index') }}"><i class="bi bi-bag me-2"></i>Shop Now</a>
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <p class="eyebrow">What We Handle</p>
                    <h2 class="display-6 fw-bold mb-0">Repair and retail services for everyday devices.</h2>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a class="btn btn-outline-primary" href="{{ route('services') }}"><i class="bi bi-grid me-2"></i>View Services</a>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'bi-phone', 'title' => 'Phone Repairs', 'copy' => 'Screen, battery, charging, camera, speaker, and back cover service.'],
                    ['icon' => 'bi-laptop', 'title' => 'Computer Repairs', 'copy' => 'Laptop and desktop diagnostics, upgrades, keyboards, batteries, and displays.'],
                    ['icon' => 'bi-bag-check', 'title' => 'Device Sales', 'copy' => 'Used and new phones, computers, and useful accessories.'],
                    ['icon' => 'bi-cpu', 'title' => 'Parts Price Check', 'copy' => 'Customer-friendly price visibility for repair parts before service.'],
                ] as $service)
                    <div class="col-md-6 col-xl-3">
                        <div class="surface h-100 p-4">
                            <span class="service-icon mb-3"><i class="bi {{ $service['icon'] }}"></i></span>
                            <h3 class="h5 fw-bold">{{ $service['title'] }}</h3>
                            <p class="muted mb-0">{{ $service['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <p class="eyebrow">Repair</p>
                    <h2 class="display-6 fw-bold">Start with the device, issue, and appointment window.</h2>
                    <p class="muted fs-5">Each repair gets a unique repair number so customers can follow diagnosis, parts status, repair progress, and pickup readiness.</p>
                    <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-calendar-check me-2"></i>Book a Repair</a>
                </div>
                <div class="col-lg-6">
                    <div class="surface p-4">
                        <div class="d-flex gap-3 mb-4">
                            <span class="status-pill">Submitted</span>
                            <span class="status-pill">Diagnosis</span>
                            <span class="status-pill">Ready</span>
                        </div>
                        <div class="timeline">
                            <div class="timeline-item">
                                <h3 class="h6 mb-1">Request received</h3>
                                <p class="muted mb-0">Customer details and issue description are saved.</p>
                            </div>
                            <div class="timeline-item">
                                <h3 class="h6 mb-1">Technician review</h3>
                                <p class="muted mb-0">Admin can update status, notes, and estimated completion.</p>
                            </div>
                            <div class="timeline-item mb-0">
                                <h3 class="h6 mb-1">Pickup or next step</h3>
                                <p class="muted mb-0">Customer-visible notes keep repair progress clear.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                <div>
                    <p class="eyebrow">Shop Preview</p>
                    <h2 class="display-6 fw-bold mb-0">Featured devices and accessories.</h2>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('shop.index') }}"><i class="bi bi-bag me-2"></i>Open Shop</a>
            </div>
            <div class="row g-4">
                @forelse ($featuredProducts as $product)
                    <div class="col-md-6 col-xl-3">
                        <div class="surface product-card h-100 overflow-hidden">
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                            <div class="p-4">
                                <p class="eyebrow mb-1">{{ $product->category?->name }}</p>
                                <h3 class="h5 fw-bold">{{ $product->name }}</h3>
                                <p class="muted small mb-3">{{ $product->condition }} &middot; {{ $product->brand }}</p>
                                <div class="d-flex align-items-center justify-content-between">
                                    <strong>${{ number_format($product->currentPrice(), 2) }}</strong>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('products.show', $product) }}"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="surface p-4">Products will appear after the catalog is seeded.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <p class="eyebrow">Parts Price Check</p>
                    <h2 class="display-6 fw-bold">Verify common repair part prices before service.</h2>
                    <p class="muted fs-5 mb-4">Parts are shown for price verification only. Please contact us for repair service availability.</p>
                    <a class="btn btn-primary" href="{{ route('parts.index') }}"><i class="bi bi-search me-2"></i>Check Parts</a>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4">
                        @forelse ($featuredParts as $part)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $part->name }}</strong>
                                    <div class="small muted">{{ $part->brand }} &middot; {{ $part->model_compatibility }}</div>
                                </div>
                                <strong>${{ number_format($part->displayPrice(), 2) }}</strong>
                            </div>
                        @empty
                            <p class="mb-0">Parts will appear after the catalog is seeded.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
