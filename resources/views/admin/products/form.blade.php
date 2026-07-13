@extends('layouts.admin')

@section('title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
    @php
        $selectedSizeIds = collect(old('product_size_ids', $product->exists ? $product->sizes->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

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
                        <input class="form-control" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="Auto-generated if blank">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="product_category_id">Product Category</label>
                        <select class="form-select" id="product_category_id" name="product_category_id" required>
                            <option value="">Choose category</option>
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
                                <option value="{{ $model->id }}" data-product-brand-id="{{ $model->product_brand_id }}" @selected((int) old('product_model_id', $product->product_model_id) === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="product_size_ids">Product Sizes</label>
                        <select class="form-select" id="product_size_ids" name="product_size_ids[]" multiple>
                            @foreach ($productSizes as $size)
                                <option value="{{ $size->id }}" @selected(in_array($size->id, $selectedSizeIds, true))>{{ $size->name }}</option>
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
                        <select class="form-select" id="product_condition_id" name="product_condition_id">
                            <option value="">No product condition</option>
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
                        <label class="form-label" for="product_network_id">Product Network</label>
                        <select class="form-select" id="product_network_id" name="product_network_id">
                            <option value="">No product network</option>
                            @foreach ($productNetworks as $network)
                                <option value="{{ $network->id }}" @selected((int) old('product_network_id', $product->product_network_id) === $network->id)>{{ $network->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="serial_number">Serial Number</label>
                        <input class="form-control" id="serial_number" name="serial_number" value="{{ old('serial_number', $product->serial_number) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="quantity">Quantity</label>
                        <input class="form-control" id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', $product->quantity ?? 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="low_stock_threshold">Low Stock Threshold</label>
                        <input class="form-control" id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="cost_price">Cost Price</label>
                        <input class="form-control" id="cost_price" name="cost_price" type="number" min="0" step="0.01" value="{{ old('cost_price', $product->cost_price) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="regular_price">Regular Price</label>
                        <input class="form-control" id="regular_price" name="regular_price" type="number" min="0" step="0.01" value="{{ old('regular_price', $product->regular_price) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sale_price">Sale Price</label>
                        <input class="form-control" id="sale_price" name="sale_price" type="number" min="0" step="0.01" value="{{ old('sale_price', $product->sale_price) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="source">Source</label>
                        <input class="form-control" id="source" name="source" value="{{ old('source', $product->source ?: 'manual') }}">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $product->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" id="is_featured" name="is_featured" type="checkbox" value="1" @checked(old('is_featured', $product->is_featured ?? false))>
                            <label class="form-check-label" for="is_featured">Featured</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="short_description">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="2">{{ old('short_description', $product->short_description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="product_images">Product Images</label>
                        <input class="form-control" id="product_images" name="product_images[]" type="file" accept="image/*" multiple>
                    </div>

                    @if ($product->exists && $product->images->isNotEmpty())
                        <div class="col-12">
                            <div class="row g-3">
                                @foreach ($product->images as $image)
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="border rounded p-2 h-100">
                                            <img class="img-fluid rounded mb-2" src="{{ $image->displayUrl() }}" alt="{{ $image->alt_text ?: $product->name }}" style="height: 130px; width: 100%; object-fit: contain;">
                                            <div class="form-check">
                                                <input class="form-check-input" id="primary_image_{{ $image->id }}" name="primary_image_id" type="radio" value="{{ $image->id }}" @checked($image->is_primary)>
                                                <label class="form-check-label" for="primary_image_{{ $image->id }}">Primary</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" id="delete_image_{{ $image->id }}" name="delete_image_ids[]" type="checkbox" value="{{ $image->id }}">
                                                <label class="form-check-label" for="delete_image_{{ $image->id }}">Delete</label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $product->exists ? 'Save Product' : 'Create Product' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const brandSelect = document.getElementById('product_brand_id');
            const modelSelect = document.getElementById('product_model_id');

            if (!brandSelect || !modelSelect) {
                return;
            }

            const syncModels = () => {
                const brandId = brandSelect.value;

                Array.from(modelSelect.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }

                    const optionBrandId = option.dataset.productBrandId || '';
                    option.hidden = Boolean(brandId && optionBrandId && optionBrandId !== brandId);
                });

                const selected = modelSelect.selectedOptions[0];
                if (selected && selected.hidden) {
                    modelSelect.value = '';
                }
            };

            brandSelect.addEventListener('change', syncModels);
            syncModels();
        })();
    </script>
@endsection
