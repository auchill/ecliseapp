@extends('layouts.app')

@section('title', 'Parts Price Check')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Parts Price Check</p>
            <h1 class="display-5 fw-bold mb-3">Verify repair part prices.</h1>
            <p class="fs-5 mb-0">Parts are shown for price verification only. Please contact us for repair service availability.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <form class="surface p-4 mb-4" method="GET" action="{{ route('parts.index') }}" data-parts-search-form data-search-url="{{ route('parts.search') }}" data-suggestions-url="{{ route('parts.suggestions') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 position-relative">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Screen, battery, model, SKU" autocomplete="off" data-parts-autocomplete>
                        <div class="list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 20;" data-parts-suggestions></div>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="brand">Brand</label>
                        <select class="form-select" id="brand" name="brand">
                            <option value="">All</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="model">Model</label>
                        <input class="form-control" id="model" name="model" value="{{ request('model') }}">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="device_type">Device</label>
                        <select class="form-select" id="device_type" name="device_type">
                            <option value="">All</option>
                            @foreach ($deviceTypes as $deviceType)
                                <option value="{{ $deviceType }}" @selected(request('device_type') === $deviceType)>{{ $deviceType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="stock">Stock</label>
                        <select class="form-select" id="stock" name="stock">
                            <option value="">All</option>
                            <option value="in" @selected(request('stock') === 'in')>In</option>
                            <option value="out" @selected(request('stock') === 'out')>Out</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="min_price">Min</label>
                        <input class="form-control" id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ request('min_price') }}">
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label class="form-label" for="max_price">Max</label>
                        <input class="form-control" id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ request('max_price') }}">
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <button class="btn btn-outline-primary w-100" type="button" data-parts-clear><i class="bi bi-x-lg"></i><span class="visually-hidden">Clear</span></button>
                    </div>
                </div>
            </form>

            <div class="alert alert-info border-0">Parts are shown for price verification only. Please contact us for repair service availability.</div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <p class="mb-0 muted"><span data-parts-count>{{ number_format($parts->total()) }}</span> result{{ $parts->total() === 1 ? '' : 's' }}</p>
                <p class="mb-0 muted d-none" data-parts-loading>Searching...</p>
            </div>

            <div data-parts-results>
                @include('parts.partials.results', ['parts' => $parts])
            </div>
        </div>
    </section>
    <script>
        (() => {
            const form = document.querySelector('[data-parts-search-form]');
            if (!form || form.dataset.bound === '1') return;
            form.dataset.bound = '1';

            const results = document.querySelector('[data-parts-results]');
            const count = document.querySelector('[data-parts-count]');
            const loading = document.querySelector('[data-parts-loading]');
            const suggestions = document.querySelector('[data-parts-suggestions]');
            const autocomplete = document.querySelector('[data-parts-autocomplete]');
            let searchController;
            let suggestController;
            let searchTimer;
            let suggestTimer;

            const formParams = () => new URLSearchParams(new FormData(form));
            const ajaxUrl = (url = null) => {
                if (url) {
                    const parsed = new URL(url, window.location.origin);
                    const target = new URL(form.dataset.searchUrl, window.location.origin);
                    target.search = parsed.search;
                    return target;
                }

                const target = new URL(form.dataset.searchUrl, window.location.origin);
                target.search = formParams().toString();
                return target;
            };

            const setLoading = (active) => loading?.classList.toggle('d-none', !active);
            const hideSuggestions = () => suggestions?.classList.add('d-none');

            const runSearch = (url) => {
                searchController?.abort();
                searchController = new AbortController();
                const target = ajaxUrl(url);
                setLoading(true);

                fetch(target, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: searchController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((payload) => {
                        results.innerHTML = payload.html;
                        if (count) count.textContent = new Intl.NumberFormat().format(payload.count || 0);
                    })
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            results.innerHTML = '<div class="surface p-4">Search failed. Please try again.</div>';
                        }
                    })
                    .finally(() => setLoading(false));
            };

            const scheduleSearch = () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => runSearch(), 300);
            };

            const renderSuggestions = (items) => {
                if (!suggestions) return;
                suggestions.innerHTML = '';
                if (!items.length) {
                    hideSuggestions();
                    return;
                }
                items.slice(0, 10).forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.textContent = `${item.label} (${item.type})`;
                    button.addEventListener('click', () => {
                        const field = form.elements[item.field];
                        if (field) field.value = item.value;
                        hideSuggestions();
                        runSearch();
                    });
                    suggestions.appendChild(button);
                });
                suggestions.classList.remove('d-none');
            };

            const scheduleSuggestions = () => {
                const term = autocomplete?.value || '';
                window.clearTimeout(suggestTimer);
                if (term.length < 2) {
                    hideSuggestions();
                    return;
                }
                suggestTimer = window.setTimeout(() => {
                    suggestController?.abort();
                    suggestController = new AbortController();
                    const url = new URL(form.dataset.suggestionsUrl, window.location.origin);
                    url.searchParams.set('q', term);
                    fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: suggestController.signal,
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject())
                        .then((payload) => renderSuggestions(payload.suggestions || []))
                        .catch((error) => {
                            if (error.name !== 'AbortError') hideSuggestions();
                        });
                }, 250);
            };

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                hideSuggestions();
                runSearch();
            });
            form.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('input', () => {
                    scheduleSearch();
                    if (field === autocomplete) scheduleSuggestions();
                });
                field.addEventListener('change', scheduleSearch);
            });
            document.querySelector('[data-parts-clear]')?.addEventListener('click', () => {
                form.reset();
                hideSuggestions();
                runSearch();
            });
            results.addEventListener('click', (event) => {
                const link = event.target.closest('[data-parts-pagination] a');
                if (!link) return;
                event.preventDefault();
                runSearch(link.href);
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-parts-search-form]')) hideSuggestions();
            });
        })();
    </script>
@endsection
