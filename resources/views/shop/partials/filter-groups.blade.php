@php
    $selectedValues = function (string $key, ?string $legacyKey = null): array {
        $value = request()->query($key);

        if (blank($value) && $legacyKey !== null) {
            $value = request()->query($legacyKey);
        }

        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }

        return collect(is_array($value) ? $value : [])
            ->flatten()
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    };

    $collapseId = fn (string $group): string => "product-filter-{$idPrefix}-{$group}";
    $categoryExpanded = true;
    $multiGroups = [
        'brands' => ['label' => 'Brand', 'slug' => 'brand', 'legacy' => 'brand', 'expanded' => false],
        'conditions' => ['label' => 'Condition', 'slug' => 'condition', 'legacy' => 'condition', 'expanded' => true],
        'grades' => ['label' => 'Grade', 'slug' => 'grade', 'legacy' => 'grade', 'expanded' => false],
        'colors' => ['label' => 'Color', 'slug' => 'color', 'legacy' => 'color', 'expanded' => false],
    ];
@endphp

<section class="shop-filter-group product-filter-group" data-filter-group="category">
    <h3 class="product-filter-group__heading">
        <button
            class="product-filter-group__toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $collapseId('category') }}"
            aria-expanded="{{ $categoryExpanded ? 'true' : 'false' }}"
            aria-controls="{{ $collapseId('category') }}"
        >
            <span>Category</span>
        </button>
    </h3>
    <div
        id="{{ $collapseId('category') }}"
        class="collapse {{ $categoryExpanded ? 'show' : '' }} product-filter-group__content"
    >
        <div data-filter-options>
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
        </div>
    </div>
</section>

@foreach ($multiGroups as $groupKey => $group)
    @php
        $options = $filterOptions[$groupKey] ?? [];
        $isExpanded = $group['expanded'] || $selectedValues($groupKey, $group['legacy']) !== [];
    @endphp
    <section class="shop-filter-group product-filter-group" data-filter-group="{{ $group['slug'] }}" @if (empty($options)) hidden @endif>
        <h3 class="product-filter-group__heading">
            <button
                class="product-filter-group__toggle {{ $isExpanded ? '' : 'collapsed' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId($group['slug']) }}"
                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                aria-controls="{{ $collapseId($group['slug']) }}"
            >
                <span>{{ $group['label'] }}</span>
            </button>
        </h3>
        <div
            id="{{ $collapseId($group['slug']) }}"
            class="collapse {{ $isExpanded ? 'show' : '' }} product-filter-group__content"
        >
            <div data-filter-options>
                @foreach ($options as $option)
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
            </div>
        </div>
    </section>
@endforeach
