@if ($selectedChips)
    <div class="shop-filter-chips mb-3" data-shop-active-filter-list>
        @foreach ($selectedChips as $chip)
            <button
                class="shop-filter-chip"
                type="button"
                data-shop-chip-url="{{ $chip['url'] }}"
                aria-label="{{ $chip['aria'] }}"
            >
                {{ $chip['label'] }} <span aria-hidden="true">&times;</span>
            </button>
        @endforeach
        <button class="shop-filter-chip shop-filter-chip-clear" type="button" data-shop-clear>Clear All</button>
    </div>
@endif
