<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Part</th>
                <th>SKU</th>
                <th>Brand</th>
                <th>Category</th>
                <th>Model</th>
                <th>Cost</th>
                <th>Selling</th>
                <th>Stock</th>
                <th>Sync</th>
                <th>Local</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($parts as $part)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $part->name }}</div>
                        <div class="small muted">{{ $part->device_type }}</div>
                        @if ($part->mobilesentrix_product_id)
                            <div class="small muted">MS ID: {{ $part->mobilesentrix_product_id }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $part->sku ?: 'N/A' }}</div>
                        @if ($part->new_sku)
                            <div class="small muted">{{ $part->new_sku }}</div>
                        @endif
                    </td>
                    <td>{{ $part->brandName() }}</td>
                    <td>{{ $part->categoryName() }}</td>
                    <td>{{ $part->modelName() }}</td>
                    <td>${{ number_format((float) ($part->cost_price ?: $part->api_price ?: $part->price), 2) }}</td>
                    <td>${{ number_format($part->displayPrice(), 2) }}</td>
                    <td>
                        <div>{{ $part->stockLabel() }}</div>
                        <div class="small muted">{{ $part->in_stock_qty ?: $part->quantity }} available</div>
                    </td>
                    <td>
                        <div><span class="badge text-bg-{{ $part->api_status === 'inactive' ? 'secondary' : 'success' }}">{{ $part->api_status ?: 'local' }}</span></div>
                        <div class="small muted">{{ $part->synced_at?->diffForHumans() ?? 'Not synced' }}</div>
                    </td>
                    <td>
                        <span class="badge text-bg-{{ $part->status === 'active' && $part->is_active ? 'success' : 'secondary' }}">{{ $part->status ?: 'active' }}</span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            @if ($part->sku)
                                <form method="POST" action="{{ route('admin.parts.mobilesentrix.refresh') }}">
                                    @csrf
                                    <input type="hidden" name="sku" value="{{ $part->sku }}">
                                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i><span class="visually-hidden">Refresh</span></button>
                                </form>
                            @endif
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.parts.edit', $part) }}"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></a>
                            <form method="POST" action="{{ route('admin.parts.destroy', $part) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11">No parts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div data-parts-pagination>
    {{ $parts->links() }}
</div>
