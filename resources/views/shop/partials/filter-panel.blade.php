<div class="shop-filter-panel">
    <div class="mb-3">
        <label class="form-label" for="{{ $idPrefix }}_q">Search</label>
        <input
            class="form-control"
            id="{{ $idPrefix }}_q"
            name="q"
            value="{{ request('q') }}"
            placeholder="Phone, laptop, SKU"
            autocomplete="off"
            data-shop-search
        >
    </div>

    <div data-shop-filter-groups>
        @include('shop.partials.filter-groups')
    </div>

    @php
        $priceExpanded = filled(request('min_price')) || filled(request('max_price'));
        $priceCollapseId = "product-filter-{$idPrefix}-price";
    @endphp

    <section class="shop-filter-group product-filter-group" data-filter-group="price">
        <h3 class="product-filter-group__heading">
            <button
                class="product-filter-group__toggle {{ $priceExpanded ? '' : 'collapsed' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $priceCollapseId }}"
                aria-expanded="{{ $priceExpanded ? 'true' : 'false' }}"
                aria-controls="{{ $priceCollapseId }}"
            >
                <span>Price</span>
            </button>
        </h3>
        <div
            id="{{ $priceCollapseId }}"
            class="collapse {{ $priceExpanded ? 'show' : '' }} product-filter-group__content"
        >
            <div class="row g-2" data-filter-options>
                <div class="col-6">
                    <label class="form-label small" for="{{ $idPrefix }}_min_price">Min</label>
                    <input class="form-control" id="{{ $idPrefix }}_min_price" name="min_price" type="number" min="0" step="0.01" placeholder="{{ $priceBounds['min'] > 0 ? number_format($priceBounds['min'], 0) : '0' }}" value="{{ request('min_price') }}">
                </div>
                <div class="col-6">
                    <label class="form-label small" for="{{ $idPrefix }}_max_price">Max</label>
                    <input class="form-control" id="{{ $idPrefix }}_max_price" name="max_price" type="number" min="0" step="0.01" placeholder="{{ $priceBounds['max'] > 0 ? number_format($priceBounds['max'], 0) : '' }}" value="{{ request('max_price') }}">
                </div>
            </div>
        </div>
    </section>

    <div class="d-grid mt-3">
        <button class="btn btn-outline-secondary" type="button" data-shop-clear>Clear All</button>
    </div>
</div>
