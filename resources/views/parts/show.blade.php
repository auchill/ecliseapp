@extends('layouts.app')

@section('title', $part->name)

@section('content')
    @php
        $galleryImages = $part->gallery_images->isNotEmpty() ? $part->gallery_images : collect();
        $mainImage = $part->main_image_url;
        $sku = $part->sku ?: $part->new_sku;
        $variation = $part->device_color_text ?: $part->color_text ?: $part->color ?: $part->front_position_text;
        $description = $part->display_description;
        $stockQuantity = max((int) $part->quantity, (int) $part->in_stock_qty);
        $badgeName = $part->display_badge_name;
        $badgeIcon = $part->display_badge_icon_url;
        $warrantyIcon = $part->display_warranty_icon_url;
        $warrantyLabel = $part->display_warranty_label;
        $reviewCount = (int) ($part->total_reviews_count ?? 0);
        $ratingRaw = data_get($part->raw_payload, 'rating_summary') ?? data_get($part->raw_payload, 'rating');
        $ratingValue = is_numeric($ratingRaw) ? (float) $ratingRaw : 0;
        $ratingOutOfFive = $ratingValue > 5 ? round($ratingValue / 20, 1) : round($ratingValue, 1);
    @endphp

    <section class="section-pad-sm bg-white">
        <div class="container ms-product-page">
            <div class="ms-product-titlebar">
                <h1>{{ $part->name }}</h1>

                @if ($warrantyLabel)
                    <div class="ms-warranty-ribbon">
                        @if ($warrantyIcon)
                            <img src="{{ $warrantyIcon }}" alt="{{ $warrantyLabel }}">
                        @endif
                        <span>{{ $warrantyLabel }}</span>
                    </div>
                @endif
            </div>

            <div class="ms-product-sku-row">
                @if ($sku)
                    <span class="ms-sku-label"><i class="bi bi-tag-fill"></i> SKU</span>
                    <span class="ms-sku-value">{{ $sku }}</span>
                    <i class="bi bi-clipboard ms-sku-copy" aria-hidden="true"></i>
                @endif

                @if ($variation)
                    <span class="ms-variation-pill">{{ $variation }}</span>
                @endif
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <div class="ms-service-row">
                        <span><i class="bi bi-truck"></i> Ship</span>
                        <span><i class="bi bi-tags"></i> Price match promise</span>
                        <span><i class="bi bi-arrow-return-left"></i> Easy refunds & returns</span>
                    </div>

                    <div class="ms-gallery-frame">
                        @if ($badgeName)
                            <div class="ms-image-badge">
                                @if ($badgeIcon)
                                    <img src="{{ $badgeIcon }}" alt="{{ $badgeName }}">
                                @else
                                    <span>{{ $badgeName }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="ms-main-image-wrap">
                            <img data-part-main-image src="{{ $mainImage }}" alt="{{ $part->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                        </div>

                        @if ($galleryImages->count() > 1)
                            <div class="ms-gallery-strip" aria-label="Product images">
                                @foreach ($galleryImages as $image)
                                    <button class="ms-gallery-thumb {{ $loop->first ? 'active' : '' }}" type="button" data-part-gallery-image="{{ $image->large_image_url ?: $image->image_url }}" data-part-gallery-alt="{{ $image->alt_text ?: $image->label ?: $part->name }}">
                                        <img src="{{ $image->thumbnail_url ?: $image->image_url }}" alt="{{ $image->alt_text ?: $image->label ?: $part->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($part->compatibility_labels->isNotEmpty())
                        <section class="ms-token-panel mt-3">
                            <h2 class="ms-token-heading ms-token-heading-red"><i class="bi bi-link-45deg"></i> Compatible</h2>
                            <div class="ms-token-body">
                                @foreach ($part->compatibility_labels as $compatibility)
                                    <span>{{ $compatibility }}</span>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($part->tag_labels->isNotEmpty())
                        <section class="ms-token-panel mt-3">
                            <h2 class="ms-token-heading ms-token-heading-blue"><i class="bi bi-tag-fill"></i> Tag</h2>
                            <div class="ms-token-body">
                                @foreach ($part->tag_labels as $tag)
                                    <span>{{ $tag }}</span>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <div class="col-lg-7">
                    <div class="ms-purchase-panel">
                        <div class="ms-price">CA${{ number_format($part->display_price, 2) }}</div>

                        <div class="ms-review-row">
                            @if ($reviewCount > 0 && $ratingOutOfFive > 0)
                                <span class="ms-stars" aria-label="{{ $ratingOutOfFive }} out of 5 rating">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi {{ $i <= round($ratingOutOfFive) ? 'bi-star-fill' : 'bi-star' }}"></i>
                                    @endfor
                                </span>
                                <span>{{ number_format($ratingOutOfFive, 1) }} Out Of 5 Rating</span>
                            @else
                                <span>Be the first to write a review</span>
                            @endif
                        </div>

                        @if ($badgeName || $warrantyLabel)
                            <div class="ms-cred-row">
                                @if ($badgeName)
                                    <span>
                                        @if ($badgeIcon)
                                            <img src="{{ $badgeIcon }}" alt="{{ $badgeName }}">
                                        @endif
                                        {{ $badgeName }}
                                    </span>
                                @endif
                                @if ($warrantyLabel)
                                    <span>
                                        @if ($warrantyIcon)
                                            <img src="{{ $warrantyIcon }}" alt="{{ $warrantyLabel }}">
                                        @endif
                                        {{ $warrantyLabel }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        <form class="ms-cart-form" method="GET" action="{{ route('contact.create') }}">
                            <input type="hidden" name="part" value="{{ $sku ?: $part->id }}">
                            <div class="ms-quantity-control" data-quantity-control>
                                <button type="button" data-quantity-minus aria-label="Decrease quantity">-</button>
                                <input name="quantity" type="number" value="1" min="1" max="{{ max(1, $stockQuantity) }}" aria-label="Quantity">
                                <button type="button" data-quantity-plus aria-label="Increase quantity">+</button>
                            </div>
                            <button class="ms-add-cart" type="submit"><i class="bi bi-cart-fill"></i> Add To Cart</button>
                        </form>

                        <p class="ms-stock-line">{{ $stockQuantity > 0 ? number_format($stockQuantity).' available' : $part->stockLabel() }}</p>
                    </div>

                    <section class="ms-description-panel">
                        <h2>Product Description</h2>
                        <div class="ms-description-body">
                            @if ($description)
                                {!! $description !!}
                            @else
                                <p>Detailed description is not available for this part yet.</p>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            @if ($part->related_product_parts->isNotEmpty())
                <section class="ms-related-section">
                    <h2><i class="bi bi-list-ul"></i> Related Products</h2>
                    <div class="ms-related-grid">
                        @foreach ($part->related_product_parts->take(4) as $related)
                            @php
                                $relatedBadgeName = $related->display_badge_name;
                                $relatedBadgeIcon = $related->display_badge_icon_url;
                                $relatedVariation = $related->device_color_text ?: $related->color_text ?: $related->color ?: $related->front_position_text;
                                $relatedStock = max((int) $related->quantity, (int) $related->in_stock_qty);
                            @endphp
                            <article class="ms-related-card">
                                @if ($relatedBadgeName)
                                    <div class="ms-related-badge">
                                        @if ($relatedBadgeIcon)
                                            <img src="{{ $relatedBadgeIcon }}" alt="{{ $relatedBadgeName }}">
                                        @else
                                            <span>{{ $relatedBadgeName }}</span>
                                        @endif
                                    </div>
                                @endif
                                <a class="ms-related-image" href="{{ route('parts.show', $related) }}">
                                    <img src="{{ $related->main_image_url }}" alt="{{ $related->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                </a>
                                @if ($relatedVariation)
                                    <div class="ms-related-variation">{{ $relatedVariation }}</div>
                                @endif
                                <h3><a href="{{ route('parts.show', $related) }}">{{ $related->name }}</a></h3>
                                <strong>CA${{ number_format($related->display_price, 2) }}</strong>
                                <form class="ms-related-cart" method="GET" action="{{ route('contact.create') }}">
                                    <input type="hidden" name="part" value="{{ $related->sku ?: $related->id }}">
                                    <div class="ms-quantity-control ms-quantity-control-sm" data-quantity-control>
                                        <button type="button" data-quantity-minus aria-label="Decrease quantity">-</button>
                                        <input name="quantity" type="number" value="0" min="0" max="{{ max(1, $relatedStock) }}" aria-label="Quantity">
                                        <button type="button" data-quantity-plus aria-label="Increase quantity">+</button>
                                    </div>
                                    <button class="ms-related-add" type="submit">Add to Cart</button>
                                </form>
                            </article>
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

        document.querySelectorAll('[data-quantity-control]').forEach((control) => {
            const input = control.querySelector('input');
            const minus = control.querySelector('[data-quantity-minus]');
            const plus = control.querySelector('[data-quantity-plus]');

            minus?.addEventListener('click', () => {
                const min = Number(input.min || 0);
                input.value = Math.max(min, Number(input.value || 0) - 1);
            });

            plus?.addEventListener('click', () => {
                const max = Number(input.max || 9999);
                input.value = Math.min(max, Number(input.value || 0) + 1);
            });
        });
    </script>
@endpush
