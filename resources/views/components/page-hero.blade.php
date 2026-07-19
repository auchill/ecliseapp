@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'image' => null,
    'imageAlt' => '',
])

<section {{ $attributes->class(['page-header page-hero-section']) }}>
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="{{ $image ? 'col-lg-7' : 'col-lg-9' }}">
                @if ($eyebrow)
                    <p class="eyebrow mb-2">{{ $eyebrow }}</p>
                @endif
                <h1 class="display-5 fw-bold mb-3">{{ $title }}</h1>
                @if ($description)
                    <p class="fs-5 mb-0">{{ $description }}</p>
                @endif
                @isset($actions)
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        {{ $actions }}
                    </div>
                @endisset
            </div>
            @if ($image)
                <div class="col-lg-5 text-lg-end">
                    <img class="page-hero-image" src="{{ $image }}" alt="{{ $imageAlt }}" loading="eager">
                </div>
            @endif
        </div>
    </div>
</section>
