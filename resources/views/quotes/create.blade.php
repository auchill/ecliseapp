@extends('layouts.app')

@section('title', 'Get a Quote')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair Quote</p>
            <h1 class="display-5 fw-bold mb-3">Request a repair quote.</h1>
            <p class="fs-5 mb-0">Send the device details first. We will review the request and contact you before creating a repair.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('quotes.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-4">
                    <div class="col-12">
                        <div class="alert alert-light border mb-0">
                            <strong>{{ auth()->user()?->customer?->full_name ?: auth()->user()?->name }}</strong>
                            <div class="small text-muted">{{ auth()->user()?->customer?->email ?: auth()->user()?->email }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_type_id">Device type</label>
                        <select class="form-select" id="device_type_id" name="device_type_id" required>
                            <option value="">Choose device type</option>
                            @foreach ($deviceTypes as $type)
                                <option value="{{ $type->id }}" @selected((int) old('device_type_id') === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="product_brand_id">Device brand</label>
                        <select class="form-select" id="product_brand_id" name="product_brand_id" required>
                            <option value="">Choose brand</option>
                            @foreach ($productBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) old('product_brand_id') === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="product_model_id">Device model</label>
                        <select class="form-select" id="product_model_id" name="product_model_id">
                            <option value="">Not listed</option>
                            @foreach ($productModels as $model)
                                <option value="{{ $model->id }}" @selected((int) old('product_model_id') === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_model">Custom device model</label>
                        <input class="form-control" id="device_model" name="device_model" value="{{ old('device_model') }}" placeholder="Use this if the model is not listed">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="issue_category_id">Issue category</label>
                        <select class="form-select" id="issue_category_id" name="issue_category_id" required>
                            <option value="">Choose issue</option>
                            @foreach ($issueCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('issue_category_id') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_date">Preferred date</label>
                        <input class="form-control" id="preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preferred_time">Preferred time</label>
                        <input class="form-control" id="preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="device_image">Device image</label>
                        <input class="form-control" id="device_image" name="device_image" type="file" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="issue_description">Issue description</label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required>{{ old('issue_description') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send me-2"></i>Submit Quote Request</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
