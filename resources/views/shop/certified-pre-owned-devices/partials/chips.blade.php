@if ($selectedChips)
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($selectedChips as $chip)
            <span class="cpo-chip">{{ $chip['label'] }}: {{ $chip['value'] }}</span>
        @endforeach
    </div>
@endif
