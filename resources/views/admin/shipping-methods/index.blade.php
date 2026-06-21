@extends('layouts.admin')

@section('title', 'Shipping Methods')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Shipping Methods</h1>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.shipping-methods.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Method</a>
            </div>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Base Cost</th>
                                <th>Delivery</th>
                                <th>Sort</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shippingMethods as $method)
                                <tr>
                                    <td>
                                        <strong>{{ $method->name }}</strong>
                                        <div class="small muted">{{ $method->description }}</div>
                                    </td>
                                    <td>{{ $method->code }}</td>
                                    <td>${{ number_format($method->base_cost, 2) }}</td>
                                    <td>{{ $method->deliveryDaysLabel() }}</td>
                                    <td>{{ $method->sort_order }}</td>
                                    <td><span class="status-pill">{{ $method->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.shipping-methods.edit', $method) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                            <form method="POST" action="{{ route('admin.shipping-methods.destroy', $method) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7">No shipping methods found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $shippingMethods->links() }}
            </div>
        </div>
    </section>
@endsection
