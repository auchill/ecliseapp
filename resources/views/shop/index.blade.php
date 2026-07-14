@extends('layouts.app')

@section('title', 'Shop')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Shop</p>
            <h1 class="display-5 fw-bold mb-3">Phones, computers, and accessories.</h1>
            <p class="fs-5 mb-0">Browse used, new, and refurbished devices.</p>
        </div>
    </section>

    <section class="section-pad bg-white" data-shop-products data-shop-index-url="{{ route('shop.index') }}">
        <div class="container">
            <div class="d-flex d-lg-none justify-content-between align-items-center gap-2 mb-3">
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#shopFilters" aria-controls="shopFilters">
                    <i class="bi bi-funnel me-2"></i>Filters
                    <span class="badge text-bg-primary ms-1 {{ $activeFilterCount > 0 ? '' : 'd-none' }}" data-shop-active-filter-count>{{ $activeFilterCount }}</span>
                </button>
                <button class="btn btn-outline-secondary" type="button" data-shop-clear>Clear</button>
            </div>

            <div class="row g-4 align-items-start">
                <aside class="col-lg-3 d-none d-lg-block">
                    <form class="surface p-3 shop-filter-sidebar" method="GET" action="{{ route('shop.index') }}" data-shop-filter-form>
                        <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}" data-shop-sort-hidden>
                        <div data-shop-desktop-filters>
                            @include('shop.partials.filter-panel', ['idPrefix' => 'desktop'])
                        </div>
                    </form>
                </aside>

                <section class="col-lg-9">
                    <form class="surface p-3 mb-3" method="GET" action="{{ route('shop.index') }}" data-shop-sort-form>
                        @foreach (request()->except(['sort', 'page']) as $key => $value)
                            @if (is_array($value))
                                @foreach ($value as $arrayValue)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $arrayValue }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div data-shop-summary aria-live="polite">
                                @include('shop.products.partials.summary')
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0" for="sort">Sort</label>
                                <select class="form-select" id="sort" name="sort" data-shop-sort>
                                    @foreach ($sortOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>

                    <div class="shop-filter-status small muted mb-3 d-none" data-shop-loading-status role="status" aria-live="polite">
                        Updating products...
                    </div>
                    <div class="shop-filter-notice small muted mb-3 d-none" data-shop-notice role="status" aria-live="polite"></div>

                    <div data-shop-active-filters>
                        @include('shop.products.partials.active-filters')
                    </div>

                    <div data-shop-results aria-live="polite">
                        @include('shop.products.partials.results')
                    </div>
                </section>
            </div>
        </div>
    </section>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="shopFilters" aria-labelledby="shopFiltersLabel">
        <div class="offcanvas-header">
            <h2 class="h5 mb-0" id="shopFiltersLabel">Filters</h2>
            <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="GET" action="{{ route('shop.index') }}" data-shop-filter-form data-shop-mobile-form>
                <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}" data-shop-sort-hidden>
                <div data-shop-mobile-filters>
                    @include('shop.partials.filter-panel', ['idPrefix' => 'mobile'])
                </div>
                <div class="d-grid mt-3">
                    <button class="btn btn-primary" type="button" data-bs-dismiss="offcanvas">View Results</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-shop-products]');
            if (!root || root.dataset.bound === '1') return;
            root.dataset.bound = '1';

            const indexUrl = root.dataset.shopIndexUrl;
            const debounceMs = 350;
            let controller;
            let sequence = 0;
            let timer;

            const selectors = {
                desktopFilters: '[data-shop-desktop-filters]',
                mobileFilters: '[data-shop-mobile-filters]',
                desktopFilterGroups: '[data-shop-desktop-filters] [data-shop-filter-groups]',
                mobileFilterGroups: '[data-shop-mobile-filters] [data-shop-filter-groups]',
                results: '[data-shop-results]',
                summary: '[data-shop-summary]',
                activeFilters: '[data-shop-active-filters]',
                loading: '[data-shop-loading-status]',
                notice: '[data-shop-notice]',
                activeCount: '[data-shop-active-filter-count]',
                sort: '[data-shop-sort]',
            };

            const visibleFilterForm = () => {
                const mobileForm = document.querySelector('[data-shop-mobile-form]');
                if (mobileForm?.closest('.offcanvas.show')) return mobileForm;

                return document.querySelector('[data-shop-filter-form]');
            };

            const cleanParams = (form) => {
                const params = new URLSearchParams();
                const data = new FormData(form || visibleFilterForm());

                for (const [key, value] of data.entries()) {
                    const normalizedValue = String(value).trim();
                    if (normalizedValue === '') continue;

                    params.append(key, normalizedValue);
                }

                const sort = document.querySelector(selectors.sort)?.value || 'newest';
                params.delete('sort');
                if (sort !== 'newest') {
                    params.set('sort', sort);
                }

                return params;
            };

            const urlForForm = (form) => {
                const url = new URL(indexUrl, window.location.origin);
                url.search = cleanParams(form).toString();

                return url;
            };

            const focusState = () => {
                const element = document.activeElement;

                if (!element?.id) {
                    return null;
                }

                return {
                    id: element.id,
                    start: element.selectionStart,
                    end: element.selectionEnd,
                    value: element.value,
                };
            };

            const restoreFocus = (state) => {
                if (!state?.id) return;

                const element = document.getElementById(state.id);
                if (!element) return;

                if (state.value !== undefined) {
                    element.value = state.value;
                }

                element.focus({ preventScroll: true });
                if (typeof element.setSelectionRange === 'function') {
                    const cursor = state.end ?? element.value.length;
                    element.setSelectionRange(cursor, cursor);
                }
            };

            const setLoading = (active) => {
                root.classList.toggle('shop-products-loading', active);
                document.querySelector(selectors.loading)?.classList.toggle('d-none', !active);
            };

            const clearProductFilterNotice = () => {
                const notice = document.querySelector(selectors.notice);
                if (!notice) return;

                window.clearTimeout(notice.hideTimer);
                notice.textContent = '';
                notice.classList.add('d-none');
            };

            const showTransientNotice = (message) => {
                const notice = document.querySelector(selectors.notice);
                if (!notice) return;

                notice.textContent = message;
                notice.classList.remove('d-none');
                window.clearTimeout(notice.hideTimer);
                notice.hideTimer = window.setTimeout(() => clearProductFilterNotice(), 4000);
            };

            const cancelPendingRequest = () => {
                controller?.abort();
                controller = null;
                sequence++;
                setLoading(false);
            };

            const updateActiveCount = (count) => {
                document.querySelectorAll(selectors.activeCount).forEach((badge) => {
                    badge.textContent = count;
                    badge.classList.toggle('d-none', Number(count) <= 0);
                });
            };

            const syncSortHidden = () => {
                const sort = document.querySelector(selectors.sort)?.value || 'newest';
                document.querySelectorAll('[data-shop-sort-hidden]').forEach((input) => {
                    input.value = sort;
                });
            };

            const paramValues = (params, key) => {
                const values = [
                    ...params.getAll(key),
                    ...params.getAll(`${key}[]`),
                ];

                for (const [paramKey, value] of params.entries()) {
                    if (paramKey.startsWith(`${key}[`)) {
                        values.push(value);
                    }
                }

                return [...new Set(values.filter((value) => value !== ''))];
            };

            const syncFormsFromParams = (params, focus = null) => {
                const activeId = focus?.id || null;
                const sort = params.get('sort') || 'newest';
                const category = params.get('category') || '';
                const search = params.get('q') || params.get('search') || '';

                document.querySelectorAll(selectors.sort).forEach((select) => {
                    select.value = sort;
                });

                document.querySelectorAll('[data-shop-filter-form]').forEach((form) => {
                    ['q', 'min_price', 'max_price'].forEach((name) => {
                        const input = form.querySelector(`[name="${name}"]`);
                        if (!input || input.id === activeId) return;
                        input.value = name === 'q' ? search : (params.get(name) || '');
                    });

                    const categoryInput = form.querySelector('[data-shop-category-input]');
                    if (categoryInput) {
                        categoryInput.value = category;
                    }

                    form.querySelectorAll('[data-shop-category]').forEach((button) => {
                        const active = (button.dataset.shopCategory || '') === category;
                        button.classList.toggle('active', active);
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });

                    ['brands', 'conditions', 'grades', 'colors'].forEach((key) => {
                        const selected = paramValues(params, key);
                        form.querySelectorAll(`input[name="${key}[]"]`).forEach((input) => {
                            input.checked = selected.includes(input.value);
                        });
                    });
                });

                syncSortHidden();
            };

            const fetchProductFilters = async (url, signal) => {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal,
                });
                const contentType = response.headers.get('content-type') || '';

                if (!response.ok) {
                    let errorPayload = null;

                    if (contentType.includes('application/json')) {
                        errorPayload = await response.json().catch(() => null);
                    } else {
                        errorPayload = await response.text().catch(() => null);
                    }

                    const requestError = new Error(
                        errorPayload?.message || `Product filter request failed with status ${response.status}.`
                    );
                    requestError.status = response.status;
                    requestError.payload = errorPayload;

                    throw requestError;
                }

                if (!contentType.includes('application/json')) {
                    const responseText = await response.text();
                    const contentTypeError = new Error('Product filter endpoint returned a non-JSON response.');
                    contentTypeError.responseText = responseText;

                    throw contentTypeError;
                }

                return response.json();
            };

            const validateFilterResponse = (payload) => {
                if (!payload || payload.success !== true) {
                    throw new Error(payload?.message || 'Invalid product-filter response.');
                }

                return payload;
            };

            const updateHtmlSection = (selector, html, label, required = false) => {
                if (typeof html !== 'string') {
                    if (required) {
                        throw new Error(`Product response is missing ${label}.`);
                    }

                    console.warn(`Product response did not include ${label}; existing content was preserved.`);
                    return false;
                }

                const element = document.querySelector(selector);
                if (!element) {
                    if (required) {
                        throw new Error(`Product page container was not found for ${label}.`);
                    }

                    console.warn(`Product page container was not found for ${label}; existing content was preserved.`);
                    return false;
                }

                element.innerHTML = html;

                return true;
            };

            const applyPayload = (payload, focus, url) => {
                const html = payload.html || {};
                const productsUpdated = updateHtmlSection(selectors.results, html.products ?? payload.results, 'product results', !document.querySelector(selectors.results)?.innerHTML.trim());

                updateHtmlSection(selectors.summary, html.summary ?? payload.summary, 'result summary');
                updateHtmlSection(selectors.activeFilters, html.active_filters ?? payload.activeFilters, 'active filter chips');
                updateHtmlSection(selectors.desktopFilterGroups, html.desktop_filter_groups ?? payload.desktopFilterGroups, 'desktop filter options');
                updateHtmlSection(selectors.mobileFilterGroups, html.mobile_filter_groups ?? payload.mobileFilterGroups, 'mobile filter options');
                updateActiveCount(payload.meta?.active_filter_count ?? payload.activeFilterCount ?? 0);
                syncFormsFromParams(new URL(url, window.location.origin).searchParams, focus);
                restoreFocus(focus);

                if (productsUpdated) {
                    clearProductFilterNotice();
                }
            };

            const currentResultsAreUsable = () => {
                const results = document.querySelector(selectors.results);

                return Boolean(results?.innerHTML.trim());
            };

            const handleNonFatalFilterError = (error) => {
                if (error.name === 'AbortError') return;

                console.error('Non-fatal product filter refresh failed:', error);
            };

            const handleFatalProductLoadError = (error) => {
                if (error.name === 'AbortError') return;

                console.error('Fatal product load error:', error);
                showTransientNotice('Products are temporarily unavailable. Please refresh the page.');
            };

            const loadUrl = async (targetUrl, push = true, focus = null) => {
                const url = new URL(targetUrl, window.location.origin);
                controller?.abort();
                controller = new AbortController();
                const requestSequence = ++sequence;

                setLoading(true);
                clearProductFilterNotice();

                try {
                    const payload = validateFilterResponse(await fetchProductFilters(url, controller.signal));
                    if (requestSequence !== sequence) return;

                    applyPayload(payload, focus, url);
                    if (push) {
                        window.history.pushState({}, '', url);
                    } else {
                        window.history.replaceState({}, '', url);
                    }
                } catch (error) {
                    if (currentResultsAreUsable()) {
                        handleNonFatalFilterError(error);
                    } else {
                        handleFatalProductLoadError(error);
                    }
                } finally {
                    if (requestSequence === sequence) {
                        setLoading(false);
                    }
                }
            };

            const runFromForm = (form, push = true) => {
                syncSortHidden();
                loadUrl(urlForForm(form), push, focusState());
            };

            const scheduleFromForm = (form) => {
                cancelPendingRequest();
                window.clearTimeout(timer);
                timer = window.setTimeout(() => runFromForm(form), debounceMs);
            };

            document.addEventListener('click', (event) => {
                const category = event.target.closest('[data-shop-category]');
                if (category) {
                    const form = category.closest('form');
                    const input = form.querySelector('[data-shop-category-input]');
                    input.value = category.dataset.shopCategory || '';
                    runFromForm(form);
                    return;
                }

                const chip = event.target.closest('[data-shop-chip-url]');
                if (chip) {
                    loadUrl(chip.dataset.shopChipUrl);
                    return;
                }

                const clear = event.target.closest('[data-shop-clear]');
                if (clear) {
                    loadUrl(indexUrl);
                    return;
                }

                const pagination = event.target.closest('[data-shop-pagination] a');
                if (pagination) {
                    event.preventDefault();
                    loadUrl(pagination.href);
                }
            });

            document.addEventListener('change', (event) => {
                if (event.target.matches('[data-shop-sort]')) {
                    runFromForm(visibleFilterForm());
                    return;
                }

                const form = event.target.closest('[data-shop-filter-form]');
                if (form && event.target.matches('input[type="checkbox"]')) {
                    runFromForm(form);
                }
            });

            document.addEventListener('input', (event) => {
                const form = event.target.closest('[data-shop-filter-form]');
                if (!form) return;

                if (event.target.matches('[data-shop-search]')) {
                    cancelPendingRequest();
                    const value = event.target.value.trim();
                    if (value.length === 0 || value.length >= 2) {
                        scheduleFromForm(form);
                    }
                    return;
                }

                if (event.target.matches('input[name="min_price"], input[name="max_price"]')) {
                    scheduleFromForm(form);
                }
            });

            document.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-shop-filter-form], [data-shop-sort-form]');
                if (!form) return;

                event.preventDefault();
                runFromForm(form);
            });

            window.addEventListener('popstate', () => loadUrl(window.location.href, false));
        })();
    </script>
@endpush
