@extends('layouts.admin')

@section('title', 'MobileSentrix Procurement Cart')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4">
                <p class="eyebrow">MobileSentrix</p>
                <h1 class="display-6 fw-bold mb-1">Procurement Cart</h1>
                <p class="muted mb-0">MobileSentrix items customers have already paid for. Select what to order now, then place the order with MobileSentrix manually.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->has('procurement'))
                <div class="alert alert-danger">{{ $errors->first('procurement') }}</div>
            @endif

            <form class="surface p-4 mb-4" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status', \App\Models\MobileSentrixBuffer::STATUS_PENDING) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="type">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All</option>
                            <option value="device" @selected(request('type') === 'device')>Device</option>
                            <option value="part" @selected(request('type') === 'part')>Part</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="sku">SKU</label>
                        <input class="form-control" id="sku" name="sku" value="{{ request('sku') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="order_number">Shop Order #</label>
                        <input class="form-control" id="order_number" name="order_number" value="{{ request('order_number') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="repair_number">Repair #</label>
                        <input class="form-control" id="repair_number" name="repair_number" value="{{ request('repair_number') }}">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.mobilesentrix-procurement.index') }}">Reset</a>
                    </div>
                </div>
            </form>

            @if ($aggregates)
                <div class="surface p-3 mb-4">
                    <p class="fw-semibold mb-2">Aggregate demand <span class="muted small fw-normal">— buying guidance only; each requirement stays traceable to its own customer.</span></p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($aggregates as $aggregate)
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $aggregate->source_sku }} — {{ $aggregate->total_required }} across {{ $aggregate->requirement_count }} requirements</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.mobilesentrix-procurement.store') }}" data-procurement-form>
                @csrf
                <div class="surface p-4 table-responsive">
                    <table class="table align-middle" data-procurement-table>
                        <thead>
                            <tr>
                                <th style="width:2.5rem;"><input class="form-check-input" type="checkbox" data-select-all aria-label="Select all"></th>
                                <th>Type</th>
                                <th>SKU</th>
                                <th>Item</th>
                                <th>Customer</th>
                                <th>Shop Order #</th>
                                <th>Repair #</th>
                                <th class="text-end">Required</th>
                                <th class="text-end">Order Now</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                @php($remaining = $item->remainingQuantity())
                                <tr>
                                    <td>
                                        <input class="form-check-input" type="checkbox" data-row-select
                                               aria-label="Select {{ $item->source_sku }}" @disabled($remaining <= 0)>
                                    </td>
                                    <td><span class="badge {{ $item->is_device ? 'bg-info-subtle text-info-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">{{ $item->typeLabel() }}</span></td>
                                    <td class="text-break">{{ $item->source_sku }}</td>
                                    <td>{{ $item->resolved_name }}</td>
                                    <td>{{ $item->customer?->full_name }}</td>
                                    <td>{{ $item->order_number ?: '—' }}</td>
                                    <td>{{ $item->repair_number ?: '—' }}</td>
                                    <td class="text-end">
                                        {{ $remaining }}
                                        @if ($item->processed_quantity > 0)
                                            <span class="muted small d-block">{{ $item->processed_quantity }} of {{ $item->quantity }} ordered</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="width:7rem;">
                                        <input class="form-control form-control-sm text-end" type="number" min="0" max="{{ $remaining }}"
                                               name="quantities[{{ $item->id }}]" value="0" data-quantity
                                               data-unit-price="{{ $item->resolved_price ?? 0 }}" @disabled($remaining <= 0)>
                                    </td>
                                    <td class="text-end">
                                        @if ($item->resolved_price === null)
                                            <span class="badge bg-danger-subtle text-danger-emphasis">Price unavailable</span>
                                        @else
                                            {{ \App\Support\Money::format($item->resolved_price) }}
                                        @endif
                                    </td>
                                    <td class="text-end" data-line-total>{{ \App\Support\Money::format(0) }}</td>
                                    <td>{{ $item->created_at?->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="12">No procurement requirements found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                        <div class="muted">
                            <span data-selected-count>0</span> item(s) selected —
                            estimated subtotal <strong data-selected-subtotal>{{ \App\Support\Money::format(0) }}</strong>
                        </div>
                        <button class="btn btn-primary" type="submit" data-submit disabled>Create MobileSentrix Order</button>
                    </div>

                    {{ $items->links() }}
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-procurement-form]');

            if (!form) {
                return;
            }

            const rows = () => Array.from(form.querySelectorAll('tbody tr')).filter((row) => row.querySelector('[data-quantity]'));
            const money = (value) => 'CAD ' + Number(value || 0).toFixed(2);

            const refresh = () => {
                let count = 0;
                let subtotal = 0;

                rows().forEach((row) => {
                    const checkbox = row.querySelector('[data-row-select]');
                    const quantity = row.querySelector('[data-quantity]');
                    const lineTotal = row.querySelector('[data-line-total]');
                    const ordered = checkbox.checked ? Number(quantity.value || 0) : 0;
                    const line = ordered * Number(quantity.dataset.unitPrice || 0);

                    quantity.disabled = !checkbox.checked || Number(quantity.max) <= 0;
                    lineTotal.textContent = money(line);

                    if (ordered > 0) {
                        count += 1;
                        subtotal += line;
                    }
                });

                form.querySelector('[data-selected-count]').textContent = String(count);
                form.querySelector('[data-selected-subtotal]').textContent = money(subtotal);
                form.querySelector('[data-submit]').disabled = count === 0;
            };

            form.addEventListener('change', (event) => {
                const target = event.target;

                if (target.matches('[data-select-all]')) {
                    rows().forEach((row) => {
                        const checkbox = row.querySelector('[data-row-select]');

                        if (checkbox.disabled) {
                            return;
                        }

                        checkbox.checked = target.checked;
                        const quantity = row.querySelector('[data-quantity]');

                        if (target.checked && Number(quantity.value || 0) === 0) {
                            quantity.value = quantity.max;
                        }
                    });
                }

                if (target.matches('[data-row-select]')) {
                    const quantity = target.closest('tr').querySelector('[data-quantity]');

                    if (target.checked && Number(quantity.value || 0) === 0) {
                        quantity.value = quantity.max;
                    }

                    if (!target.checked) {
                        quantity.value = 0;
                    }
                }

                refresh();
            });

            form.addEventListener('input', (event) => {
                if (event.target.matches('[data-quantity]')) {
                    refresh();
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const count = form.querySelector('[data-selected-count]').textContent;
                const subtotal = form.querySelector('[data-selected-subtotal]').textContent;

                const result = await window.EcliseAlert.confirm({
                    title: 'Create MobileSentrix order?',
                    html: `This creates an internal procurement order for <strong>${count}</strong> item(s), estimated <strong>${subtotal}</strong>.<br><br>No order is placed with MobileSentrix automatically — you must still place it manually.`,
                    confirmButtonText: 'Create order',
                });

                if (result.isConfirmed) {
                    form.submit();
                }
            });

            refresh();
        })();
    </script>
@endpush
