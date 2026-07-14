@extends('layouts.admin')

@section('title', 'MobileSentrix Price Markup')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">MobileSentrix</p>
                    <h1 class="display-6 fw-bold mb-0">Price Markup</h1>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.mobilesentrix-markups.refresh') }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-clockwise me-2"></i>Refresh Calculated Prices</button>
                    </form>
                    <a class="btn btn-primary" href="{{ route('admin.mobilesentrix-markups.create') }}"><i class="bi bi-plus-lg me-2"></i>Add Rule</a>
                </div>
            </div>

            <form class="surface p-4 mb-4" method="GET" action="{{ route('admin.mobilesentrix-markups.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="item_type">Inventory Type</label>
                        <select class="form-select" id="item_type" name="item_type">
                            <option value="">All</option>
                            @foreach (\App\Models\EcliseMarkup::ITEM_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(request('item_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="scope_type">Scope</label>
                        <select class="form-select" id="scope_type" name="scope_type">
                            <option value="">All</option>
                            @foreach (\App\Models\EcliseMarkup::SCOPE_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(request('scope_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="active">Status</label>
                        <select class="form-select" id="active" name="active">
                            <option value="">All</option>
                            <option value="1" @selected(request('active') === '1')>Active</option>
                            <option value="0" @selected(request('active') === '0')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
                    </div>
                </div>
            </form>

            @foreach (\App\Models\EcliseMarkup::ITEM_TYPES as $itemType => $itemLabel)
                <div class="surface p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">{{ $itemLabel }}</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Scope</th>
                                    <th>MobileSentrix Category</th>
                                    <th>Markup</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($markups->where('item_type', $itemType) as $markup)
                                    <tr>
                                        <td>{{ $markup->scopeTypeLabel() }}</td>
                                        <td>{{ $markup->category_id ? ($categoryLabels[$markup->item_type][$markup->category_id] ?? 'MobileSentrix Category #'.$markup->category_id) : 'All '.$itemLabel }}</td>
                                        <td>
                                            {{ $markup->markupTypeLabel() }}
                                            <div class="small muted">
                                                {{ $markup->markup_type === \App\Models\EcliseMarkup::MARKUP_PERCENTAGE ? rtrim(rtrim(number_format((float) $markup->markup_value, 2), '0'), '.').'%' : '$'.number_format((float) $markup->markup_value, 2) }}
                                            </div>
                                        </td>
                                        <td>{{ $markup->priority }}</td>
                                        <td><span class="status-pill">{{ $markup->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td>{{ $markup->updated_at?->format('M j, Y g:i A') }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.mobilesentrix-markups.edit', $markup) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                                                <form method="POST" action="{{ route('admin.mobilesentrix-markups.toggle', $markup) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit">{{ $markup->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.mobilesentrix-markups.destroy', $markup) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7">No {{ strtolower($itemLabel) }} markup rules found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{ $markups->links() }}
        </div>
    </section>
@endsection
