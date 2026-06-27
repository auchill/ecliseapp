@props([
    'user',
    'menuId' => 'customerAccountDropdown',
])

@php
    $name = trim((string) ($user?->name ?? 'Customer'));
    $nameParts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';

    foreach (array_slice($nameParts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    $initials = $initials ?: 'U';
    $avatarPath = null;

    foreach (['profile_image', 'profile_photo_path', 'avatar', 'avatar_path', 'image_path'] as $candidate) {
        $value = $user?->getAttribute($candidate);

        if (filled($value)) {
            $avatarPath = $value;
            break;
        }
    }

    $avatarUrl = null;

    if ($avatarPath) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://', '/'])
            ? $avatarPath
            : asset(\Illuminate\Support\Str::startsWith($avatarPath, 'storage/') ? $avatarPath : 'storage/'.$avatarPath);
    }
@endphp

@if($user?->isCustomer())
    <div {{ $attributes->merge(['class' => 'dropdown customer-account-dropdown']) }}>
        <button class="customer-avatar-button dropdown-toggle" id="{{ $menuId }}" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open customer account menu">
            @if($avatarUrl)
                <img class="customer-avatar" src="{{ $avatarUrl }}" alt="{{ $name }} profile">
            @else
                <span class="customer-avatar customer-avatar-initials" aria-hidden="true">{{ $initials }}</span>
                <span class="visually-hidden">{{ $name }} profile</span>
            @endif
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="{{ $menuId }}">
            <li><span class="dropdown-item-text customer-dropdown-name">{{ $name }}</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard</a></li>
            <li><a class="dropdown-item" href="{{ route('customer.repairs') }}"><i class="bi bi-tools me-2" aria-hidden="true"></i>My Repairs</a></li>
            <li><a class="dropdown-item" href="{{ route('customer.orders') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>My Orders</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</button>
                </form>
            </li>
        </ul>
    </div>
@endif
