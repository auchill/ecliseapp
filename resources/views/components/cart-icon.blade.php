@props([
    'count' => 0,
    'variant' => 'nav',
])

@php
    $count = (int) ($count ?? 0);
    $classes = 'cart-action '.($variant === 'compact' ? 'cart-action-compact' : 'cart-action-nav');
    $linkAttributes = [
        'class' => $classes,
        'href' => route('cart.index'),
        'aria-label' => 'Cart',
    ];

    if (auth()->guest()) {
        $linkAttributes['data-auth-required'] = true;
        $linkAttributes['data-intended-url'] = route('cart.index');
    }
@endphp

<a {{ $attributes->merge($linkAttributes) }}>
    <i class="bi bi-bag" aria-hidden="true"></i>
    <span class="visually-hidden">Cart</span>
    <span class="cart-action-badge badge rounded-pill bg-primary">{{ $count }}</span>
</a>
