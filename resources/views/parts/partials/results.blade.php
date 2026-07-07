<div class="row g-3 parts-card-grid">
    @forelse ($parts as $part)
        @php
            $fallbackImage = asset('images/brand/logo_main.png');
            $partImage = ($part->local_image_path ?: $part->image_path)
                ? asset('storage/'.($part->local_image_path ?: $part->image_path))
                : ($part->default_image ?: $part->image_url ?: $fallbackImage);
        @endphp
        <div class="col-12 col-sm-6 col-lg-4 parts-card-column">
            <div class="surface part-card h-100 overflow-hidden">
                <a class="parts-card-image-wrap" href="{{ route('parts.show', $part) }}">
                    <img class="parts-card-image" src="{{ $partImage }}" alt="{{ $part->name }}" onerror="this.onerror=null;this.src='{{ $fallbackImage }}';">
                </a>
                <div class="parts-card-body p-4">
                    <h2 class="h5 fw-bold">{{ $part->name }}</h2>
                    <p class="muted small mb-2">{{ $part->brandName() }} &middot; {{ $part->modelName() }}</p>
                    <div class="small muted mb-3">
                        <div>SKU: {{ $part->sku ?: $part->new_sku ?: 'N/A' }}</div>
                        <div>{{ $part->stockLabel() }}</div>
                    </div>
                    <div class="parts-card-actions d-flex justify-content-between align-items-center gap-2">
                        <strong>${{ number_format($part->displayPrice(), 2) }}</strong>
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('parts.show', $part) }}">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="surface p-4">No parts found.</div>
        </div>
    @endforelse
</div>

<div class="mt-4" data-parts-pagination>
    {{ $parts->links() }}
</div>
