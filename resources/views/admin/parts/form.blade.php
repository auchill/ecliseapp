@extends('layouts.app')

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
                        <label class="form-label" for="model_compatibility">Model compatibility</label>
                        <input class="form-control" id="model_compatibility" name="model_compatibility" value="{{ old('model_compatibility', $part->model_compatibility) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="part_category">Category</label>
                        <select class="form-select" id="part_category" name="part_category" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('part_category', $part->part_category ?: 'Screens') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="price">Price</label>
                        <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $part->price) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="stock_status">Stock status</label>
                        <input class="form-control" id="stock_status" name="stock_status" value="{{ old('stock_status', $part->stock_status ?: 'Check availability') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="supplier">Supplier</label>
                        <input class="form-control" id="supplier" name="supplier" value="{{ old('supplier', $part->supplier ?: 'MobileSentrix') }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="part_image">Part image</label>
                        <input class="form-control" id="part_image" name="part_image" type="file" accept="image/*">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $part->exists ? 'Save Part' : 'Create Part' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
