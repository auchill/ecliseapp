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
                <div class="container">
                    <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}" aria-label="Eclise Technology Inc. home">
                        <img class="brand-logo" src="{{ asset('images/brand/header_logo.png') }}" alt="Eclise Technology Inc.">
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mainNav">
                        <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">
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
                            <li class="nav-item">
                                <a class="nav-link position-relative {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">
                                    <i class="bi bi-bag"></i><span class="visually-hidden">Cart</span>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">{{ $cartItemCount ?? 0 }}</span>
                                </a>
                            </li>
                            @auth
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ auth()->user()->name }}</a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('dashboard') }}">Dashboard</a></li>
                                        <li><a class="dropdown-item" href="{{ route('customer.repairs') }}">My Repairs</a></li>
                                        <li><a class="dropdown-item" href="{{ route('customer.orders') }}">My Orders</a></li>
                                        @if(auth()->user()->isAdmin())
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin</a></li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button class="dropdown-item" type="submit">Logout</button>
                                            </form>
                                        </li>
                                    </ul>
                                </li>
                            @else
                                <li class="nav-item ms-xl-2"><a class="btn btn-outline-primary btn-sm px-3" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a></li>
                                <li class="nav-item"><a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}"><i class="bi bi-person-plus me-1"></i>Register</a></li>
                            @endauth
                        </ul>
                    </div>
                </div>
            </nav>

            @auth
                @if(auth()->user()->isAdmin() && request()->is('admin*'))
                    <nav class="admin-nav py-2">
                        <div class="container">
                            <ul class="nav gap-1">
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Overview</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.repairs.*') ? 'active' : '' }}" href="{{ route('admin.repairs.index') }}"><i class="bi bi-tools me-1"></i>Repairs</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-phone me-1"></i>Products</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.product-brands.*') ? 'active' : '' }}" href="{{ route('admin.product-brands.index') }}"><i class="bi bi-tags me-1"></i>Product Brands</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.product-categories.*') ? 'active' : '' }}" href="{{ route('admin.product-categories.index') }}"><i class="bi bi-grid me-1"></i>Product Categories</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.product-models.*') ? 'active' : '' }}" href="{{ route('admin.product-models.index') }}"><i class="bi bi-phone-vibrate me-1"></i>Product Models</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt me-1"></i>Orders</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.parts.*') ? 'active' : '' }}" href="{{ route('admin.parts.index') }}"><i class="bi bi-cpu me-1"></i>Parts</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.part-brands.*') ? 'active' : '' }}" href="{{ route('admin.part-brands.index') }}"><i class="bi bi-tags me-1"></i>Parts Brands</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.part-categories.*') ? 'active' : '' }}" href="{{ route('admin.part-categories.index') }}"><i class="bi bi-grid me-1"></i>Parts Categories</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.part-models.*') ? 'active' : '' }}" href="{{ route('admin.part-models.index') }}"><i class="bi bi-cpu-fill me-1"></i>Parts Models</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}"><i class="bi bi-chat-square-text me-1"></i>Quotes</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.device-types.*') ? 'active' : '' }}" href="{{ route('admin.device-types.index') }}"><i class="bi bi-hdd-stack me-1"></i>Device Types</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.device-brands.*') ? 'active' : '' }}" href="{{ route('admin.device-brands.index') }}"><i class="bi bi-bookmark me-1"></i>Device Brands</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.device-models.*') ? 'active' : '' }}" href="{{ route('admin.device-models.index') }}"><i class="bi bi-phone-landscape me-1"></i>Device Models</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.issue-categories.*') ? 'active' : '' }}" href="{{ route('admin.issue-categories.index') }}"><i class="bi bi-wrench-adjustable me-1"></i>Issues</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="bi bi-credit-card me-1"></i>Payments</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.shipping-methods.*') ? 'active' : '' }}" href="{{ route('admin.shipping-methods.index') }}"><i class="bi bi-truck me-1"></i>Shipping Methods</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.shipping-discounts.*') ? 'active' : '' }}" href="{{ route('admin.shipping-discounts.index') }}"><i class="bi bi-percent me-1"></i>Shipping Discounts</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}"><i class="bi bi-people me-1"></i>Customers</a></li>
                                <li class="nav-item"><a class="nav-link rounded {{ request()->routeIs('admin.contact-messages.*') ? 'active' : '' }}" href="{{ route('admin.contact-messages.index') }}"><i class="bi bi-envelope me-1"></i>Messages</a></li>
                            </ul>
                        </div>
                    </nav>
                @endif
            @endauth

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
    </body>
</html>
