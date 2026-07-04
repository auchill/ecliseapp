@php
    $adminNavGroups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'active' => 'admin.dashboard'],
        ],
        'MobileSentrix API' => [
            ['label' => 'API Dashboard', 'route' => 'admin.parts.mobilesentrix.index', 'icon' => 'bi-cloud-arrow-down', 'active' => 'admin.parts.mobilesentrix.*'],
        ],
        'MobileSentrix Devices' => [
            ['label' => 'Pre-Owned Devices', 'route' => 'admin.devices.index', 'icon' => 'bi-phone', 'active' => 'admin.devices.*'],
            ['label' => 'Device Manufacturers', 'route' => 'admin.device-manufacturers.index', 'icon' => 'bi-building', 'active' => 'admin.device-manufacturers.*'],
            ['label' => 'Device Models', 'route' => 'admin.device-models.index', 'icon' => 'bi-phone-landscape', 'active' => 'admin.device-models.*'],
            ['label' => 'Device Colors', 'route' => 'admin.device-colors.index', 'icon' => 'bi-palette', 'active' => 'admin.device-colors.*'],
            ['label' => 'Device Conditions', 'route' => 'admin.device-conditions.index', 'icon' => 'bi-check2-square', 'active' => 'admin.device-conditions.*'],
            ['label' => 'Device Carriers', 'route' => 'admin.device-carriers.index', 'icon' => 'bi-broadcast-pin', 'active' => 'admin.device-carriers.*'],
            ['label' => 'Device Sizes', 'route' => 'admin.device-sizes.index', 'icon' => 'bi-aspect-ratio', 'active' => 'admin.device-sizes.*'],
            ['label' => 'Device Grades', 'route' => 'admin.device-grades.index', 'icon' => 'bi-stars', 'active' => 'admin.device-grades.*'],
        ],
        'Repairs' => [
            ['label' => 'Device Types', 'route' => 'admin.device-types.index', 'icon' => 'bi-hdd-stack', 'active' => 'admin.device-types.*'],
            ['label' => 'Device Brands', 'route' => 'admin.device-brands.index', 'icon' => 'bi-bookmark', 'active' => 'admin.device-brands.*'],
            ['label' => 'Device Models', 'route' => 'admin.device-models.index', 'icon' => 'bi-phone-landscape', 'active' => 'admin.device-models.*'],
            ['label' => 'Issues', 'route' => 'admin.issue-categories.index', 'icon' => 'bi-wrench-adjustable', 'active' => 'admin.issue-categories.*'],
            ['label' => 'Quotes', 'route' => 'admin.quotes.index', 'icon' => 'bi-chat-square-text', 'active' => 'admin.quotes.*'],
            ['label' => 'Payments', 'route' => 'admin.repair-payments.index', 'icon' => 'bi-cash-stack', 'active' => 'admin.repair-payments.*'],
        ],
        'Shop' => [
            ['label' => 'Product Brands', 'route' => 'admin.product-brands.index', 'icon' => 'bi-tags', 'active' => 'admin.product-brands.*'],
            ['label' => 'Product Categories', 'route' => 'admin.product-categories.index', 'icon' => 'bi-grid', 'active' => 'admin.product-categories.*'],
            ['label' => 'Product Models', 'route' => 'admin.product-models.index', 'icon' => 'bi-phone-vibrate', 'active' => 'admin.product-models.*'],
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'bi-receipt', 'active' => 'admin.orders.*'],
            ['label' => 'Payments', 'route' => 'admin.shop-payments.index', 'icon' => 'bi-credit-card', 'active' => 'admin.shop-payments.*'],
        ],
        'Parts' => [
            ['label' => 'Parts', 'route' => 'admin.parts.index', 'icon' => 'bi-box-seam', 'active' => 'admin.parts.index'],
            ['label' => 'Parts Brands', 'route' => 'admin.part-brands.index', 'icon' => 'bi-tags', 'active' => 'admin.part-brands.*'],
            ['label' => 'Parts Categories', 'route' => 'admin.part-categories.index', 'icon' => 'bi-grid', 'active' => 'admin.part-categories.*'],
            ['label' => 'Parts Models', 'route' => 'admin.part-models.index', 'icon' => 'bi-cpu-fill', 'active' => 'admin.part-models.*'],
        ],
        'Others' => [
            ['label' => 'Shipping Methods', 'route' => 'admin.shipping-methods.index', 'icon' => 'bi-truck', 'active' => 'admin.shipping-methods.*'],
            ['label' => 'Shipping Discounts', 'route' => 'admin.shipping-discounts.index', 'icon' => 'bi-percent', 'active' => 'admin.shipping-discounts.*'],
            ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'bi-person-gear', 'active' => 'admin.users.*'],
            ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'icon' => 'bi-shield-lock', 'active' => 'admin.permissions.*'],
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'bi-people', 'active' => 'admin.customers.*'],
            ['label' => 'Messages', 'route' => 'admin.contact-messages.index', 'icon' => 'bi-envelope', 'active' => 'admin.contact-messages.*'],
        ],
    ];
@endphp

<div class="admin-sidebar-inner">
    <a class="admin-brand" href="{{ route('admin.dashboard') }}" aria-label="Eclise admin dashboard">
        <img src="{{ asset('images/brand/header_logo.png') }}" alt="Eclise Technology Inc.">
    </a>

    <nav class="admin-menu" aria-label="Admin navigation">
        @foreach ($adminNavGroups as $group => $items)
            @php
                $groupKey = ($sidebarId ?? 'admin').'-'.\Illuminate\Support\Str::slug($group);
                $groupActive = collect($items)->contains(fn ($item) => request()->routeIs($item['active']));
            @endphp
            <div class="admin-menu-group">
                <button class="admin-menu-heading" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupKey }}" aria-expanded="{{ $groupActive || $loop->first ? 'true' : 'false' }}" aria-controls="{{ $groupKey }}">
                    <span>{{ $group }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse {{ $groupActive || $loop->first ? 'show' : '' }}" id="{{ $groupKey }}">
                    @foreach ($items as $item)
                        <a class="admin-menu-link {{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</div>
