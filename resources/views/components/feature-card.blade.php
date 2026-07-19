@props([
    'icon' => 'bi-check-circle',
    'title',
    'copy' => null,
    'href' => null,
    'action' => null,
    'requiresAuth' => false,
])

<article {{ $attributes->class(['surface feature-card h-100 p-4']) }}>
    <span class="service-icon mb-3" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <h3 class="h5 fw-bold">{{ $title }}</h3>
    @if ($copy)
        <p class="muted mb-0">{{ $copy }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="muted mt-3">{{ $slot }}</div>
    @endif
    @if ($href && $action)
        <div class="mt-4">
            <a
                class="btn btn-outline-primary btn-sm"
                href="{{ $href }}"
                @if ($requiresAuth && ! auth()->check()) data-auth-required data-intended-url="{{ $href }}" @endif
            >{{ $action }}</a>
        </div>
    @endif
</article>
