@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="eyebrow">Admin</p>
            <h2 class="h1 fw-bold mb-0">Users</h2>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><i class="bi bi-person-plus me-2"></i>Create User</a>
    </div>

    <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.users.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Name or email">
            </div>
            <div class="col-md-4 col-lg-3">
                <label class="form-label" for="permission_id">Permission</label>
                <select class="form-select" id="permission_id" name="permission_id">
                    <option value="">All permissions</option>
                    @foreach ($permissions as $permission)
                        <option value="{{ $permission->id }}" @selected((string) request('permission_id') === (string) $permission->id)>{{ ucfirst($permission->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
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
                        <th>Permission</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ ucfirst($user->permission?->name ?? $user->role ?? 'Unassigned') }}</td>
                            <td><span class="status-pill">{{ ucfirst($user->status ?? 'active') }}</span></td>
                            <td>{{ $user->created_at?->format('M j, Y') }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
