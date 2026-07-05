@php
    $deviceFilterColumns = [
        'manufacturer_text' => 'Make',
        'device_model_text' => 'Model',
        'device_size_text' => 'Size',
        'device_color_text' => 'Color',
        'condition_text' => 'Condition',
        'device_carrier_text' => 'Carrier',
    ];
@endphp

<form id="cpoBulkCartForm" method="POST" action="{{ route('cart.devices.bulk') }}" data-cpo-bulk-cart>
    @csrf
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="mb-0 text-primary fw-bold">{{ number_format($devices->total()) }} result{{ $devices->total() === 1 ? '' : 's' }}</p>
        <p class="mb-0 muted">Showing {{ number_format($devices->firstItem() ?? 0) }}-{{ number_format($devices->lastItem() ?? 0) }}</p>
    </div>

    <div class="surface p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover cpo-table mb-0">
                {{-- sh:codes - Increase header height and other formatting--}}
                <thead>
                    <tr>
                        @foreach ($deviceFilterColumns as $field => $label)
                            <th>
                                <div class="dropdown" data-cpo-filter-dropdown>
                                    <button class="cpo-th-filter dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false">
                                        <span class="cpo-th-label">{{ $label }}</span>
                                        <span class="cpo-th-arrow"></span>
                                    </button>

                                    <div class="dropdown-menu cpo-filter-menu" data-cpo-filter-menu>
                                        <input class="form-control form-control-sm cpo-filter-search mb-2" placeholder="Search Entire {{ $label }}"
                                            data-cpo-filter-search>

                                        <div class="cpo-filter-options">
                                            @foreach (($filterOptions[$field]['values'] ?? []) as $item)
                                            <label class="dropdown-item cpo-filter-option">
                                                <span class="cpo-filter-option-left">
                                                    <input class="form-check-input cpo-filter-checkbox" type="checkbox" value="{{ $item['value'] }}"
                                                        data-cpo-filter-field="{{ $field }}" @checked(in_array($item['value'], (array) request($field, []),
                                                        true))>
                                                    <span class="cpo-filter-label" data-cpo-filter-label>
                                                        {{ $item['value'] }}
                                                    </span>
                                                </span>
                                                <span class="cpo-filter-count">
                                                    ({{ $item['count'] }})
                                                </span>
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </th>
                        @endforeach
                        <th class="cpo-th-static">Available</th>
                        <th class="cpo-th-static">Price</th>
                        <th class="cpo-th-static">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        @php
                            $available = $device->availableQuantity();
                            $price = $device->displayPrice() ?? 0;
                        @endphp
                        <tr data-cpo-row data-cpo-price="{{ $price }}" data-cpo-available="{{ $available }}">
                            <td>{{ $device->manufacturer_text ?: '--' }}</td>
                            <td>{{ $device->device_model_text ?: '--' }}</td>
                            <td>{{ $device->device_size_text ?: '--' }}</td>
                            <td>{{ $device->device_color_text ?: '--' }}</td>
                            <td>{{ $device->condition_text ?: '--' }}</td>
                            <td>{{ $device->device_carrier_text ?: '--' }}</td>
                            {{-- sh:codes - adding 'pcs' to the qty --}}
                            {{-- <td>{{ number_format($available) }}</td> --}}
                            <td>{{ number_format($available) }} pcs</td>
                            <td class="cpo-price">{{ $device->displayPrice() !== null ? 'CA$'.number_format($device->displayPrice(), 2) : '--' }}</td>

                            {{-- sh:codes - Quantity column formatting --}}
                            {{-- <td>
                                <div class="input-group input-group-sm cpo-qty" data-cpo-quantity>
                                    <button class="btn btn-outline-secondary" type="button" data-cpo-minus @disabled($available <= 0)>-</button>
                                    <input class="form-control text-center" name="devices[{{ $device->id }}]" value="0" min="0" max="{{ $available }}" type="number" inputmode="numeric" data-cpo-qty-input @disabled($available <= 0)>
                                    <button class="btn btn-outline-secondary" type="button" data-cpo-plus @disabled($available <= 0)>+</button>
                                </div>
                            </td> --}}
                            <td class="cpo-qty-cell">
                                <div class="cpo-qty" data-cpo-quantity>
                                    <button class="cpo-qty-btn" type="button" data-cpo-minus @disabled($available <=0)>
                                        <span>-</span>
                                    </button>
                                    <input class="cpo-qty-input" name="devices[{{ $device->id }}]" value="0" min="0" max="{{ $available }}"
                                        type="number" inputmode="numeric" data-cpo-qty-input @disabled($available <=0)>
                                    <button class="cpo-qty-btn" type="button" data-cpo-plus @disabled($available <=0)>
                                        <span>+</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center py-5 muted" colspan="9">No available certified pre-owned devices match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="cpo-action-bar d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
        {{-- sh:codes - Pushed to th bottom --}}
        {{-- <strong class="cpo-total">TOTAL: <span data-cpo-selected-total>CA$0.00</span></strong> --}}
        <div class="d-flex flex-wrap gap-2">
            @auth
                @if(auth()->user()->isCustomer())
                    <button class="btn btn-danger" type="submit"><i class="bi bi-bag-plus me-2"></i>Add To Cart</button>
                    <a class="btn btn-dark" href="{{ route('cart.index') }}"><i class="bi bi-cart me-2"></i>View Cart</a>
                    <a class="btn btn-primary" href="{{ route('checkout.show') }}"><i class="bi bi-credit-card me-2"></i>Checkout</a>
                @endif
            @else
                <button class="btn btn-danger" type="button" data-auth-required data-intended-url="{{ request()->fullUrl() }}"><i class="bi bi-bag-plus me-2"></i>Add To Cart</button>
                <button class="btn btn-dark" type="button" data-auth-required data-intended-url="{{ route('cart.index') }}"><i class="bi bi-cart me-2"></i>View Cart</button>
                <button class="btn btn-primary" type="button" data-auth-required data-intended-url="{{ route('checkout.show') }}"><i class="bi bi-credit-card me-2"></i>Checkout</button>
            @endauth
            <a class="btn btn-outline-primary" href="{{ route('shop.certified-pre-owned-devices.export', request()->query()) }}"><i class="bi bi-download me-2"></i>Export Result CSV</a>
        </div>
        <strong class="cpo-total">TOTAL: <span data-cpo-selected-total>CA$0.00</span></strong>
    </div>

    <div class="mt-4" data-cpo-pagination>{{ $devices->links() }}</div>
</form>
