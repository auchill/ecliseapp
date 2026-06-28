<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name')) | {{ config('app.name') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="{{ asset('css/eclise.css') }}" rel="stylesheet">
    </head>
    <body>
        <div class="site-shell">
            <nav class="navbar navbar-expand-xl bg-white sticky-top">
                @php
                    $publicNavUser = auth()->user();
                    $publicNavIsAdmin = $publicNavUser?->isAdmin() === true;
                    $publicNavIsCustomer = $publicNavUser?->isCustomer() === true;
                @endphp
                <div class="container">
                    <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}" aria-label="Eclise Technology Inc. home">
                        <img class="brand-logo" src="{{ asset('images/brand/header_logo.png') }}" alt="Eclise Technology Inc.">
                    </a>
                    <div class="mobile-header-actions d-flex d-xl-none align-items-center ms-auto">
                        @unless($publicNavIsAdmin)
                            <x-cart-icon :count="$cartItemCount ?? 0" variant="compact" />

                            @if($publicNavIsCustomer)
                                <x-customer-avatar-dropdown :user="$publicNavUser" menu-id="mobileCustomerAccountDropdown" />
                            @elseif(! $publicNavUser)
                                <a class="btn btn-outline-primary btn-sm mobile-auth-btn" href="{{ route('login') }}">
                                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login
                                </a>
                                <a class="btn btn-primary btn-sm mobile-auth-btn" href="{{ route('register') }}">
                                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Register
                                </a>
                            @endif
                        @endunless
                    </div>
                    <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Open navigation menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mainNav">
                        <ul class="navbar-nav ms-xl-auto align-items-xl-center gap-xl-1">
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('services') ? 'active' : '' }}" href="{{ route('services') }}">Services</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('repairs.*') || request()->routeIs('quotes.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Repair</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('quotes.create') }}">Get a Quote</a></li>
                                    <li><a class="dropdown-item" href="{{ route('repairs.create') }}">Book Repair</a></li>
                                    <li><a class="dropdown-item" href="{{ route('repairs.track') }}">Track Repair</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('shop.*') || request()->routeIs('products.*') || request()->routeIs('orders.track*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Shop</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('shop.index') }}">Shop Online</a></li>
                                    <li><a class="dropdown-item" href="{{ route('orders.track') }}">Track Order</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('parts.*') ? 'active' : '' }}" href="{{ route('parts.index') }}">Parts</a></li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact.*') ? 'active' : '' }}" href="{{ route('contact.create') }}">Contact</a></li>
                        </ul>
                        @unless($publicNavIsAdmin)
                            <div class="public-nav-actions d-none d-xl-flex align-items-center gap-2 ms-xl-3">
                                <x-cart-icon :count="$cartItemCount ?? 0" variant="nav" class="{{ request()->routeIs('cart.*') ? 'active' : '' }}" />

                                @if($publicNavIsCustomer)
                                    <x-customer-avatar-dropdown :user="$publicNavUser" menu-id="desktopCustomerAccountDropdown" />
                                @elseif(! $publicNavUser)
                                    <a class="btn btn-outline-primary btn-sm px-3" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login</a>
                                    <a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Register</a>
                                @endif
                            </div>
                        @endunless
                    </div>
                </div>
            </nav>

            <main class="flex-grow-1">
                @include('partials.flash')
                @yield('content')
            </main>

            <footer class="bg-dark text-white py-5 mt-auto">
                <div class="container">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-5">
                            <img class="footer-logo mb-3" src="{{ asset('images/brand/logo_wt.png') }}" alt="Eclise Technology Inc.">
                            <p class="text-white-50 mb-0">Repair. Reuse. Reconnect.</p>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <h2 class="h6">Services</h2>
                            <ul class="list-unstyled text-white-50 mb-0">
                                <li>Phone and computer repairs</li>
                                <li>Used and new devices</li>
                                <li>Accessories and parts checks</li>
                            </ul>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h2 class="h6">Quick Links</h2>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-light btn-sm" href="{{ route('quotes.create') }}">Get a Quote</a>
                                <a class="btn btn-outline-light btn-sm" href="{{ route('repairs.create') }}">Book Repair</a>
                                <a class="btn btn-outline-light btn-sm" href="{{ route('repairs.track') }}">Track Repair</a>
                                <a class="btn btn-outline-light btn-sm" href="{{ route('orders.track') }}">Track Order</a>
                                <a class="btn btn-outline-light btn-sm" href="{{ route('shop.index') }}">Shop</a>
                                <a class="btn btn-outline-light btn-sm" href="{{ route('parts.index') }}">Parts</a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        @stack('scripts')
    </body>
</html>
