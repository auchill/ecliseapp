<strong>{{ number_format($products->total()) }} product{{ $products->total() === 1 ? '' : 's' }} found</strong>
<div class="small muted">Showing {{ number_format($products->firstItem() ?? 0) }}-{{ number_format($products->lastItem() ?? 0) }}</div>
