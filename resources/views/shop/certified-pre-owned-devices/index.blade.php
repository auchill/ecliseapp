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
        .cpo-action-bar { border: 2px solid #071d3a; border-radius: 8px; padding: 1rem; background: #fff; }
        .cpo-total { color: #071d3a; font-size: 1.1rem; }
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

            <div data-cpo-selected-chips>
                @include('shop.certified-pre-owned-devices.partials.chips', ['selectedChips' => $selectedChips])
            </div>

            <div data-cpo-results>
                @include('shop.certified-pre-owned-devices.partials.table', ['devices' => $devices, 'filterOptions' => $filterOptions])
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('cpoFilters');
            if (!form) return;
            const results = document.querySelector('[data-cpo-results]');
            const selectedChips = document.querySelector('[data-cpo-selected-chips]');
            const currency = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });
            let searchController;

            const formParams = () => new URLSearchParams(new FormData(form));
            const targetUrl = (url = null) => {
                if (url) {
                    const parsed = new URL(url, window.location.origin);
                    const target = new URL(form.action, window.location.origin);
                    target.search = parsed.search;
                    return target;
                }

                const target = new URL(form.action, window.location.origin);
                target.search = formParams().toString();
                return target;
            };

            const bindFilterMenus = () => {
                document.querySelectorAll('[data-cpo-filter-menu]').forEach((menu) => {
                    menu.addEventListener('click', (event) => event.stopPropagation());
                });

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
                        runSearch();
                    });
                });
            };

            const updateSelectedTotal = () => {
                let total = 0;
                document.querySelectorAll('[data-cpo-row]').forEach((row) => {
                    const input = row.querySelector('[data-cpo-qty-input]');
                    total += Number(row.dataset.cpoPrice || 0) * Number(input?.value || 0);
                });
                document.querySelector('[data-cpo-selected-total]').textContent = currency.format(total).replace('$', 'CA$');
            };

            const bindQuantities = () => {
                document.querySelectorAll('[data-cpo-quantity]').forEach((group) => {
                    const input = group.querySelector('[data-cpo-qty-input]');
                    const normalize = () => {
                        input.value = Math.min(Number(input.max || 0), Math.max(Number(input.min || 0), Number(input.value || 0)));
                        updateSelectedTotal();
                    };
                    group.querySelector('[data-cpo-minus]')?.addEventListener('click', () => {
                        input.value = Math.max(Number(input.min || 0), Number(input.value || 0) - 1);
                        updateSelectedTotal();
                    });
                    group.querySelector('[data-cpo-plus]')?.addEventListener('click', () => {
                        input.value = Math.min(Number(input.max || 0), Number(input.value || 0) + 1);
                        updateSelectedTotal();
                    });
                    input?.addEventListener('input', normalize);
                    input?.addEventListener('change', normalize);
                });
                updateSelectedTotal();
            };

            const bindPagination = () => {
                document.querySelector('[data-cpo-pagination]')?.addEventListener('click', (event) => {
                    const link = event.target.closest('a');
                    if (!link) return;
                    event.preventDefault();
                    runSearch(link.href);
                });
            };

            const bindResults = () => {
                bindFilterMenus();
                bindQuantities();
                bindPagination();
            };

            const runSearch = (url = null) => {
                searchController?.abort();
                searchController = new AbortController();
                const target = targetUrl(url);

                fetch(target, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: searchController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((payload) => {
                        results.innerHTML = payload.html;
                        if (selectedChips) selectedChips.innerHTML = payload.chips_html || '';
                        window.history.replaceState({}, '', target);
                        bindResults();
                    })
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            results.innerHTML = '<div class="surface p-4">Device search failed. Please try again.</div>';
                        }
                    });
            };

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                runSearch();
            });

            form.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('change', () => runSearch());
                if (field.tagName === 'INPUT') {
                    let timer;
                    field.addEventListener('input', () => {
                        window.clearTimeout(timer);
                        timer = window.setTimeout(() => runSearch(), 300);
                    });
                }
            });

            bindResults();

            document.addEventListener('auth-modal-ready', bindResults);
        })();
    </script>
@endpush
