@extends('layouts.admin')

@section('title', 'Admin Customers')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Customer Management</h1>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.customers.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-10">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Name or email">
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
                                <th>Name</th>
                                <th>Email</th>
                                <th>Repairs</th>
                                <th>Orders</th>
                                <th>Joined</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $customer)
                                <tr>
                                    <td>{{ $customer->name }}</td>
                                    <td>{{ $customer->email }}</td>
                                    <td>{{ $customer->repair_bookings_count }}</td>
                                    <td>{{ $customer->orders_count }}</td>
                                    <td>{{ $customer->created_at->format('M j, Y') }}</td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.customers.show', $customer) }}"><i class="bi bi-eye"></i><span class="visually-hidden">Open</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No customers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $customers->links() }}
            </div>
        </div>
    </section>
@endsection
