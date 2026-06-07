@extends('layouts.app')

@section('title', 'Parts Price Check')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Parts Price Check</p>
            <h1 class="display-5 fw-bold mb-3">Verify repair part prices.</h1>
            <p class="fs-5 mb-0">Parts are shown for price verification only. Please contact us for repair service availability.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 mb-4" method="GET" action="{{ route('parts.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Screen, battery, model">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="brand">Brand</label>
                        <select class="form-select" id="brand" name="brand">
                            <option value="">All</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->slug }}" @selected(request('brand') === $brand->slug)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="model">Model</label>
                        <input class="form-control" id="model" name="model" value="{{ request('model') }}">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="device_type">Device</label>
                        <select class="form-select" id="device_type" name="device_type">
                            <option value="">All</option>
                            @foreach ($deviceTypes as $deviceType)
                                <option value="{{ $deviceType }}" @selected(request('device_type') === $deviceType)>{{ $deviceType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="part_category">Category</label>
                        <select class="form-select" id="part_category" name="part_category">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected(request('part_category') === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                </div>
            </form>

            <div class="alert alert-info border-0">Parts are shown for price verification only. Please contact us for repair service availability.</div>

            <div class="row g-4">
                @forelse ($parts as $part)
                    <div class="col-md-6 col-xl-3">
                        <div class="surface part-card h-100 overflow-hidden">
                            <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}">
                            <div class="p-4">
                                <p class="eyebrow mb-1">{{ $part->categoryName() }}</p>
                                <h2 class="h5 fw-bold">{{ $part->name }}</h2>
                                <p class="muted small">{{ $part->brandName() }} &middot; {{ $part->model_compatibility }} &middot; {{ $part->availability_status ?: $part->stock_status }}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>${{ number_format($part->displayPrice(), 2) }}</strong>
                                    <span class="small muted">{{ $part->external_api_source ?: $part->supplier }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="surface p-4">No parts match the selected filters.</div>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $parts->links() }}
            </div>
        </div>
    </section>
@endsection
