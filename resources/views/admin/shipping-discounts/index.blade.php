@extends('layouts.admin')

@section('title', 'Shipping Discounts')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Shipping Discounts</h1>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.shipping-discounts.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Discount</a>
            </div>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Minimum</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Method</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($discountRules as $rule)
                                <tr>
                                    <td>{{ $rule->name }}</td>
                                    <td>${{ number_format($rule->minimum_order_amount, 2) }}</td>
                                    <td>{{ \App\Models\ShippingDiscountRule::TYPES[$rule->discount_type] ?? $rule->discount_type }}</td>
                                    <td>{{ $rule->discount_type === 'free_shipping' ? 'Free shipping' : number_format($rule->discount_value, 2) }}</td>
                                    <td>{{ $rule->shippingMethod?->name ?? 'All methods' }}</td>
                                    <td class="small muted">
                                        {{ $rule->starts_at?->format('M j, Y') ?? 'Any start' }} - {{ $rule->ends_at?->format('M j, Y') ?? 'Any end' }}
                                    </td>
                                    <td><span class="status-pill">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.shipping-discounts.edit', $rule) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                            <form method="POST" action="{{ route('admin.shipping-discounts.destroy', $rule) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8">No shipping discount rules found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $discountRules->links() }}
            </div>
        </div>
    </section>
@endsection
