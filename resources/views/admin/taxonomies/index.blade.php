@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $title }}</h1>
                </div>
                <a class="btn btn-primary" href="{{ route($routePrefix.'.create') }}"><i class="bi bi-plus-lg me-2"></i>Add</a>
            </div>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Sort</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                        <div class="small muted">{{ $item->description }}</div>
                                    </td>
                                    <td>{{ $item->slug }}</td>
                                    <td>{{ $item->sort_order }}</td>
                                    <td>
                                        <span class="status-pill">
                                            {{ ($usesStatus ?? false) ? ucfirst($item->status) : ($item->is_active ? 'Active' : 'Inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route($routePrefix.'.edit', $item) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                            <form method="POST" action="{{ route($routePrefix.'.destroy', $item) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $items->links() }}
            </div>
        </div>
    </section>
@endsection
