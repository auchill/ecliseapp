@extends('layouts.app')

@section('title', 'Shop')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Shop</p>
            <h1 class="display-5 fw-bold mb-3">Phones, computers, and accessories.</h1>
            <p class="fs-5 mb-0">Browse used, new, and refurbished devices.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 mb-4" method="GET" action="{{ route('shop.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Phone, laptop, SKU">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="brand">Brand</label>
                        <select class="form-select" id="brand" name="brand">
                            <option value="">All</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->slug }}" @selected(request('brand') === $brand->slug)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="condition">Condition</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="">All</option>
                            @foreach ($conditions as $condition)
                                <option value="{{ $condition->id }}" @selected((int) request('condition') === $condition->id)>{{ $condition->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="model">Model</label>
                        <select class="form-select" id="model" name="model">
                            <option value="">All</option>
                            @foreach ($models as $model)
                                <option value="{{ $model->id }}" @selected((int) request('model') === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="size">Size</label>
                        <select class="form-select" id="size" name="size">
                            <option value="">All</option>
                            @foreach ($sizes as $size)
                                <option value="{{ $size->id }}" @selected((int) request('size') === $size->id)>{{ $size->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="grade">Grade</label>
                        <select class="form-select" id="grade" name="grade">
                            <option value="">All</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected((int) request('grade') === $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="color">Color</label>
                        <select class="form-select" id="color" name="color">
                            <option value="">All</option>
                            @foreach ($colors as $color)
                                <option value="{{ $color->id }}" @selected((int) request('color') === $color->id)>{{ $color->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="network">Network</label>
                        <select class="form-select" id="network" name="network">
                            <option value="">All</option>
                            @foreach ($networks as $network)
                                <option value="{{ $network->id }}" @selected((int) request('network', request('carrier')) === $network->id)>{{ $network->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="min_price">Min</label>
                        <input class="form-control" id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ request('min_price') }}">
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="max_price">Max</label>
                        <input class="form-control" id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ request('max_price') }}">
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button>
                    </div>
                </div>
            </form>

            <div class="row g-4">
                @forelse ($products as $product)
                    <div class="col-md-6 col-xl-3">
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

            <div class="mt-4">
                {{ $products->links() }}
            </div>
        </div>
    </section>
@endsection
