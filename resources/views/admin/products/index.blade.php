@extends('layouts.app')

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
                    <div class="col-lg-10">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Name, SKU, brand">
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
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->categoryName() }}</td>
                                    <td>${{ number_format($product->currentPrice(), 2) }}</td>
                                    <td>{{ $product->quantity }}</td>
                                    <td><span class="status-pill">{{ $product->status }}</span></td>
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
                                <tr><td colspan="7">No products found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $products->links() }}
            </div>
        </div>
    </section>
@endsection
