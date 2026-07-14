<fieldset class="shop-filter-group">
    <legend>Category</legend>
    <input type="hidden" name="category" value="{{ request('category') }}" data-shop-category-input>
    <button
        class="shop-category-option {{ request('category') ? '' : 'active' }}"
        type="button"
        data-shop-category=""
        aria-pressed="{{ request('category') ? 'false' : 'true' }}"
    >
        <span>All Products</span>
    </button>
    @foreach ($filterOptions['categories'] ?? [] as $option)
        <button
            class="shop-category-option {{ $option['selected'] ? 'active' : '' }}"
            type="button"
            data-shop-category="{{ $option['value'] }}"
            aria-pressed="{{ $option['selected'] ? 'true' : 'false' }}"
        >
            <span>{{ $option['label'] }}</span>
            <small>{{ $option['count'] }}</small>
        </button>
    @endforeach
</fieldset>

@foreach ([
    'brands' => 'Brand',
    'conditions' => 'Condition',
    'grades' => 'Grade',
    'colors' => 'Color',
] as $groupKey => $label)
    @if (! empty($filterOptions[$groupKey]))
        <fieldset class="shop-filter-group">
            <legend>{{ $label }}</legend>
            @foreach ($filterOptions[$groupKey] as $option)
                <label class="shop-filter-option" for="{{ $idPrefix }}_{{ $groupKey }}_{{ \Illuminate\Support\Str::slug($option['value']) }}">
                    <span>
                        <input
                            id="{{ $idPrefix }}_{{ $groupKey }}_{{ \Illuminate\Support\Str::slug($option['value']) }}"
                            class="form-check-input"
                            name="{{ $groupKey }}[]"
                            type="checkbox"
                            value="{{ $option['value'] }}"
                            @checked($option['selected'])
                        >
                        {{ $option['label'] }}
                    </span>
                    <small>{{ $option['count'] }}</small>
                </label>
            @endforeach
        </fieldset>
    @endif
@endforeach
