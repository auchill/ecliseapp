@extends('layouts.app')

@section('title', 'Convert Quote')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Quote {{ $quote->quote_number }}</p>
                    <h1 class="display-6 fw-bold mb-0">Create Repair Booking</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.quotes.show', $quote) }}"><i class="bi bi-arrow-left me-2"></i>Quote</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ route('admin.quotes.convert.store', $quote) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="device_type_id">Device type</label>
                        <select class="form-select" id="device_type_id" name="device_type_id" required>
                            @foreach ($deviceTypes as $type)
                                <option value="{{ $type->id }}" @selected((int) old('device_type_id', $quote->device_type_id) === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="device_brand_id">Brand</label>
                        <select class="form-select" id="device_brand_id" name="device_brand_id">
                            <option value="">No brand</option>
                            @foreach ($deviceBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) old('device_brand_id', $quote->device_brand_id) === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="device_model_id">Listed model</label>
                        <select class="form-select" id="device_model_id" name="device_model_id">
                            <option value="">Custom model</option>
                            @foreach ($deviceModels as $model)
                                <option value="{{ $model->id }}" @selected((int) old('device_model_id', $quote->device_model_id) === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="device_model">Custom model</label>
                        <input class="form-control" id="device_model" name="device_model" value="{{ old('device_model', $quote->device_model) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="issue_category_id">Issue category</label>
                        <select class="form-select" id="issue_category_id" name="issue_category_id" required>
                            @foreach ($issueCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('issue_category_id', $quote->issue_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="preferred_appointment_date">Preferred date</label>
                        <input class="form-control" id="preferred_appointment_date" name="preferred_appointment_date" type="date" value="{{ old('preferred_appointment_date', $quote->preferred_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="preferred_appointment_time">Preferred time</label>
                        <input class="form-control" id="preferred_appointment_time" name="preferred_appointment_time" type="time" value="{{ old('preferred_appointment_time', $quote->preferred_time) }}">
                    </div>

                    <div class="col-12"><hr><h2 class="h5 fw-bold">Repair Pricing Items</h2></div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="col-md-2">
                            <label class="form-label" for="repair_item_type_{{ $i }}">Type</label>
                            <select class="form-select" id="repair_item_type_{{ $i }}" name="repair_item_type[]">
                                @foreach (['workmanship' => 'Workmanship', 'part' => 'Part', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old("repair_item_type.$i", $i === 1 ? 'part' : 'workmanship') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="repair_item_name_{{ $i }}">Item</label>
                            <input class="form-control" id="repair_item_name_{{ $i }}" name="repair_item_name[]" value="{{ old("repair_item_name.$i", $i === 0 ? 'Repair labour' : ($i === 1 ? $quote->deviceLabel().' part' : '')) }}" @required($i < 2)>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="repair_item_quantity_{{ $i }}">Qty</label>
                            <input class="form-control" id="repair_item_quantity_{{ $i }}" name="repair_item_quantity[]" type="number" min="0.01" step="0.01" value="{{ old("repair_item_quantity.$i", $i < 2 ? 1 : '') }}" @required($i < 2)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="repair_item_unit_price_{{ $i }}">Unit price</label>
                            <input class="form-control" id="repair_item_unit_price_{{ $i }}" name="repair_item_unit_price[]" type="number" min="0" step="0.01" value="{{ old("repair_item_unit_price.$i", $i === 0 ? 80 : ($i === 1 ? 120 : '')) }}" @required($i < 2)>
                        </div>
                    @endfor
                    <div class="col-md-4">
                        <label class="form-label" for="tax_amount">Tax amount</label>
                        <input class="form-control" id="tax_amount" name="tax_amount" type="number" min="0" step="0.01" value="{{ old('tax_amount', 0) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="internal_notes">Internal note</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="4">{{ old('internal_notes', $quote->admin_note) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-2"></i>Convert to Booking</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
