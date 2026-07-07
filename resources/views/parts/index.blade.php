@extends('layouts.app')

@section('title', 'Parts')

@section('content')
    <section class="parts-menu-page bg-white">
        <div class="container">
            <div class="parts-menu-header">
                <p class="eyebrow mb-2">Parts</p>
                <h1>Find repair parts by device family.</h1>
                <p>Browse MobileSentrix categories and verify repair part pricing from the synced Eclise parts catalog.</p>
            </div>

            <div class="parts-browser"
                data-parts-menu-browser
                data-menu-url="{{ route('parts.menu') }}"
                data-search-url="{{ route('parts.search') }}">
                <div class="parts-menu-search-wrap">
                    <label class="visually-hidden" for="parts-menu-search">Search by model or model number</label>
                    <div class="parts-menu-search">
                        <input id="parts-menu-search" type="search" placeholder="Search by model or model number" autocomplete="off" data-parts-menu-search>
                        <button type="button" aria-label="Search parts" data-parts-menu-search-button>
                            <i class="bi bi-search" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="parts-menu-search-results d-none" data-parts-menu-search-results></div>
                </div>

                <div class="parts-browser-layout">
                    <aside class="parts-menu-sidebar" aria-label="Parts menu">
                        <div class="parts-menu-sidebar-title">Parts Menu</div>
                        <div class="parts-menu-list" data-parts-menu-list>
                            @forelse ($mainMenu as $item)
                                <button class="parts-menu-item" type="button" data-parts-menu-item data-category-id="{{ $item['id'] }}">
                                    <span>{{ $item['name'] }}</span>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </button>
                            @empty
                                <div class="parts-menu-empty">No active parts categories are available.</div>
                            @endforelse
                        </div>
                    </aside>

                    <section class="parts-menu-content" aria-live="polite">
                        <div class="parts-menu-content-head">
                            <div>
                                <p class="parts-menu-kicker mb-1" data-parts-menu-kicker>Browse categories</p>
                                <h2 data-parts-menu-title>Select a parts category</h2>
                            </div>
                            <p class="parts-menu-count mb-0 d-none" data-parts-menu-count></p>
                        </div>

                        <div class="parts-menu-loading d-none" data-parts-menu-loading>
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <span>Loading...</span>
                        </div>

                        <div class="parts-menu-content-body" data-parts-menu-content>
                            <div class="parts-menu-empty">Choose a category from the left to browse subcategories or parts.</div>
                        </div>

                        <div class="parts-menu-more d-none" data-parts-menu-more-wrap>
                            <button class="btn btn-outline-primary" type="button" data-parts-menu-load-more>
                                Load More
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const browser = document.querySelector('[data-parts-menu-browser]');
            if (!browser || browser.dataset.bound === '1') return;
            browser.dataset.bound = '1';

            const menuList = browser.querySelector('[data-parts-menu-list]');
            const content = browser.querySelector('[data-parts-menu-content]');
            const title = browser.querySelector('[data-parts-menu-title]');
            const kicker = browser.querySelector('[data-parts-menu-kicker]');
            const count = browser.querySelector('[data-parts-menu-count]');
            const loading = browser.querySelector('[data-parts-menu-loading]');
            const moreWrap = browser.querySelector('[data-parts-menu-more-wrap]');
            const loadMore = browser.querySelector('[data-parts-menu-load-more]');
            const searchInput = browser.querySelector('[data-parts-menu-search]');
            const searchButton = browser.querySelector('[data-parts-menu-search-button]');
            const searchResults = browser.querySelector('[data-parts-menu-search-results]');
            const categoryCache = new Map();

            let currentCategory = null;
            let nextPartsUrl = null;
            let childrenController = null;
            let partsController = null;
            let searchController = null;
            let searchTimer = null;

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const initialMenu = @json($mainMenu);

            initialMenu.forEach((item) => categoryCache.set(Number(item.id), item));

            const setLoading = (active) => loading?.classList.toggle('d-none', !active);
            const hideMore = () => moreWrap?.classList.add('d-none');
            const showMore = () => moreWrap?.classList.remove('d-none');
            const hideSearchResults = () => searchResults?.classList.add('d-none');

            const emptyState = (message) => {
                content.innerHTML = `<div class="parts-menu-empty">${message}</div>`;
                hideMore();
                count?.classList.add('d-none');
            };

            const setActiveSidebar = (categoryId) => {
                menuList?.querySelectorAll('[data-parts-menu-item]').forEach((button) => {
                    button.classList.toggle('active', Number(button.dataset.categoryId) === Number(categoryId));
                });
            };

            const renderCategories = (categories) => {
                if (!categories.length) {
                    emptyState('No subcategories found.');
                    return;
                }

                content.innerHTML = '<div class="parts-category-grid"></div>';
                const grid = content.querySelector('.parts-category-grid');

                categories.forEach((category) => {
                    categoryCache.set(Number(category.id), category);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'parts-category-card';
                    button.dataset.categoryId = category.id;
                    button.innerHTML = `
                        ${category.image_url ? `<span class="parts-category-image"><img src="${escapeHtml(category.image_url)}" alt=""></span>` : '<span class="parts-category-icon"><i class="bi bi-cpu" aria-hidden="true"></i></span>'}
                        <span class="parts-category-name">${escapeHtml(category.name)}</span>
                        <span class="parts-category-meta">${category.has_children ? 'Browse models' : 'View parts'}</span>
                    `;
                    button.addEventListener('click', () => selectCategory(category));
                    grid.appendChild(button);
                });

                hideMore();
                count?.classList.add('d-none');
            };

            const loadParts = (category, url = null, append = false) => {
                partsController?.abort();
                partsController = new AbortController();
                setLoading(true);

                const target = new URL(url || category.parts_url, window.location.origin);
                if (!target.searchParams.has('per_page')) target.searchParams.set('per_page', '24');

                return fetch(target, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: partsController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((payload) => {
                        if (!append) {
                            content.innerHTML = '<div class="parts-menu-grid" data-parts-menu-grid></div>';
                        }

                        const grid = content.querySelector('[data-parts-menu-grid]');
                        grid.insertAdjacentHTML('beforeend', payload.html || '');
                        nextPartsUrl = payload.next_page_url;

                        if (payload.total > 0) {
                            count.textContent = `${new Intl.NumberFormat().format(payload.total)} part${payload.total === 1 ? '' : 's'}`;
                            count.classList.remove('d-none');
                        } else {
                            count.classList.add('d-none');
                        }

                        payload.has_more ? showMore() : hideMore();
                    })
                    .catch((error) => {
                        if (error?.name !== 'AbortError') {
                            emptyState('No parts found for this category.');
                        }
                    })
                    .finally(() => setLoading(false));
            };

            const selectCategory = (category) => {
                currentCategory = category;
                setActiveSidebar(category.id);
                title.textContent = category.name;
                kicker.textContent = category.has_children ? 'Browse subcategories' : 'Browse parts';
                emptyState('');
                setLoading(true);
                hideSearchResults();

                childrenController?.abort();
                childrenController = new AbortController();

                fetch(category.children_url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: childrenController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((payload) => {
                        const children = payload.children || [];

                        if (children.length) {
                            renderCategories(children);
                            return;
                        }

                        return loadParts(category);
                    })
                    .catch((error) => {
                        if (error?.name !== 'AbortError') {
                            emptyState('No subcategories found.');
                        }
                    })
                    .finally(() => setLoading(false));
            };

            const renderSearchResults = (payload) => {
                const categories = payload.categories || [];
                const parts = payload.parts || [];
                searchResults.innerHTML = '';

                if (!categories.length && !parts.length) {
                    searchResults.innerHTML = '<div class="parts-menu-search-empty">No search results found.</div>';
                    searchResults.classList.remove('d-none');
                    return;
                }

                if (categories.length) {
                    const categoryGroup = document.createElement('div');
                    categoryGroup.className = 'parts-menu-search-group';
                    categoryGroup.innerHTML = '<h3>Categories</h3>';
                    categories.forEach((category) => {
                        categoryCache.set(Number(category.id), category);
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'parts-menu-search-row';
                        button.innerHTML = `<span>${escapeHtml(category.name)}</span><small>${category.has_children ? 'Category' : 'Parts category'}</small>`;
                        button.addEventListener('click', () => selectCategory(category));
                        categoryGroup.appendChild(button);
                    });
                    searchResults.appendChild(categoryGroup);
                }

                if (parts.length) {
                    const partGroup = document.createElement('div');
                    partGroup.className = 'parts-menu-search-group';
                    partGroup.innerHTML = '<h3>Parts</h3>';
                    parts.forEach((part) => {
                        const link = document.createElement('a');
                        link.className = 'parts-menu-search-row parts-menu-search-part';
                        link.href = part.url;
                        link.innerHTML = `
                            <img src="${escapeHtml(part.image_url)}" alt="">
                            <span>
                                <strong>${escapeHtml(part.name)}</strong>
                                <small>${escapeHtml(part.sku || 'No SKU')}${part.model ? ` &middot; ${escapeHtml(part.model)}` : ''}</small>
                            </span>
                            <b>$${escapeHtml(part.price)}</b>
                        `;
                        partGroup.appendChild(link);
                    });
                    searchResults.appendChild(partGroup);
                }

                searchResults.classList.remove('d-none');
            };

            const runSearch = () => {
                const term = (searchInput?.value || '').trim();

                if (term.length < 2) {
                    hideSearchResults();
                    return;
                }

                searchController?.abort();
                searchController = new AbortController();
                const target = new URL(browser.dataset.searchUrl, window.location.origin);
                target.searchParams.set('q', term);

                fetch(target, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: searchController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then(renderSearchResults)
                    .catch((error) => {
                        if (error?.name !== 'AbortError') hideSearchResults();
                    });
            };

            const scheduleSearch = () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(runSearch, 350);
            };

            menuList?.querySelectorAll('[data-parts-menu-item]').forEach((button) => {
                button.addEventListener('click', () => {
                    const category = categoryCache.get(Number(button.dataset.categoryId));

                    if (category) {
                        selectCategory(category);
                    }
                });
            });

            loadMore?.addEventListener('click', () => {
                if (currentCategory && nextPartsUrl) {
                    loadParts(currentCategory, nextPartsUrl, true);
                }
            });

            searchInput?.addEventListener('input', scheduleSearch);
            searchButton?.addEventListener('click', runSearch);
            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-parts-menu-browser]')) hideSearchResults();
            });

            if (initialMenu.length) {
                selectCategory(initialMenu[0]);
            }
        })();
    </script>
@endpush
