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
                        <label class="form-label" for="item_type">Inventory Type</label>
                        <select class="form-select" id="item_type" name="item_type" data-markup-item-type required>
                            @foreach (\App\Models\EcliseMarkup::ITEM_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('item_type', $markup->item_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="scope_type">Markup Scope</label>
                        <select class="form-select" id="scope_type" name="scope_type" data-markup-scope required>
                            @foreach (\App\Models\EcliseMarkup::SCOPE_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('scope_type', $markup->scope_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12" data-markup-category-wrap>
                        <label class="form-label" for="category_id">MobileSentrix Category</label>
                        <select class="form-select" id="category_id" name="category_id" data-markup-category data-selected-category="{{ old('category_id', $markup->category_id) }}">
                            <option value="">Choose category</option>
                        </select>
                        <div class="form-text">This list is built from MobileSentrix categories only, not Eclise Shop product categories.</div>
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
            const optionsByType = @json($categoryOptions);
            const itemType = document.querySelector('[data-markup-item-type]');
            const scope = document.querySelector('[data-markup-scope]');
            const categoryWrap = document.querySelector('[data-markup-category-wrap]');
            const category = document.querySelector('[data-markup-category]');
            const markupType = document.querySelector('[data-markup-type]');
            const markupValue = document.querySelector('[data-markup-value]');
            const previewPrice = document.querySelector('[data-markup-preview-price]');

            if (!itemType || !scope || !category || !categoryWrap || !markupType || !markupValue || !previewPrice) return;

            const selectedCategory = category.dataset.selectedCategory || '';

            const syncCategories = () => {
                const options = optionsByType[itemType.value] || [];
                category.innerHTML = '<option value="">Choose category</option>';
                options.forEach((option) => {
                    const element = document.createElement('option');
                    element.value = option.id;
                    element.textContent = option.label;
                    if (String(option.id) === String(selectedCategory)) element.selected = true;
                    category.appendChild(element);
                });
            };

            const syncScope = () => {
                const categoryMode = scope.value === 'category';
                categoryWrap.classList.toggle('d-none', !categoryMode);
                category.required = categoryMode;
                if (!categoryMode) category.value = '';
            };

            const syncPreview = () => {
                const source = 100;
                const value = Number(markupValue.value || 0);
                const total = markupType.value === 'percentage'
                    ? source + (source * (value / 100))
                    : source + value;
                previewPrice.textContent = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(total);
            };

            itemType.addEventListener('change', syncCategories);
            scope.addEventListener('change', syncScope);
            markupType.addEventListener('change', syncPreview);
            markupValue.addEventListener('input', syncPreview);

            syncCategories();
            syncScope();
            syncPreview();
        })();
    </script>
@endpush
