@extends('layouts.admin')

@section('title', $permission->exists ? 'Edit Permission' : 'Create Permission')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="eyebrow">Permissions</p>
            <h2 class="h1 fw-bold mb-0">{{ $permission->exists ? 'Edit Permission' : 'Create Permission' }}</h2>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.permissions.index') }}"><i class="bi bi-arrow-left me-2"></i>Permissions</a>
    </div>

    <form class="surface p-4" method="POST" action="{{ $permission->exists ? route('admin.permissions.update', $permission) : route('admin.permissions.store') }}">
        @csrf
        @if ($permission->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $permission->name) }}" @readonly(in_array($permission->name, ['admin', 'customer'], true)) required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $permission->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
            @if ($permission->exists)
                <button class="btn btn-outline-danger" type="submit" form="delete-permission" @disabled(in_array($permission->name, ['admin', 'customer'], true))>
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            @else
                <span></span>
            @endif
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('admin.permissions.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">{{ $permission->exists ? 'Update Permission' : 'Create Permission' }}</button>
            </div>
        </div>
    </form>

    @if ($permission->exists)
        <form id="delete-permission" method="POST" action="{{ route('admin.permissions.destroy', $permission) }}">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
