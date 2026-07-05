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
                        <label class="form-label" for="brand">Brand</label>
                        <input class="form-control" id="brand" name="brand" value="{{ old('brand', $part->brand) }}" required>
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
                        <label class="form-label" for="model_compatibility">Model compatibility</label>
                        <input class="form-control" id="model_compatibility" name="model_compatibility" value="{{ old('model_compatibility', $part->model_compatibility) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sku">API SKU</label>
                        <input class="form-control" id="sku" name="sku" value="{{ old('sku', $part->sku) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_sku">New SKU</label>
                        <input class="form-control" id="new_sku" name="new_sku" value="{{ old('new_sku', $part->new_sku) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="barcode">Barcode</label>
                        <input class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $part->barcode) }}">
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
                        <label class="form-label" for="cost_price">Supplier cost</label>
                        <input class="form-control" id="cost_price" name="cost_price" type="number" min="0" step="0.01" value="{{ old('cost_price', $part->cost_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="selling_price">Selling price</label>
                        <input class="form-control" id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', $part->selling_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="markup_type">Markup type</label>
                        <select class="form-select" id="markup_type" name="markup_type">
                            @foreach ($markupTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('markup_type', $part->markup_type ?: 'none') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="markup_value">Markup value</label>
                        <input class="form-control" id="markup_value" name="markup_value" type="number" min="0" step="0.01" value="{{ old('markup_value', $part->markup_value ?? 0) }}">
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
                        <label class="form-label" for="in_stock_qty">API stock quantity</label>
                        <input class="form-control" id="in_stock_qty" name="in_stock_qty" type="number" min="0" value="{{ old('in_stock_qty', $part->in_stock_qty) }}">
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
                    @if ($part->exists && $part->is_api_item)
                        <div class="col-md-4">
                            <label class="form-label">MobileSentrix product ID</label>
                            <input class="form-control" value="{{ $part->id }}" readonly>
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label" for="api_status">API status</label>
                        <input class="form-control" id="api_status" name="api_status" value="{{ old('api_status', $part->api_status) }}">
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
                            <input class="form-check-input" id="is_in_stock" name="is_in_stock" type="checkbox" value="1" @checked(old('is_in_stock', $part->is_in_stock))>
                            <label class="form-check-label" for="is_in_stock">In stock</label>
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
