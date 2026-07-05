@if ($selectedFilterGroups)
    <div class="cpo-active-filters" data-cpo-active-filters>
        @foreach ($selectedFilterGroups as $group)
            <div class="cpo-active-filter-group" data-cpo-active-filter-group="{{ $group['field'] }}">
                <span class="cpo-active-filter-title">{{ $group['label'] }} :</span>
                <span class="cpo-active-filter-values">
                    @foreach ($group['values'] as $selected)
                        <span class="cpo-active-filter-pill" data-cpo-active-filter>
                            <span class="cpo-active-filter-value">{{ $selected['value'] }}</span>
                            <button
                                class="cpo-active-filter-remove"
                                type="button"
                                data-cpo-remove-filter
                                data-filter-type="{{ $selected['type'] }}"
                                data-filter-field="{{ $group['field'] }}"
                                data-filter-value="{{ $selected['value'] }}"
                                aria-label="Remove {{ $group['label'] }} {{ $selected['value'] }} filter"
                            >&times;</button>
                        </span>
                    @endforeach
                </span>
            </div>
        @endforeach
    </div>
@endif
