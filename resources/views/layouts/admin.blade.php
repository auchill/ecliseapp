<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Admin') | {{ config('app.name') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="{{ asset('css/eclise.css') }}" rel="stylesheet">
        <style>
            body { background: #f5f7fb; }
            .admin-shell { min-height: 100vh; display: flex; }
            .admin-sidebar { width: 280px; background: #071d3a; color: #d7e5f7; flex-shrink: 0; }
            .admin-sidebar-inner { min-height: 100vh; padding: 1.25rem 1rem; display: flex; flex-direction: column; gap: 1.25rem; }
            .admin-brand { display: flex; align-items: center; min-height: 48px; padding: .25rem .5rem 1rem; border-bottom: 1px solid rgba(255,255,255,.12); }
            .admin-brand img { max-width: 190px; width: 100%; height: auto; }
            .admin-menu { display: flex; flex-direction: column; gap: .6rem; overflow-y: auto; }
            .admin-menu-heading { width: 100%; border: 0; background: transparent; color: #89a8cb; display: flex; align-items: center; justify-content: space-between; padding: .45rem .65rem; font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; }
            .admin-menu-heading i { font-size: .75rem; }
            .admin-menu-link { display: flex; align-items: center; gap: .65rem; color: #d7e5f7; text-decoration: none; padding: .62rem .75rem; border-radius: 8px; font-weight: 600; font-size: .94rem; }
            .admin-menu-link i { color: #1d8fff; width: 1.1rem; text-align: center; }
            .admin-menu-link:hover, .admin-menu-link.active { background: rgba(29,143,255,.16); color: #fff; }
            .admin-main { flex: 1; min-width: 0; }
            .admin-topbar { min-height: 68px; background: #fff; border-bottom: 1px solid #e3e8f0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1.25rem; position: sticky; top: 0; z-index: 1020; }
            .admin-content { padding: 1.25rem; }
            .admin-page-title { font-size: 1.25rem; font-weight: 800; margin: 0; color: #071d3a; }
            .admin-profile-button { border: 1px solid #dbe4f0; background: #fff; border-radius: 999px; padding: .35rem .5rem .35rem .35rem; display: flex; align-items: center; gap: .5rem; }
            .admin-avatar { width: 34px; height: 34px; border-radius: 50%; background: #071d3a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
            .offcanvas.admin-mobile-nav { background: #071d3a; color: #d7e5f7; }
            @media (min-width: 992px) {
                .admin-sidebar { position: sticky; top: 0; height: 100vh; }
                .admin-content { padding: 1.5rem; }
            }
        </style>
    </head>
    <body>
        <div class="admin-shell">
            <aside class="admin-sidebar d-none d-lg-block">
                @include('partials.admin-sidebar', ['sidebarId' => 'desktopAdminNav'])
            </aside>

            <div class="admin-main">
                <header class="admin-topbar">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileNav" aria-controls="adminMobileNav">
                            <i class="bi bi-list"></i><span class="visually-hidden">Open admin menu</span>
                        </button>
                        <h1 class="admin-page-title">@yield('title', 'Admin')</h1>
                    </div>

                    <div class="dropdown">
                        <button class="admin-profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="admin-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('admin.users.edit', auth()->id()) }}"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </header>

                <main class="admin-content">
                    @include('partials.flash')
                    @yield('content')
                </main>
            </div>
        </div>

        <div class="offcanvas offcanvas-start admin-mobile-nav" tabindex="-1" id="adminMobileNav" aria-labelledby="adminMobileNavLabel">
            <div class="offcanvas-header">
                <h2 class="h6 mb-0" id="adminMobileNavLabel">Admin Menu</h2>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-0">
                @include('partials.admin-sidebar', ['sidebarId' => 'mobileAdminNav'])
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
