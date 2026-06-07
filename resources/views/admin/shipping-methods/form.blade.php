@extends('layouts.app')

@section('title', $shippingMethod->exists ? 'Edit Shipping Method' : 'Add Shipping Method')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $shippingMethod->exists ? 'Edit Shipping Method' : 'Add Shipping Method' }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.shipping-methods.index') }}"><i class="bi bi-arrow-left me-2"></i>Shipping Methods</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $shippingMethod->exists ? route('admin.shipping-methods.update', $shippingMethod) : route('admin.shipping-methods.store') }}">
                @csrf
                @if ($shippingMethod->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $shippingMethod->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="code">Code</label>
                        <input class="form-control" id="code" name="code" value="{{ old('code', $shippingMethod->code) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="base_cost">Base cost</label>
                        <input class="form-control" id="base_cost" name="base_cost" type="number" min="0" step="0.01" value="{{ old('base_cost', $shippingMethod->base_cost) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="delivery_days_min">Delivery days min</label>
                        <input class="form-control" id="delivery_days_min" name="delivery_days_min" type="number" min="1" value="{{ old('delivery_days_min', $shippingMethod->delivery_days_min) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="delivery_days_max">Delivery days max</label>
                        <input class="form-control" id="delivery_days_max" name="delivery_days_max" type="number" min="1" value="{{ old('delivery_days_max', $shippingMethod->delivery_days_max) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sort_order">Sort order</label>
                        <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $shippingMethod->sort_order ?? 0) }}" required>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $shippingMethod->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $shippingMethod->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $shippingMethod->exists ? 'Save Method' : 'Create Method' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
