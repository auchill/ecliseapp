@extends('layouts.admin')

@section('title', 'Pre-Owned Devices')

@section('content')
    <style>
        .ms-device-table thead th { background: #c91522; color: #fff; border-color: #b2101d; font-size: .78rem; text-transform: uppercase; white-space: nowrap; }
        .ms-device-table td { vertical-align: middle; font-size: .9rem; }
        .ms-filter-chip { border: 1px solid #dbe4f0; border-radius: 999px; padding: .35rem .65rem; background: #f8fafc; font-size: .8rem; }
        .ms-price { color: #c91522; font-weight: 800; white-space: nowrap; }
    </style>

    <section class="section-pad bg-white">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">MobileSentrix Devices</p>
                    <h1 class="display-6 fw-bold mb-0">Pre-Owned Devices</h1>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="{{ route('admin.devices.export', request()->query()) }}"><i class="bi bi-download me-2"></i>Export CSV</a>
                    <form class="d-flex gap-2" method="POST" action="{{ route('admin.devices.sync') }}">
                        @csrf
                        <input class="form-control" type="number" name="limit" min="1" max="500" value="30" aria-label="Sync limit" style="width: 96px;">
                        <input class="form-control" type="number" name="page" min="1" value="1" aria-label="Sync page" style="width: 86px;">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Sync Devices</button>
                    </form>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.devices.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Make, model, SKU, entity ID">
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="availability">Availability</label>
                        <select class="form-select" id="availability" name="availability">
                            <option value="">All</option>
                            <option value="in_stock" @selected(request('availability') === 'in_stock')>In Stock</option>
                            <option value="out_of_stock" @selected(request('availability') === 'out_of_stock')>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="price_sort">Price sort</label>
                        <select class="form-select" id="price_sort" name="price_sort">
                            <option value="">Default</option>
                            <option value="price_asc" @selected(request('price_sort') === 'price_asc')>Low to High</option>
                            <option value="price_desc" @selected(request('price_sort') === 'price_desc')>High to Low</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="price_min">Min</label>
                        <input class="form-control" id="price_min" name="price_min" value="{{ request('price_min') }}" inputmode="decimal">
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="price_max">Max</label>
                        <input class="form-control" id="price_max" name="price_max" value="{{ request('price_max') }}" inputmode="decimal">
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="per_page">Rows</label>
                        <select class="form-select" id="per_page" name="per_page">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected((int) request('per_page', 10) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Filter</span></button>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <a class="btn btn-dark w-100" href="{{ route('admin.devices.index') }}"><i class="bi bi-x-lg"></i><span class="visually-hidden">Reset Filters</span></a>
                    </div>
                </div>
            </form>

            @if ($selectedChips)
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($selectedChips as $chip)
                        <span class="ms-filter-chip">{{ $chip['label'] }}: {{ $chip['value'] }}</span>
                    @endforeach
                </div>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <p class="mb-0 text-primary fw-bold">{{ number_format($devices->total()) }} result{{ $devices->total() === 1 ? '' : 's' }}</p>
                <p class="mb-0 muted">Showing {{ number_format($devices->firstItem() ?? 0) }}-{{ number_format($devices->lastItem() ?? 0) }}</p>
            </div>

            <div class="surface p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover ms-device-table mb-0">
                        <thead>
                            <tr>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Size</th>
                                <th>Color</th>
                                <th>Condition</th>
                                <th>Carrier</th>
                                <th>Available Qty</th>
                                <th>Price</th>
                                <th>SKU</th>
                                <th>Entity ID</th>
                                <th>Synced</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr>
                                    <td>{{ $device->manufacturer_text ?: '—' }}</td>
                                    <td>{{ $device->device_model_text ?: '—' }}</td>
                                    <td>{{ $device->device_size_text ?: '—' }}</td>
                                    <td>{{ $device->device_color_text ?: '—' }}</td>
                                    <td>{{ $device->condition_text ?: '—' }}</td>
                                    <td>{{ $device->device_carrier_text ?: '—' }}</td>
                                    <td>{{ number_format($device->availableQuantity()) }} pcs</td>
                                    <td class="ms-price">{{ $device->displayPrice() !== null ? 'CA$'.number_format($device->displayPrice(), 2) : '—' }}</td>
                                    <td>{{ $device->sku ?: '—' }}</td>
                                    <td>{{ $device->entity_id ?: '—' }}</td>
                                    <td>{{ $device->synced_at?->diffForHumans() ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center py-5 muted" colspan="11">No MobileSentrix devices found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $devices->links() }}</div>
        </div>
    </section>
@endsection
