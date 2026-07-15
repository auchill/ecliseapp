@extends('layouts.admin')

@section('title', $markup->exists ? 'Edit Price Markup' : 'Create Price Markup')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">MobileSentrix</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $markup->exists ? 'Edit Price Markup' : 'Create Price Markup' }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.mobilesentrix-markups.index') }}"><i class="bi bi-arrow-left me-2"></i>Back</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $markup->exists ? route('admin.mobilesentrix-markups.update', $markup) : route('admin.mobilesentrix-markups.store') }}" data-markup-form>
                @csrf
                @if ($markup->exists)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="item_type">Source</label>
                        <select class="form-select" id="item_type" name="item_type" data-markup-item-type required>
                            @foreach (\App\Models\EcliseMarkup::ITEM_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('item_type', $markup->item_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="scope_type">Applies To</label>
                        <select class="form-select" id="scope_type" name="scope_type" data-markup-scope required>
                            @foreach (\App\Models\EcliseMarkup::SCOPE_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('scope_type', $markup->scope_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12" data-markup-brand-wrap>
                        <label class="form-label" for="brand_text">MobileSentrix Manufacturer</label>
                        <input class="form-control" id="brand_text" name="brand_text" list="markup_brand_options" value="{{ old('brand_text', $markup->brand_text) }}" data-markup-brand data-selected-brand="{{ old('brand_text', $markup->brand_text) }}">
                        <datalist id="markup_brand_options" data-markup-brand-list></datalist>
                        <div class="form-text">Uses the MobileSentrix manufacturer_text field. Matching is case-insensitive and trim-insensitive.</div>
                    </div>
                    <div class="col-12" data-markup-range-wrap>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="min_price">Minimum Source Price</label>
                                <input class="form-control" id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ old('min_price', $markup->min_price) }}" data-markup-min-price>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="max_price">Maximum Source Price</label>
                                <input class="form-control" id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ old('max_price', $markup->max_price) }}" data-markup-max-price>
                            </div>
                        </div>
                        <div class="form-text" data-markup-bounds>Available source price range will be shown after choosing a source.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="markup_type">Markup Type</label>
                        <select class="form-select" id="markup_type" name="markup_type" data-markup-type required>
                            @foreach (\App\Models\EcliseMarkup::MARKUP_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('markup_type', $markup->markup_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="markup_value">Markup Value</label>
                        <input class="form-control" id="markup_value" name="markup_value" type="number" min="0" step="0.01" value="{{ old('markup_value', $markup->markup_value ?? 0) }}" data-markup-value required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="priority">Priority</label>
                        <input class="form-control" id="priority" name="priority" type="number" min="0" value="{{ old('priority', $markup->priority ?? 0) }}" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $markup->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info mb-0" data-markup-preview>
                            Sample source price: $100.00<br>
                            Calculated customer price: <strong data-markup-preview-price>$100.00</strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save Rule</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const brandOptionsByType = @json($brandOptions);
            const priceBoundsByType = @json($priceBounds);
            const itemType = document.querySelector('[data-markup-item-type]');
            const scope = document.querySelector('[data-markup-scope]');
            const brandWrap = document.querySelector('[data-markup-brand-wrap]');
            const brand = document.querySelector('[data-markup-brand]');
            const brandList = document.querySelector('[data-markup-brand-list]');
            const rangeWrap = document.querySelector('[data-markup-range-wrap]');
            const minPrice = document.querySelector('[data-markup-min-price]');
            const maxPrice = document.querySelector('[data-markup-max-price]');
            const bounds = document.querySelector('[data-markup-bounds]');
            const markupType = document.querySelector('[data-markup-type]');
            const markupValue = document.querySelector('[data-markup-value]');
            const previewPrice = document.querySelector('[data-markup-preview-price]');

            if (!itemType || !scope || !brand || !brandWrap || !brandList || !rangeWrap || !minPrice || !maxPrice || !markupType || !markupValue || !previewPrice) return;

            const selectedBrand = brand.dataset.selectedBrand || '';

            const syncBrands = () => {
                const options = brandOptionsByType[itemType.value] || [];
                brandList.innerHTML = '';
                options.forEach((option) => {
                    const element = document.createElement('option');
                    element.value = option.value;
                    element.label = option.label;
                    brandList.appendChild(element);
                });
                if (!brand.value && selectedBrand) brand.value = selectedBrand;
            };

            const syncBounds = () => {
                const range = priceBoundsByType[itemType.value] || {};
                if (range.min !== null && range.min !== undefined && range.max !== null && range.max !== undefined) {
                    minPrice.min = range.min;
                    maxPrice.min = range.min;
                    maxPrice.max = range.max;
                    bounds.textContent = `Current ${itemType.options[itemType.selectedIndex].text} source price range: $${Number(range.min).toFixed(2)} - $${Number(range.max).toFixed(2)}.`;
                } else {
                    minPrice.removeAttribute('min');
                    maxPrice.removeAttribute('max');
                    bounds.textContent = 'No source price range is available yet for this source.';
                }
            };

            const syncScope = () => {
                const brandMode = scope.value === 'brand';
                const rangeMode = scope.value === 'price_range';
                brandWrap.classList.toggle('d-none', !brandMode);
                rangeWrap.classList.toggle('d-none', !rangeMode);
                brand.required = brandMode;
                minPrice.required = rangeMode;
                maxPrice.required = rangeMode;
                if (!brandMode) brand.value = '';
                if (!rangeMode) {
                    minPrice.value = '';
                    maxPrice.value = '';
                }
            };

            const syncPreview = () => {
                const source = 100;
                const value = Number(markupValue.value || 0);
                const total = markupType.value === 'percentage'
                    ? source + (source * (value / 100))
                    : source + value;
                previewPrice.textContent = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(total);
            };

            itemType.addEventListener('change', () => {
                syncBrands();
                syncBounds();
            });
            scope.addEventListener('change', syncScope);
            markupType.addEventListener('change', syncPreview);
            markupValue.addEventListener('input', syncPreview);

            syncBrands();
            syncBounds();
            syncScope();
            syncPreview();
        })();
    </script>
@endpush
