@extends('layouts.app')

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
                    <form method="POST" action="{{ route('admin.parts.sync') }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Sync Placeholder</button>
                    </form>
                    <a class="btn btn-primary" href="{{ route('admin.parts.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Part</a>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.parts.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-10">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Part, brand, model">
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
                                <th>Part</th>
                                <th>Device</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($parts as $part)
                                <tr>
                                    <td>{{ $part->name }}</td>
                                    <td>{{ $part->device_type }}</td>
                                    <td>{{ $part->brandName() }}</td>
                                    <td>{{ $part->categoryName() }}</td>
                                    <td>${{ number_format($part->displayPrice(), 2) }}</td>
                                    <td>{{ $part->availability_status ?: $part->stock_status }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
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
                                <tr><td colspan="7">No parts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $parts->links() }}
            </div>
        </div>
    </section>
@endsection
