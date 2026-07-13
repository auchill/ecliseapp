@extends('layouts.app')

@section('title', $product->name)

@section('content')
    @php
        $mainImageUrl = $product->imageUrl();
        $galleryImages = $product->images
            ->filter(fn ($image) => $image->displayUrl() !== $mainImageUrl)
            ->values();
    @endphp

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="surface product-card overflow-hidden">
                        <img id="product-main-image" src="{{ $mainImageUrl }}" alt="{{ $product->name }}" style="height: 420px;" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                    </div>
                    @if ($galleryImages->isNotEmpty())
                        <div class="d-flex gap-2 mt-3 overflow-auto">
                            @foreach ($galleryImages as $image)
                                <button class="btn border rounded p-1 product-thumb-button" type="button" data-image-url="{{ $image->displayUrl() }}">
                                    <img src="{{ $image->displayUrl() }}" alt="{{ $image->alt_text ?: $product->name }}" style="width: 76px; height: 76px; object-fit: contain;" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-lg-6">
                    <p class="eyebrow">{{ $product->categoryName() }}</p>
                    <h1 class="display-5 fw-bold">{{ $product->name }}</h1>
                    <p class="muted fs-5">{{ $product->description }}</p>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="status-pill">{{ $product->conditionName() }}</span>
                        <span class="status-pill">{{ $product->brandName() }}</span>
                        <span class="status-pill">{{ $product->modelName() }}</span>
                        @foreach($product->sizes as $size)
                            <span class="status-pill">{{ $size->name }}</span>
                        @endforeach
                        @if($product->productGrade)
                            <span class="status-pill">{{ $product->productGrade->name }}</span>
                        @endif
                        @if($product->productColor)
                            <span class="status-pill">{{ $product->productColor->name }}</span>
                        @endif
                        @if($product->network)
                            <span class="status-pill">{{ $product->network->name }}</span>
                        @endif
                        <span class="status-pill">{{ $product->quantity }} in stock</span>
                    </div>
                    <div class="mb-4">
                        @if ($product->sale_price)
                            <span class="text-decoration-line-through muted fs-5">${{ number_format($product->regularDisplayPrice(), 2) }}</span>
                        @endif
                        <strong class="display-6 d-block">${{ number_format($product->currentPrice(), 2) }}</strong>
                    </div>
                    @if(! auth()->user()?->isAdmin())
                        <form class="d-flex gap-2 align-items-end" method="POST" action="{{ route('cart.store', $product) }}">
                            @csrf
                            <div>
                                <label class="form-label" for="quantity">Quantity</label>
                                <input class="form-control" id="quantity" name="quantity" type="number" value="1" min="1" max="{{ $product->quantity }}" style="width: 110px;">
                            </div>
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-bag-plus me-2"></i>Add to Cart</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($relatedProducts->isNotEmpty())
                <div class="mt-5">
                    <h2 class="h3 fw-bold mb-4">Related Products</h2>
                    <div class="row g-4">
                        @foreach ($relatedProducts as $related)
                            <div class="col-md-4">
                                <div class="surface product-card h-100 overflow-hidden">
                                    <img src="{{ $related->imageUrl() }}" alt="{{ $related->name }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                    <div class="p-4">
                                        <h3 class="h5 fw-bold">{{ $related->name }}</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>${{ number_format($related->currentPrice(), 2) }}</strong>
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('products.show', $related) }}"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <script>
        (() => {
            const mainImage = document.getElementById('product-main-image');
            const buttons = document.querySelectorAll('.product-thumb-button');

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (!mainImage || !button.dataset.imageUrl) {
                        return;
                    }

                    mainImage.src = button.dataset.imageUrl;
                    buttons.forEach((item) => item.classList.remove('border-primary'));
                    button.classList.add('border-primary');
                });
            });
        })();
    </script>
@endsection
