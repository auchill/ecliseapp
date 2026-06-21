@extends('layouts.admin')

@section('title', 'Permissions')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="eyebrow">Admin Users</p>
            <h2 class="h1 fw-bold mb-0">Permissions</h2>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.permissions.create') }}"><i class="bi bi-shield-plus me-2"></i>Create Permission</a>
    </div>

    <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.permissions.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Permission name">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
            </div>
        </div>
    </form>

    <div class="surface p-4">
        @error('permission')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td>{{ ucfirst($permission->name) }}</td>
                            <td><span class="status-pill">{{ ucfirst($permission->status) }}</span></td>
                            <td>{{ $permission->users_count }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.permissions.edit', $permission) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No permissions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $permissions->links() }}
    </div>
@endsection
