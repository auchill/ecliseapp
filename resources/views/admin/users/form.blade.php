@extends('layouts.admin')

@section('title', $user->exists ? 'Edit User' : 'Create User')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="eyebrow">Admin Users</p>
            <h2 class="h1 fw-bold mb-0">{{ $user->exists ? 'Edit User' : 'Create User' }}</h2>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-left me-2"></i>Users</a>
    </div>

    <form class="surface p-4" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="permission_id">Permission</label>
                <select class="form-select @error('permission_id') is-invalid @enderror" id="permission_id" name="permission_id" required>
                    <option value="">Select permission</option>
                    @foreach ($permissions as $permission)
                        <option value="{{ $permission->id }}" @selected((string) old('permission_id', $user->permission_id) === (string) $permission->id)>{{ ucfirst($permission->name) }} · {{ ucfirst($permission->status) }}</option>
                    @endforeach
                </select>
                @error('permission_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $user->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="password">Password</label>
                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" @required(! $user->exists)>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" @required(! $user->exists)>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">{{ $user->exists ? 'Update User' : 'Create User' }}</button>
        </div>
    </form>
@endsection
