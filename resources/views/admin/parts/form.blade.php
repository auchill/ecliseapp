@extends('layouts.admin')

@section('title', $part->exists ? 'Edit Part' : 'Add Part')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $part->exists ? 'Edit Part' : 'Add Part' }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.parts.index') }}"><i class="bi bi-arrow-left me-2"></i>Parts</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $part->exists ? route('admin.parts.update', $part) : route('admin.parts.store') }}" enctype="multipart/form-data">
                @csrf
                @if ($part->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $part->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="device_type">Device type</label>
                        <input class="form-control" id="device_type" name="device_type" value="{{ old('device_type', $part->device_type) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="part_brand_id">Parts Brand</label>
                        <select class="form-select" id="part_brand_id" name="part_brand_id" required>
                            <option value="">Choose brand</option>
                            @foreach ($partBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) old('part_brand_id', $part->part_brand_id) === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="part_category_id">Parts Category</label>
                        <select class="form-select" id="part_category_id" name="part_category_id" required>
                            <option value="">Choose category</option>
                            @foreach ($partCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('part_category_id', $part->part_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="part_model_id">Parts Model</label>
                        <select class="form-select" id="part_model_id" name="part_model_id">
                            <option value="">Choose model if listed</option>
                            @foreach ($partModels as $model)
                                <option value="{{ $model->id }}" @selected((int) old('part_model_id', $part->part_model_id) === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="model_compatibility">Custom compatibility</label>
                        <input class="form-control" id="model_compatibility" name="model_compatibility" value="{{ old('model_compatibility', $part->model_compatibility) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sku">API SKU</label>
                        <input class="form-control" id="sku" name="sku" value="{{ old('sku', $part->sku) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="internal_sku">Internal SKU</label>
                        <input class="form-control" id="internal_sku" name="internal_sku" value="{{ old('internal_sku', $part->internal_sku) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="condition">Condition</label>
                        <input class="form-control" id="condition" name="condition" value="{{ old('condition', $part->condition) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="price">Legacy price</label>
                        <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $part->price) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="selling_price">Selling price</label>
                        <input class="form-control" id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', $part->selling_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="api_price">API price</label>
                        <input class="form-control" id="api_price" name="api_price" type="number" min="0" step="0.01" value="{{ old('api_price', $part->api_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="final_price">Final price</label>
                        <input class="form-control" id="final_price" name="final_price" type="number" min="0" step="0.01" value="{{ old('final_price', $part->final_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="quantity">Local quantity</label>
                        <input class="form-control" id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', $part->quantity ?? 0) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="api_quantity">API quantity</label>
                        <input class="form-control" id="api_quantity" name="api_quantity" type="number" min="0" value="{{ old('api_quantity', $part->api_quantity) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="stock_status">Legacy stock status</label>
                        <input class="form-control" id="stock_status" name="stock_status" value="{{ old('stock_status', $part->stock_status ?: 'Check availability') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="availability_status">API availability</label>
                        <input class="form-control" id="availability_status" name="availability_status" value="{{ old('availability_status', $part->availability_status) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="supplier">Legacy supplier</label>
                        <input class="form-control" id="supplier" name="supplier" value="{{ old('supplier', $part->supplier ?: 'MobileSentrix') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="external_api_source">API source</label>
                        <input class="form-control" id="external_api_source" name="external_api_source" value="{{ old('external_api_source', $part->external_api_source) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="external_api_id">External API ID</label>
                        <input class="form-control" id="external_api_id" name="external_api_id" value="{{ old('external_api_id', $part->external_api_id) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="image_url">Remote image URL</label>
                        <input class="form-control" id="image_url" name="image_url" type="url" value="{{ old('image_url', $part->image_url) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="part_image">Local part image</label>
                        <input class="form-control" id="part_image" name="part_image" type="file" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="compatibility">Compatibility JSON</label>
                        <textarea class="form-control" id="compatibility" name="compatibility" rows="4">{{ old('compatibility', $part->compatibility ? json_encode($part->compatibility, JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="specifications">Specifications JSON</label>
                        <textarea class="form-control" id="specifications" name="specifications" rows="4">{{ old('specifications', $part->specifications ? json_encode($part->specifications, JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $part->description) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" id="is_api_item" name="is_api_item" type="checkbox" value="1" @checked(old('is_api_item', $part->is_api_item))>
                            <label class="form-check-label" for="is_api_item">API-sourced item</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $part->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $part->exists ? 'Save Part' : 'Create Part' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
