<div class="row g-4 shop-product-grid">
    @forelse ($products as $product)
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="surface product-card h-100 overflow-hidden">
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                <div class="p-4">
                    <p class="eyebrow mb-1">{{ $product->categoryName() ?? 'Product' }}</p>
                    <h2 class="h5 fw-bold">{{ $product->name }}</h2>
                    <p class="muted small">{{ collect([$product->conditionName(), $product->brandName(), $product->modelName(), $product->sizeNames(), $product->networkName(), $product->quantity.' in stock'])->filter()->implode(' - ') }}</p>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div>
                            @if ($product->sale_price)
                                <span class="text-decoration-line-through muted small">${{ number_format($product->regularDisplayPrice(), 2) }}</span>
                            @endif
                            <strong>${{ number_format($product->currentPrice(), 2) }}</strong>
                        </div>
                        <div class="d-inline-flex gap-2">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('products.show', $product) }}"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></a>
                            @if(! auth()->user()?->isAdmin())
                                <form method="POST" action="{{ route('cart.store', $product) }}">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-bag-plus"></i><span class="visually-hidden">Add to Cart</span></button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="surface p-4">No products match the selected filters.</div>
        </div>
    @endforelse
</div>

<div class="mt-4" data-shop-pagination>
    {{ $products->links() }}
</div>
