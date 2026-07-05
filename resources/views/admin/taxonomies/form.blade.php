@extends('layouts.admin')

@section('title', $title)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $title }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route($routePrefix.'.index') }}"><i class="bi bi-arrow-left me-2"></i>Back</a>
            </div>

            <form class="surface p-4" method="POST" action="{{ $item->exists ? route($routePrefix.'.update', $item) : route($routePrefix.'.store') }}">
                @csrf
                @if ($item->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $item->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="slug">Slug</label>
                        <input class="form-control" id="slug" name="slug" value="{{ old('slug', $item->slug) }}">
                    </div>
                    @if($usesCodeSource ?? false)
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code</label>
                            <input class="form-control" id="code" name="code" value="{{ old('code', $item->code) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="source">Source</label>
                            <input class="form-control" id="source" name="source" value="{{ old('source', $item->source) }}">
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label" for="sort_order">Sort order</label>
                        <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}" required>
                    </div>
                    <div class="col-md-8 {{ ($usesStatus ?? false) ? '' : 'd-flex align-items-end' }}">
                        @if ($usesStatus ?? false)
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $item->status ?: 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="form-check mb-2">
                                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $item->is_active ?? true))>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $item->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i>Save</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
