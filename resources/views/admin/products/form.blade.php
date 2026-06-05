@extends('layouts.app')

@section('title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $product->exists ? 'Edit Product' : 'Add Product' }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.products.index') }}"><i class="bi bi-arrow-left me-2"></i>Products</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data">
                @csrf
                @if ($product->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sku">SKU</label>
                        <input class="form-control" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Uncategorized</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="brand">Brand</label>
                        <input class="form-control" id="brand" name="brand" value="{{ old('brand', $product->brand) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="model">Model</label>
                        <input class="form-control" id="model" name="model" value="{{ old('model', $product->model) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="condition">Condition</label>
                        <select class="form-select" id="condition" name="condition" required>
                            @foreach ($conditions as $condition)
                                <option value="{{ $condition }}" @selected(old('condition', $product->condition ?: 'New') === $condition)>{{ $condition }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $product->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="quantity">Quantity</label>
                        <input class="form-control" id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', $product->quantity ?? 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="price">Price</label>
                        <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $product->price) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sale_price">Sale price</label>
                        <input class="form-control" id="sale_price" name="sale_price" type="number" min="0" step="0.01" value="{{ old('sale_price', $product->sale_price) }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="product_image">Product image</label>
                        <input class="form-control" id="product_image" name="product_image" type="file" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $product->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $product->exists ? 'Save Product' : 'Create Product' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
