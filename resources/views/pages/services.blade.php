@extends('layouts.app')

@section('title', 'Services')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Services</p>
            <h1 class="display-5 fw-bold mb-3">Phone repairs, computer repairs, device sales, accessories, and parts checks.</h1>
            <p class="fs-5 mb-0">A single place for everyday tech support and practical replacement options.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'bi-phone', 'title' => 'Phone Repairs', 'items' => ['Screen replacement', 'Battery replacement', 'Charging port service', 'Camera and speaker repairs']],
                    ['icon' => 'bi-pc-display', 'title' => 'Computer Repairs', 'items' => ['Laptop diagnostics', 'Desktop troubleshooting', 'Keyboard and screen service', 'Storage and performance upgrades']],
                    ['icon' => 'bi-bag-check', 'title' => 'Device Sales', 'items' => ['New phones', 'Used phones', 'New computers', 'Used and refurbished computers']],
                    ['icon' => 'bi-plug', 'title' => 'Accessories', 'items' => ['Chargers and cables', 'Cases and protection', 'Computer accessories', 'Everyday replacement essentials']],
                    ['icon' => 'bi-cpu', 'title' => 'Parts Price Verification', 'items' => ['Screens', 'Batteries', 'Charging ports', 'Laptop parts and boards']],
                    ['icon' => 'bi-clipboard2-check', 'title' => 'Repair Tracking', 'items' => ['Tracking number lookup', 'Status timeline', 'Customer-visible notes', 'Estimated completion dates']],
                ] as $service)
                    <div class="col-md-6 col-xl-4">
                        <div class="surface h-100 p-4">
                            <span class="service-icon mb-3"><i class="bi {{ $service['icon'] }}"></i></span>
                            <h2 class="h4 fw-bold">{{ $service['title'] }}</h2>
                            <ul class="list-unstyled muted mb-0">
                                @foreach ($service['items'] as $item)
                                    <li class="mb-2"><i class="bi bi-check2 text-primary me-2"></i>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="surface p-4 p-lg-5 d-lg-flex align-items-center justify-content-between gap-4">
                <div>
                    <p class="eyebrow">Next Step</p>
                    <h2 class="h1 fw-bold mb-2">Ready to start a repair?</h2>
                    <p class="muted fs-5 mb-lg-0">Submit the device details and preferred appointment window to get a tracking number.</p>
                </div>
                <a class="btn btn-primary btn-lg mt-3 mt-lg-0" href="{{ route('repairs.create') }}"><i class="bi bi-calendar-check me-2"></i>Book a Repair</a>
            </div>
        </div>
    </section>
@endsection
