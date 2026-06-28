@extends('layouts.app')

@section('title', $part->name)

@section('content')
    @php
        $galleryImages = $part->images->isNotEmpty() ? $part->images : collect();
        $mainImage = $part->mainImageUrl();
        $sku = $part->sku ?: $part->new_sku;
        $description = $part->displayDescription();
        $stockQuantity = max((int) $part->quantity, (int) $part->in_stock_qty);
        $detailRows = collect([
            'SKU' => $part->sku,
            'New SKU' => $part->new_sku,
            'Brand' => $part->brandName(),
            'Manufacturer' => $part->manufacturer_text ?: $part->manufacturer,
            'Model' => $part->modelName(),
            'Category' => $part->categoryName(),
            'Color' => $part->device_color_text ?: $part->color_text ?: $part->color,
            'Carrier' => $part->device_carrier_text,
            'Grade' => $part->device_grade_text,
            'Size' => $part->device_size_text,
            'Warranty' => $part->warranty_period_text ?: $part->warranty_period,
            'Order Status' => $part->product_order_status_text ?: $part->product_order_status,
            'Stock' => $stockQuantity > 0 ? number_format($stockQuantity).' available' : $part->stockLabel(),
            'Weight' => $part->weight ? $part->weight.' lb' : null,
            'Dimensions' => ($part->length && $part->width && $part->height) ? "{$part->length} x {$part->width} x {$part->height}" : null,
        ])->filter();
    @endphp

    <section class="part-detail-hero">
        <div class="container">
            <nav class="small mb-3" aria-label="breadcrumb">
                <a class="text-white text-decoration-none" href="{{ route('parts.index') }}">Parts</a>
                @if ($part->categoryName())
                    <span class="text-white-50 mx-2">/</span>
                    <span class="text-white-50">{{ $part->categoryName() }}</span>
                @endif
            </nav>
            <p class="eyebrow mb-2">Parts Price Check</p>
            <h1 class="display-6 fw-bold mb-3">{{ $part->name }}</h1>
            <div class="d-flex flex-wrap gap-2">
                @if ($sku)
                    <span class="part-hero-pill">SKU {{ $sku }}</span>
                @endif
                @if ($part->brandName())
                    <span class="part-hero-pill">{{ $part->brandName() }}</span>
                @endif
                @if ($part->modelName())
                    <span class="part-hero-pill">{{ $part->modelName() }}</span>
                @endif
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="part-gallery surface p-3">
                        <div class="part-gallery-main">
                            <img data-part-main-image src="{{ $mainImage }}" alt="{{ $part->name }}">
                        </div>

                        @if ($galleryImages->count() > 1)
                            <div class="part-gallery-thumbs" aria-label="Product images">
                                @foreach ($galleryImages as $image)
                                    <button class="part-gallery-thumb {{ $loop->first ? 'active' : '' }}" type="button" data-part-gallery-image="{{ $image->image_url }}" data-part-gallery-alt="{{ $image->label ?: $part->name }}">
                                        <img src="{{ $image->image_url }}" alt="{{ $image->label ?: $part->name }}">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-5">
                    <aside class="surface p-4 part-purchase-panel">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <p class="eyebrow mb-1">{{ $part->categoryName() ?: 'MobileSentrix Part' }}</p>
                                <strong class="display-6 d-block">${{ number_format($part->displayPrice(), 2) }}</strong>
                            </div>
                            <span class="status-pill {{ $part->isAvailableForPartsPurchase() ? 'status-pill-success' : 'status-pill-muted' }}">
                                {{ $part->stockLabel() }}
                            </span>
                        </div>

                        @if ($part->badges->isNotEmpty() || $part->premium || $part->end_of_life)
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                @foreach ($part->badges as $badge)
                                    <span class="part-badge">{{ $badge->name }}</span>
                                @endforeach
                                @if ($part->premium)
                                    <span class="part-badge">Premium</span>
                                @endif
                                @if ($part->end_of_life)
                                    <span class="part-badge part-badge-muted">End of Life</span>
                                @endif
                            </div>
                        @endif

                        <dl class="part-summary-list mb-4">
                            @foreach ($detailRows->take(8) as $label => $value)
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if(! auth()->user()?->isAdmin())
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <div>
                                    <label class="form-label" for="part_quantity">Quantity</label>
                                    <input class="form-control" id="part_quantity" type="number" value="1" min="1" max="{{ max(1, $stockQuantity) }}" style="width: 112px;">
                                </div>
                                <a class="btn btn-primary btn-lg" href="{{ route('contact.create', ['part' => $sku ?: $part->id]) }}">
                                    <i class="bi bi-chat-dots me-2"></i>Ask About This Part
                                </a>
                            </div>
                        @endif

                        @if ($part->last_enriched_at)
                            <p class="small muted mt-3 mb-0">Updated {{ $part->last_enriched_at->diffForHumans() }}</p>
                        @endif
                    </aside>
                </div>
            </div>

            <div class="row g-4 mt-4">
                <div class="col-lg-7">
                    <section class="surface p-4 h-100">
                        <h2 class="h4 fw-bold mb-3">Description</h2>
                        @if ($description)
                            <div class="part-description">
                                {!! $description !!}
                            </div>
                        @else
                            <p class="muted mb-0">Detailed description is not available for this part yet.</p>
                        @endif
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="surface p-4 h-100">
                        <h2 class="h4 fw-bold mb-3">Product Details</h2>
                        <dl class="part-details-table mb-0">
                            @foreach ($detailRows as $label => $value)
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                </div>
            </div>

            @if ($part->tags->isNotEmpty() || $part->compatibilities->isNotEmpty())
                <div class="row g-4 mt-4">
                    @if ($part->tags->isNotEmpty())
                        <div class="col-lg-5">
                            <section class="surface p-4 h-100">
                                <h2 class="h4 fw-bold mb-3">Tags</h2>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($part->tags as $tag)
                                        <span class="part-tag">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    @endif

                    @if ($part->compatibilities->isNotEmpty())
                        <div class="{{ $part->tags->isNotEmpty() ? 'col-lg-7' : 'col-12' }}">
                            <section class="surface p-4 h-100">
                                <h2 class="h4 fw-bold mb-3">Compatibility</h2>
                                <div class="part-compatibility-grid">
                                    @foreach ($part->compatibilities as $compatibility)
                                        <span>{{ $compatibility->name }}</span>
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    @endif
                </div>
            @endif

            @if ($part->relatedParts->isNotEmpty())
                <section class="mt-5">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                        <h2 class="h3 fw-bold mb-0">Related Parts</h2>
                        <a class="btn btn-outline-primary" href="{{ route('parts.index') }}">Browse Parts</a>
                    </div>
                    <div class="row g-4">
                        @foreach ($part->relatedParts->take(4) as $related)
                            <div class="col-md-6 col-xl-3">
                                <div class="surface part-card h-100 overflow-hidden">
                                    <img src="{{ $related->mainImageUrl() }}" alt="{{ $related->name }}">
                                    <div class="p-4">
                                        <p class="eyebrow mb-1">{{ $related->categoryName() ?: 'Part' }}</p>
                                        <h3 class="h6 fw-bold">{{ $related->name }}</h3>
                                        <p class="small muted mb-3">{{ $related->sku ?: $related->new_sku }}</p>
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <strong>${{ number_format($related->displayPrice(), 2) }}</strong>
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('parts.show', $related) }}">
                                                <i class="bi bi-eye"></i><span class="visually-hidden">View</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-part-gallery-image]').forEach((button) => {
            button.addEventListener('click', () => {
                const mainImage = document.querySelector('[data-part-main-image]');

                if (!mainImage) return;

                mainImage.src = button.dataset.partGalleryImage;
                mainImage.alt = button.dataset.partGalleryAlt || mainImage.alt;
                document.querySelectorAll('[data-part-gallery-image]').forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
            });
        });
    </script>
@endpush
