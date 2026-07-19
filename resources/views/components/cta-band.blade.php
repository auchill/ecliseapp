@props([
    'eyebrow' => null,
    'title',
    'copy' => null,
])

<section {{ $attributes->class(['section-pad']) }}>
    <div class="container">
        <div class="surface cta-band p-4 p-lg-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg">
                    @if ($eyebrow)
                        <p class="eyebrow mb-2">{{ $eyebrow }}</p>
                    @endif
                    <h2 class="h1 fw-bold mb-2">{{ $title }}</h2>
                    @if ($copy)
                        <p class="muted fs-5 mb-0">{{ $copy }}</p>
                    @endif
                </div>
                <div class="col-lg-auto">
                    <div class="d-flex flex-wrap gap-2">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
