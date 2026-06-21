@extends('layouts.admin')

@section('title', $discountRule->exists ? 'Edit Shipping Discount' : 'Add Shipping Discount')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $discountRule->exists ? 'Edit Shipping Discount' : 'Add Shipping Discount' }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.shipping-discounts.index') }}"><i class="bi bi-arrow-left me-2"></i>Shipping Discounts</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $discountRule->exists ? route('admin.shipping-discounts.update', $discountRule) : route('admin.shipping-discounts.store') }}">
                @csrf
                @if ($discountRule->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $discountRule->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="minimum_order_amount">Minimum order amount</label>
                        <input class="form-control" id="minimum_order_amount" name="minimum_order_amount" type="number" min="0" step="0.01" value="{{ old('minimum_order_amount', $discountRule->minimum_order_amount) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="discount_type">Discount type</label>
                        <select class="form-select" id="discount_type" name="discount_type" required>
                            @foreach ($discountTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('discount_type', $discountRule->discount_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="discount_value">Discount value</label>
                        <input class="form-control" id="discount_value" name="discount_value" type="number" min="0" step="0.01" value="{{ old('discount_value', $discountRule->discount_value) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="shipping_method_id">Applies to method</label>
                        <select class="form-select" id="shipping_method_id" name="shipping_method_id">
                            <option value="">All shipping methods</option>
                            @foreach ($shippingMethods as $method)
                                <option value="{{ $method->id }}" @selected((int) old('shipping_method_id', $discountRule->shipping_method_id) === $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="starts_at">Starts at</label>
                        <input class="form-control" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $discountRule->starts_at?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ends_at">Ends at</label>
                        <input class="form-control" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $discountRule->ends_at?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $discountRule->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>{{ $discountRule->exists ? 'Save Discount' : 'Create Discount' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
