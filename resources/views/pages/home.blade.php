@extends('layouts.app')

@section('title', 'Device Repair, Parts and Technology Products')
@section('meta_description', 'Eclise Technology Inc. helps customers repair devices, browse replacement parts, and shop for phones, computers, and accessories.')

@section('content')
    @php
        $homeSlides = [
            [
                'label' => 'Device Repair',
                'heading' => 'Professional Phone, Tablet and Computer Repair',
                'copy' => 'Get help diagnosing and repairing common device problems through a clear repair process.',
                'image' => asset('images/brand/logo_main2.png'),
                'image_path' => 'images/brand/logo_main2.png',
                'actions' => [
                    ['label' => 'Get a Quote', 'href' => route('quotes.create'), 'style' => 'primary', 'auth' => true],
                    ['label' => 'Book a Repair', 'href' => route('repairs.create'), 'style' => 'outline-light'],
                ],
            ],
            [
                'label' => 'Shop',
                'heading' => 'Shop Phones, Computers and Accessories',
                'copy' => 'Browse available new and pre-owned technology products and accessories.',
                'image' => asset('images/brand/header_logo.png'),
                'image_path' => 'images/brand/header_logo.png',
                'actions' => [
                    ['label' => 'Visit Shop', 'href' => route('shop.index'), 'style' => 'primary'],
                    ['label' => 'Certified Pre-Owned', 'href' => route('shop.certified-pre-owned-devices.index'), 'style' => 'outline-light'],
                ],
            ],
            [
                'label' => 'Replacement Parts',
                'heading' => 'Find Replacement Parts for Your Device',
                'copy' => 'Search available replacement parts by device, model, part name, SKU or model number.',
                'image' => asset('images/brand/logo.png'),
                'image_path' => 'images/brand/logo.png',
                'actions' => [
                    ['label' => 'Browse Parts', 'href' => route('parts.index'), 'style' => 'primary'],
                    ['label' => 'Search Parts', 'href' => route('parts.index').'#parts-menu-search', 'style' => 'outline-light'],
                ],
            ],
            [
                'label' => 'Repair Tracking',
                'heading' => 'Stay Updated on Your Repair',
                'copy' => 'Use repair tracking to check customer-visible progress on an existing repair.',
                'image' => asset('images/brand/eclise-thumb-grey-m.png'),
                'image_path' => 'images/brand/eclise-thumb-grey-m.png',
                'actions' => [
                    ['label' => 'Track Repair', 'href' => route('repairs.track'), 'style' => 'primary'],
                    ['label' => 'View Services', 'href' => route('services'), 'style' => 'outline-light'],
                ],
            ],
            [
                'label' => 'Support',
                'heading' => 'Need Help Choosing the Right Service?',
                'copy' => 'Contact Eclise for assistance with repairs, replacement parts, products or an existing order.',
                'image' => asset('images/brand/logo_wt.png'),
                'image_path' => 'images/brand/logo_wt.png',
                'actions' => [
                    ['label' => 'Contact Us', 'href' => route('contact.create'), 'style' => 'primary'],
                    ['label' => 'View Services', 'href' => route('services'), 'style' => 'outline-light'],
                ],
            ],
        ];
    @endphp

    <section class="home-slider" aria-label="Eclise service highlights">
        <div
            id="homeHeroCarousel"
            class="carousel slide carousel-fade"
            data-bs-ride="carousel"
            data-bs-interval="6500"
            data-bs-pause="hover"
            data-bs-touch="true"
            data-eclise-home-carousel
        >
            <div class="carousel-indicators">
                @foreach ($homeSlides as $slide)
                    <button type="button" data-bs-target="#homeHeroCarousel" data-bs-slide-to="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" aria-label="Show {{ $slide['label'] }} slide"></button>
                @endforeach
            </div>

            <div class="carousel-inner">
                @foreach ($homeSlides as $slide)
                    <div class="carousel-item {{ $loop->first ? 'active' : '' }}" data-slide-image="{{ $slide['image_path'] }}">
                        <img class="home-slide-bg" src="{{ $slide['image'] }}" alt="" aria-hidden="true">
                        <div class="home-slide-overlay" aria-hidden="true"></div>
                        <div class="container home-slide-container">
                            <div class="home-slide-content">
                                <p class="eyebrow mb-3">{{ $slide['label'] }}</p>
                                <h1 class="fw-black mb-4">{{ $slide['heading'] }}</h1>
                                <p class="fs-5 mb-4">{{ $slide['copy'] }}</p>
                                <div class="home-slide-actions">
                                    @foreach ($slide['actions'] as $action)
                                        <a class="btn btn-{{ $action['style'] }} btn-lg px-4" href="{{ $action['href'] }}" @if (($action['auth'] ?? false) && ! auth()->check()) data-auth-required data-intended-url="{{ $action['href'] }}" @endif>{{ $action['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous home slide</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next home slide</span>
            </button>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <p class="eyebrow">What We Handle</p>
                    <h2 class="display-6 fw-bold mb-0">Repair support and product options for everyday technology.</h2>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a class="btn btn-outline-primary" href="{{ route('services') }}"><i class="bi bi-grid me-2" aria-hidden="true"></i>View Services</a>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'bi-phone', 'title' => 'Phone and Tablet Repair', 'copy' => 'Support for common device issues such as screens, batteries, charging problems, cameras, speakers and other hardware concerns.', 'href' => route('repairs.create'), 'action' => 'Book Repair'],
                    ['icon' => 'bi-laptop', 'title' => 'Computer and Laptop Repair', 'copy' => 'Assessment and repair options for laptop and desktop problems, including hardware diagnostics and component-related concerns.', 'href' => route('services'), 'action' => 'Learn More'],
                    ['icon' => 'bi-search', 'title' => 'Device Diagnostics', 'copy' => 'Issue details help Eclise identify likely causes, suitable next steps and parts that may need to be considered.', 'href' => route('quotes.create'), 'action' => 'Request Quote', 'requiresAuth' => true],
                    ['icon' => 'bi-cpu', 'title' => 'Replacement Parts', 'copy' => 'Browse available replacement parts for pricing visibility and compatibility discussions before purchase or service.', 'href' => route('parts.index'), 'action' => 'Browse Parts'],
                    ['icon' => 'bi-bag-check', 'title' => 'Phones, Computers and Accessories', 'copy' => 'Shop new and pre-owned technology products, certified pre-owned devices and everyday accessories where available.', 'href' => route('shop.index'), 'action' => 'Visit Shop'],
                ] as $service)
                    <div class="col-md-6 col-xl">
                        <x-feature-card :icon="$service['icon']" :title="$service['title']" :copy="$service['copy']" :href="$service['href']" :action="$service['action']" :requires-auth="$service['requiresAuth'] ?? false" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <p class="eyebrow">Repair Process</p>
                    <h2 class="display-6 fw-bold mb-0">A clear path from issue details to repair tracking.</h2>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['title' => 'Request a quote', 'copy' => 'Submit device and issue details so Eclise can review the request and respond with the next step.'],
                    ['title' => 'Book or confirm the repair', 'copy' => 'Use the repair number provided by Eclise to continue with an approved repair when it is ready for customer action.'],
                    ['title' => 'Share the device details', 'copy' => 'Provide the information needed to confirm the device, issue, fulfilment preference and repair notes.'],
                    ['title' => 'Track progress', 'copy' => 'Use repair tracking to review customer-visible status updates and repair notes.'],
                    ['title' => 'Complete the next step', 'copy' => 'Follow the repair instructions provided by Eclise, including payment or pickup steps when available.'],
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

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'bi-tools', 'title' => 'Repairs', 'copy' => 'Start with a quote, continue with a repair number, or check progress on an existing repair.', 'href' => route('repairs.create'), 'action' => 'Book Repair'],
                    ['icon' => 'bi-bag', 'title' => 'Shop', 'copy' => 'Browse available products, including new and pre-owned devices and accessories.', 'href' => route('shop.index'), 'action' => 'Browse Shop'],
                    ['icon' => 'bi-cpu', 'title' => 'Parts', 'copy' => 'Review replacement part availability and pricing information for repair planning.', 'href' => route('parts.index'), 'action' => 'Browse Parts'],
                ] as $area)
                    <div class="col-md-4">
                        <article class="surface business-area-card p-4">
                            <span class="service-icon mb-3" aria-hidden="true"><i class="bi {{ $area['icon'] }}"></i></span>
                            <h2 class="h4 fw-bold">{{ $area['title'] }}</h2>
                            <p class="muted">{{ $area['copy'] }}</p>
                            <a class="btn btn-outline-primary" href="{{ $area['href'] }}">{{ $area['action'] }}</a>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <p class="eyebrow">Service Information</p>
                    <h2 class="display-6 fw-bold">Repair options should be clear before customers commit.</h2>
                    <p class="muted fs-5 mb-0">Eclise keeps the customer workflow focused on practical next steps: quote requests, repair booking, part selection, status updates and payment when required.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach ([
                            'Transparent repair options',
                            'Clear customer communication',
                            'Repair progress tracking',
                            'Access to replacement parts',
                            'New and pre-owned device options',
                            'Self-service order and repair lookup',
                        ] as $item)
                            <div class="col-sm-6">
                                <div class="feature-list-item surface p-3 h-100">
                                    <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                                    <span>{{ $item }}</span>
                                </div>
                            </div>
                        @endforeach
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
                    <h2 class="display-6 fw-bold mb-0">Available products from the shop.</h2>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('shop.index') }}"><i class="bi bi-bag me-2" aria-hidden="true"></i>Open Shop</a>
            </div>
            <div class="row g-4">
                @forelse ($featuredProducts as $product)
                    <div class="col-md-6 col-xl-3">
                        <div class="surface product-card h-100 overflow-hidden">
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                            <div class="p-4">
                                <p class="eyebrow mb-1">{{ $product->category?->name }}</p>
                                <h3 class="h5 fw-bold">{{ $product->name }}</h3>
                                <p class="muted small mb-3">{{ collect([$product->conditionName(), $product->brandName(), $product->modelName()])->filter()->implode(' - ') }}</p>
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <strong>${{ number_format($product->currentPrice(), 2) }}</strong>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('products.show', $product) }}"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View {{ $product->name }}</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="surface p-4">Products will appear after the catalog is available.</div>
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
                    <h2 class="display-6 fw-bold">Review replacement part pricing before service decisions.</h2>
                    <p class="muted fs-5 mb-4">Parts are shown for price verification and compatibility discussions. Availability and fit should be confirmed before purchase or installation.</p>
                    <a class="btn btn-primary" href="{{ route('parts.index') }}"><i class="bi bi-search me-2" aria-hidden="true"></i>Browse Parts</a>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4">
                        @forelse ($featuredParts as $part)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div class="min-width-0">
                                    <strong class="d-block text-truncate" title="{{ $part->name }}">{{ $part->name }}</strong>
                                    <div class="small muted text-truncate">{{ collect([$part->brand, $part->model_compatibility])->filter()->implode(' - ') }}</div>
                                </div>
                                <strong class="text-nowrap">${{ number_format($part->displayPrice(), 2) }}</strong>
                            </div>
                        @empty
                            <p class="mb-0">Parts will appear after the catalog is available.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-cta-band eyebrow="Next Step" title="Need help choosing the right path?" copy="Start with a quote, continue an approved repair, or contact Eclise for help with repairs, parts and products." class="bg-white">
        <a class="btn btn-primary btn-lg" href="{{ route('quotes.create') }}" @guest data-auth-required data-intended-url="{{ route('quotes.create') }}" @endguest>Get a Quote</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('repairs.create') }}">Book a Repair</a>
        <a class="btn btn-outline-primary btn-lg" href="{{ route('contact.create') }}">Contact Eclise</a>
    </x-cta-band>
@endsection
