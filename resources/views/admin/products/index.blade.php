@extends('layouts.admin')

@section('title', 'Admin Products')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Product Management</h1>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Product</a>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.products.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Name, SKU, brand">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="brand">Brand</label>
                        <select class="form-select" id="brand" name="brand">
                            <option value="">All</option>
                            @foreach ($productBrands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) request('brand') === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="model">Model</label>
                        <select class="form-select" id="model" name="model">
                            <option value="">All</option>
                            @foreach ($productModels as $model)
                                <option value="{{ $model->id }}" @selected((int) request('model') === $model->id)>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="condition">Condition</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="">All</option>
                            @foreach ($productConditions as $condition)
                                <option value="{{ $condition->id }}" @selected((int) request('condition') === $condition->id)>{{ $condition->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="active">Active</label>
                        <select class="form-select" id="active" name="active">
                            <option value="">All</option>
                            @foreach ($activeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('active') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                </div>
            </form>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Sizes</th>
                                <th>Condition</th>
                                <th>Network</th>
                                <th>Regular</th>
                                <th>Sale</th>
                                <th>Stock</th>
                                <th>Active</th>
                                <th>Featured</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td><img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" style="width: 54px; height: 54px; object-fit: contain;" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';"></td>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->categoryName() }}</td>
                                    <td>{{ $product->brandName() }}</td>
                                    <td>{{ $product->modelName() }}</td>
                                    <td>{{ $product->sizeNames() ?: '—' }}</td>
                                    <td>{{ $product->conditionName() }}</td>
                                    <td>{{ $product->networkName() ?: '—' }}</td>
                                    <td>${{ number_format($product->regularDisplayPrice(), 2) }}</td>
                                    <td>{{ $product->sale_price !== null ? '$'.number_format((float) $product->sale_price, 2) : '—' }}</td>
                                    <td>{{ $product->quantity }}</td>
                                    <td><span class="status-pill">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>{{ $product->is_featured ? 'Yes' : 'No' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.products.edit', $product) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="15">No products found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $products->links() }}
            </div>
        </div>
    </section>
@endsection
