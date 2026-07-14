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

    <fieldset class="shop-filter-group">
        <legend>Price</legend>
        <div class="row g-2">
            <div class="col-6">
                <label class="form-label small" for="{{ $idPrefix }}_min_price">Min</label>
                <input class="form-control" id="{{ $idPrefix }}_min_price" name="min_price" type="number" min="0" step="0.01" placeholder="{{ $priceBounds['min'] > 0 ? number_format($priceBounds['min'], 0) : '0' }}" value="{{ request('min_price') }}">
            </div>
            <div class="col-6">
                <label class="form-label small" for="{{ $idPrefix }}_max_price">Max</label>
                <input class="form-control" id="{{ $idPrefix }}_max_price" name="max_price" type="number" min="0" step="0.01" placeholder="{{ $priceBounds['max'] > 0 ? number_format($priceBounds['max'], 0) : '' }}" value="{{ request('max_price') }}">
            </div>
        </div>
    </fieldset>

    <div class="d-grid mt-3">
        <button class="btn btn-outline-secondary" type="button" data-shop-clear>Clear All</button>
    </div>
</div>
