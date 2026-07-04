<div class="row g-4">
    @forelse ($parts as $part)
        <div class="col-md-6 col-xl-3">
            <div class="surface part-card h-100 overflow-hidden">
                <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}">
                <div class="p-4">
                    <h2 class="h5 fw-bold">{{ $part->name }}</h2>
                    <p class="muted small mb-2">{{ $part->brandName() }} &middot; {{ $part->modelName() }}</p>
                    <div class="small muted mb-3">
                        <div>SKU: {{ $part->sku ?: $part->new_sku ?: 'N/A' }}</div>
                        <div>{{ $part->stockLabel() }}</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
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
