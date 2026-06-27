@props([
    'count' => 0,
    'variant' => 'nav',
])

@php
    $count = (int) ($count ?? 0);
    $classes = 'cart-action '.($variant === 'compact' ? 'cart-action-compact' : 'cart-action-nav');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} href="{{ route('cart.index') }}" aria-label="Cart">
    <i class="bi bi-bag" aria-hidden="true"></i>
    <span class="visually-hidden">Cart</span>
    <span class="cart-action-badge badge rounded-pill bg-primary">{{ $count }}</span>
</a>
