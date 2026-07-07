@forelse ($parts as $part)
    <article class="parts-menu-part-card">
        <a class="parts-menu-part-image" href="{{ route('parts.show', $part) }}">
            <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}">
        </a>
        <div class="parts-menu-part-body">
            <h3><a href="{{ route('parts.show', $part) }}">{{ $part->name }}</a></h3>
            <p>{{ $part->brandName() ?: 'MobileSentrix' }}{{ $part->modelName() ? ' · '.$part->modelName() : '' }}</p>
            <div class="parts-menu-part-meta">
                <span>SKU: {{ $part->sku ?: $part->new_sku ?: 'N/A' }}</span>
                <span>{{ $part->stockLabel() }}</span>
            </div>
            <div class="parts-menu-part-footer">
                <strong>${{ number_format($part->displayPrice(), 2) }}</strong>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('parts.show', $part) }}">View Details</a>
            </div>
        </div>
    </article>
@empty
    <div class="parts-menu-empty">No parts found for this category.</div>
@endforelse
