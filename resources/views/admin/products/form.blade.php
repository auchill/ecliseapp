@extends('layouts.admin')

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
                        <label class="form-label" for="product_category_id">Product Category</label>
                        <select class="form-select" id="product_category_id" name="product_category_id">
                            <option value="">Uncategorized</option>
                            @foreach ($productCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('product_category_id', $product->product_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_brand_id">Product Brand</label>
                        <select class="form-select" id="product_brand_id" name="product_brand_id">
                            <option value="">No product brand</option>
                            @foreach ($productBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) old('product_brand_id', $product->product_brand_id) === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_model_id">Product Model</label>
                        <select class="form-select" id="product_model_id" name="product_model_id">
                            <option value="">No product model</option>
                            @foreach ($productModels as $model)
                                <option value="{{ $model->id }}" @selected((int) old('product_model_id', $product->product_model_id) === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_size_id">Product Size</label>
                        <select class="form-select" id="product_size_id" name="product_size_id">
                            <option value="">No product size</option>
                            @foreach ($productSizes as $size)
                                <option value="{{ $size->id }}" @selected((int) old('product_size_id', $product->product_size_id) === $size->id)>{{ $size->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_grade_id">Product Grade</label>
                        <select class="form-select" id="product_grade_id" name="product_grade_id">
                            <option value="">No product grade</option>
                            @foreach ($productGrades as $grade)
                                <option value="{{ $grade->id }}" @selected((int) old('product_grade_id', $product->product_grade_id) === $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_condition_id">Product Condition</label>
                        <select class="form-select" id="product_condition_id" name="product_condition_id" required>
                            <option value="">Choose condition</option>
                            @foreach ($productConditions as $condition)
                                <option value="{{ $condition->id }}" @selected((int) old('product_condition_id', $product->product_condition_id) === $condition->id)>{{ $condition->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_color_id">Product Color</label>
                        <select class="form-select" id="product_color_id" name="product_color_id">
                            <option value="">No product color</option>
                            @foreach ($productColors as $color)
                                <option value="{{ $color->id }}" @selected((int) old('product_color_id', $product->product_color_id) === $color->id)>{{ $color->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="product_carrier_id">Product Carrier</label>
                        <select class="form-select" id="product_carrier_id" name="product_carrier_id">
                            <option value="">No product carrier</option>
                            @foreach ($productCarriers as $carrier)
                                <option value="{{ $carrier->id }}" @selected((int) old('product_carrier_id', $product->product_carrier_id) === $carrier->id)>{{ $carrier->name }}</option>
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
