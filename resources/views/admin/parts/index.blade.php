@extends('layouts.admin')

@section('title', 'Admin Parts')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Parts Management</h1>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary" href="{{ route('admin.parts.mobilesentrix.index') }}"><i class="bi bi-cloud-arrow-down me-2"></i>MobileSentrix API</a>
                    <a class="btn btn-primary" href="{{ route('admin.parts.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Part</a>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.parts.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Part, SKU, brand, model">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="brand">Brand</label>
                        <select class="form-select" id="brand" name="brand">
                            <option value="">All</option>
                            @foreach ($partBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) request('brand') === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All</option>
                            @foreach ($partCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="model">Model</label>
                        <select class="form-select" id="model" name="model">
                            <option value="">All</option>
                            @foreach ($partModels as $model)
                                <option value="{{ $model->id }}" @selected((int) request('model') === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="stock">Stock</label>
                        <select class="form-select" id="stock" name="stock">
                            <option value="">All</option>
                            <option value="in" @selected(request('stock') === 'in')>In</option>
                            <option value="out" @selected(request('stock') === 'out')>Out</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="api_status">API</label>
                        <select class="form-select" id="api_status" name="api_status">
                            <option value="">All</option>
                            <option value="active" @selected(request('api_status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('api_status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                </div>
            </form>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th>SKU</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Model</th>
                                <th>Cost</th>
                                <th>Selling</th>
                                <th>Stock</th>
                                <th>Sync</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($parts as $part)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $part->name }}</div>
                                        <div class="small muted">{{ $part->device_type }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $part->sku ?: 'N/A' }}</div>
                                        @if ($part->new_sku)
                                            <div class="small muted">{{ $part->new_sku }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $part->brandName() }}</td>
                                    <td>{{ $part->categoryName() }}</td>
                                    <td>{{ $part->modelName() }}</td>
                                    <td>${{ number_format((float) ($part->cost_price ?: $part->api_price ?: $part->price), 2) }}</td>
                                    <td>${{ number_format($part->displayPrice(), 2) }}</td>
                                    <td>
                                        <div>{{ $part->stockLabel() }}</div>
                                        <div class="small muted">{{ $part->in_stock_qty ?: $part->quantity }} available</div>
                                    </td>
                                    <td>
                                        <div><span class="badge text-bg-{{ $part->api_status === 'inactive' ? 'secondary' : 'success' }}">{{ $part->api_status ?: 'local' }}</span></div>
                                        <div class="small muted">{{ $part->synced_at?->diffForHumans() ?? 'Not synced' }}</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            @if ($part->sku)
                                                <form method="POST" action="{{ route('admin.parts.mobilesentrix.refresh') }}">
                                                    @csrf
                                                    <input type="hidden" name="sku" value="{{ $part->sku }}">
                                                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i><span class="visually-hidden">Refresh</span></button>
                                                </form>
                                            @endif
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.parts.edit', $part) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                            <form method="POST" action="{{ route('admin.parts.destroy', $part) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10">No parts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $parts->links() }}
            </div>
        </div>
    </section>
@endsection
