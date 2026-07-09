@forelse ($parts as $part)
    @php
        $fallbackImage = \App\Support\CatalogImage::fallbackUrl();
        $partImage = $part->imageUrl();
    @endphp
    <article class="parts-menu-part-card">
        <a class="parts-card-image-wrap parts-menu-part-image" href="{{ route('parts.show', $part) }}">
            <img class="parts-card-image" src="{{ $partImage }}" alt="{{ $part->name }}" onerror="this.onerror=null;this.src='{{ $fallbackImage }}';">
        </a>
        <div class="parts-card-body parts-menu-part-body">
            <h3><a href="{{ route('parts.show', $part) }}">{{ $part->name }}</a></h3>
            <p>{{ $part->brandName() ?: 'MobileSentrix' }}{{ $part->modelName() ? ' · '.$part->modelName() : '' }}</p>
            <div class="parts-menu-part-meta">
                <span>SKU: {{ $part->sku ?: $part->new_sku ?: 'N/A' }}</span>
                <span>{{ $part->stockLabel() }}</span>
            </div>
            <div class="parts-card-actions parts-menu-part-footer">
                <strong>${{ number_format($part->displayPrice(), 2) }}</strong>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('parts.show', $part) }}">View Details</a>
            </div>
        </div>
    </article>
@empty
    <div class="parts-menu-empty">No parts found for this category.</div>
@endforelse
