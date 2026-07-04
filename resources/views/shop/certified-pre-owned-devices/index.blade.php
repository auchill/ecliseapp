@extends('layouts.app')

@section('title', 'Certified Pre-Owned Devices')

@section('content')
    <style>
        .cpo-table thead th { background: #c91522; color: #fff; border-color: #b2101d; font-size: .78rem; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
        .cpo-table td { vertical-align: middle; font-size: .9rem; }
        .cpo-price { color: #c91522; font-weight: 800; white-space: nowrap; }
        .cpo-filter-menu { min-width: 260px; max-height: 360px; overflow-y: auto; }
        .cpo-chip { border: 1px solid #dbe4f0; border-radius: 999px; padding: .35rem .65rem; background: #f8fafc; font-size: .8rem; }
        .cpo-qty { width: 110px; }
        .cpo-cart-box { border: 2px solid #071d3a; border-radius: 8px; padding: 1rem; background: #fff; }
    </style>

    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Shop</p>
            <h1 class="display-5 fw-bold mb-3">Certified Pre-Owned Devices</h1>
            <p class="fs-5 mb-0">Browse available certified pre-owned devices.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container-fluid">
            <form id="cpoFilters" class="surface p-4 mb-4" method="GET" action="{{ route('shop.certified-pre-owned-devices.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-xl-3">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Make, model, SKU">
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="price_sort">Sort price</label>
                        <select class="form-select" id="price_sort" name="price_sort">
                            <option value="">Default</option>
                            <option value="price_asc" @selected(request('price_sort') === 'price_asc')>Low to High</option>
                            <option value="price_desc" @selected(request('price_sort') === 'price_desc')>High to Low</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="price_min">Min</label>
                        <input class="form-control" id="price_min" name="price_min" value="{{ request('price_min') }}" inputmode="decimal">
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="price_max">Max</label>
                        <input class="form-control" id="price_max" name="price_max" value="{{ request('price_max') }}" inputmode="decimal">
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <label class="form-label" for="per_page">Rows</label>
                        <select class="form-select" id="per_page" name="per_page">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected((int) request('per_page', 10) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Filter</span></button>
                    </div>
                    <div class="col-sm-6 col-xl-1">
                        <a class="btn btn-dark w-100" href="{{ route('shop.certified-pre-owned-devices.index') }}"><i class="bi bi-x-lg"></i><span class="visually-hidden">Reset Filters</span></a>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <a class="btn btn-outline-primary w-100" href="{{ route('shop.certified-pre-owned-devices.export', request()->query()) }}"><i class="bi bi-download me-2"></i>Export Result CSV</a>
                    </div>
                </div>

                @foreach ($filterOptions as $field => $option)
                    @foreach ((array) request($field, []) as $selected)
                        <input type="hidden" name="{{ $field }}[]" value="{{ $selected }}" data-cpo-hidden-filter="{{ $field }}">
                    @endforeach
                @endforeach
            </form>

            <div class="row g-4 align-items-start">
                <div class="col-xl-10">
                    @if ($selectedChips)
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($selectedChips as $chip)
                                <span class="cpo-chip">{{ $chip['label'] }}: {{ $chip['value'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <p class="mb-0 text-primary fw-bold">{{ number_format($devices->total()) }} result{{ $devices->total() === 1 ? '' : 's' }}</p>
                        <p class="mb-0 muted">Showing {{ number_format($devices->firstItem() ?? 0) }}-{{ number_format($devices->lastItem() ?? 0) }}</p>
                    </div>

                    <div class="surface p-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover cpo-table mb-0">
                                <thead>
                                    <tr>
                                        @foreach (['manufacturer_text' => 'Make', 'device_model_text' => 'Model', 'device_size_text' => 'Size', 'device_color_text' => 'Color', 'condition_text' => 'Condition', 'device_carrier_text' => 'Carrier'] as $field => $label)
                                            <th>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm text-white dropdown-toggle p-0 fw-bold text-uppercase" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">{{ $label }}</button>
                                                    <div class="dropdown-menu p-3 cpo-filter-menu">
                                                        <input class="form-control form-control-sm mb-2" placeholder="Search {{ strtolower($label) }}" data-cpo-filter-search>
                                                        @foreach (($filterOptions[$field]['values'] ?? []) as $item)
                                                            <label class="dropdown-item d-flex justify-content-between gap-3">
                                                                <span>
                                                                    <input class="form-check-input me-2" type="checkbox" value="{{ $item['value'] }}" data-cpo-filter-field="{{ $field }}" @checked(in_array($item['value'], (array) request($field, []), true))>
                                                                    <span data-cpo-filter-label>{{ $item['value'] }}</span>
                                                                </span>
                                                                <span class="muted small">{{ $item['count'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </th>
                                        @endforeach
                                        <th>Available</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($devices as $device)
                                        <tr>
                                            <td>{{ $device->manufacturer_text ?: '—' }}</td>
                                            <td>{{ $device->device_model_text ?: '—' }}</td>
                                            <td>{{ $device->device_size_text ?: '—' }}</td>
                                            <td>{{ $device->device_color_text ?: '—' }}</td>
                                            <td>{{ $device->condition_text ?: '—' }}</td>
                                            <td>{{ $device->device_carrier_text ?: '—' }}</td>
                                            <td>{{ number_format($device->availableQuantity()) }} pcs</td>
                                            <td class="cpo-price">{{ $device->displayPrice() !== null ? 'CA$'.number_format($device->displayPrice(), 2) : '—' }}</td>
                                            <td>
                                                <div class="input-group input-group-sm cpo-qty" data-cpo-quantity>
                                                    <button class="btn btn-outline-secondary" type="button" data-cpo-minus>-</button>
                                                    <input class="form-control text-center" form="addDevice{{ $device->id }}" name="quantity" value="1" min="1" max="{{ $device->availableQuantity() }}" type="number">
                                                    <button class="btn btn-outline-secondary" type="button" data-cpo-plus>+</button>
                                                </div>
                                            </td>
                                            <td>
                                                @unless(auth()->user()?->isAdmin())
                                                    <form id="addDevice{{ $device->id }}" method="POST" action="{{ route('cart.devices.store', $device) }}">
                                                        @csrf
                                                        <button class="btn btn-danger btn-sm text-nowrap" type="submit"><i class="bi bi-bag-plus me-1"></i>Add To Cart</button>
                                                    </form>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-center py-5 muted" colspan="10">No available certified pre-owned devices match the selected filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">{{ $devices->links() }}</div>
                </div>

                <div class="col-xl-2">
                    <div class="cpo-cart-box sticky-xl-top" style="top: 92px;">
                        <p class="eyebrow mb-2">Cart Total</p>
                        <p class="h4 fw-bold mb-1">CA${{ number_format($cartSummary['total'], 2) }}</p>
                        <p class="muted small">{{ number_format($cartSummary['count']) }} item{{ $cartSummary['count'] === 1 ? '' : 's' }}</p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-dark" href="{{ route('cart.index') }}"><i class="bi bi-cart me-2"></i>View Cart</a>
                            @auth
                                @if(auth()->user()->isCustomer())
                                    <a class="btn btn-primary" href="{{ route('checkout.show') }}"><i class="bi bi-credit-card me-2"></i>Checkout</a>
                                @endif
                            @else
                                <a class="btn btn-primary" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Checkout</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('cpoFilters');
            if (!form) return;

            document.querySelectorAll('[data-cpo-filter-search]').forEach((input) => {
                input.addEventListener('input', () => {
                    const term = input.value.toLowerCase();
                    input.closest('.dropdown-menu')?.querySelectorAll('.dropdown-item').forEach((item) => {
                        const label = item.querySelector('[data-cpo-filter-label]')?.textContent.toLowerCase() || '';
                        item.classList.toggle('d-none', !label.includes(term));
                    });
                });
            });

            document.querySelectorAll('[data-cpo-filter-field]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const field = checkbox.dataset.cpoFilterField;
                    form.querySelectorAll(`[data-cpo-hidden-filter="${field}"]`).forEach((node) => node.remove());
                    document.querySelectorAll(`[data-cpo-filter-field="${field}"]:checked`).forEach((checked) => {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = `${field}[]`;
                        hidden.value = checked.value;
                        hidden.dataset.cpoHiddenFilter = field;
                        form.appendChild(hidden);
                    });
                    form.submit();
                });
            });

            document.querySelectorAll('[data-cpo-quantity]').forEach((group) => {
                const input = group.querySelector('input');
                group.querySelector('[data-cpo-minus]')?.addEventListener('click', () => {
                    input.value = Math.max(Number(input.min || 1), Number(input.value || 1) - 1);
                });
                group.querySelector('[data-cpo-plus]')?.addEventListener('click', () => {
                    input.value = Math.min(Number(input.max || 1), Number(input.value || 1) + 1);
                });
            });
        })();
    </script>
@endpush
