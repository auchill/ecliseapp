@if ($conversation)
    <div class="surface p-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <p class="eyebrow mb-1">Repair Agreement</p>
                <h2 class="h4 fw-bold mb-1">Customer proposal and messaging</h2>
                <p class="muted mb-0">Status: {{ $conversation->statusLabel() }} &middot; Proposal version {{ $conversation->proposal_version }}</p>
            </div>
            <form method="POST" action="{{ route('admin.repairs.conversation.proposal.store', $repair) }}">
                @csrf
                <button class="btn btn-primary" type="submit"><i class="bi bi-send me-2"></i>Send Proposal</button>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <form class="border rounded p-3 mb-3" method="POST" action="{{ route('admin.repairs.conversation.charges.update', $repair) }}">
                    @csrf
                    @method('PATCH')
                    <h3 class="h6 fw-bold">Pricing</h3>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="labour_amount">Labour</label>
                            <input class="form-control" id="labour_amount" name="labour_amount" type="number" min="0" step="0.01" value="{{ old('labour_amount', $conversation->labour_amount) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="diagnostic_fee">Diagnostic</label>
                            <input class="form-control" id="diagnostic_fee" name="diagnostic_fee" type="number" min="0" step="0.01" value="{{ old('diagnostic_fee', $conversation->diagnostic_fee) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="service_fee">Service fee</label>
                            <input class="form-control" id="service_fee" name="service_fee" type="number" min="0" step="0.01" value="{{ old('service_fee', $conversation->service_fee) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="discount_amount">Discount</label>
                            <input class="form-control" id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="{{ old('discount_amount', $conversation->discount_amount) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="tax_amount">Tax</label>
                            <input class="form-control" id="tax_amount" name="tax_amount" type="number" min="0" step="0.01" value="{{ old('tax_amount', $conversation->tax_amount) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Selected parts</label>
                            <div class="form-control bg-light">${{ number_format((float) $conversation->selected_parts_subtotal, 2) }}</div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">Agreement total: <strong>${{ number_format((float) $conversation->final_total, 2) }}</strong></div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-outline-primary" type="submit">Update Pricing</button>
                        </div>
                    </div>
                </form>

                <form class="border rounded p-3" method="POST" action="{{ route('admin.repairs.conversation.messages.store', $repair) }}">
                    @csrf
                    <h3 class="h6 fw-bold">Add Message</h3>
                    <label class="form-label" for="conversation_message">Message</label>
                    <textarea class="form-control mb-2" id="conversation_message" name="message" rows="4" required>{{ old('message') }}</textarea>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="is_internal" name="is_internal" type="checkbox" value="1">
                        <label class="form-check-label" for="is_internal">Internal note only</label>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Send Message</button>
                </form>
            </div>

            <div class="col-lg-7">
                <div class="border rounded p-3 mb-3">
                    <h3 class="h6 fw-bold">Required Part Groups</h3>
                    <form class="row g-2 mb-3" method="POST" action="{{ route('admin.repairs.conversation.part-groups.store', $repair) }}">
                        @csrf
                        <div class="col-md-6">
                            <input class="form-control" name="title" placeholder="Group title, e.g. Display assembly" required>
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" name="sort_order" type="number" min="0" value="0" placeholder="Sort">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100" type="submit">Add Group</button>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" name="description" rows="2" placeholder="Optional description"></textarea>
                        </div>
                    </form>

                    @forelse ($conversation->partGroups as $group)
                        <div class="border-top pt-3 mt-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <h4 class="h6 fw-bold mb-1">{{ $group->title }}</h4>
                                    <p class="small muted mb-2">{{ $group->description ?: 'No description.' }}</p>
                                </div>
                                <span class="small muted">Required</span>
                            </div>
                            <div class="d-flex flex-column gap-2 mb-3">
                                @forelse ($group->activeOptions as $option)
                                    @php
                                        $isCustomerSupplied = $option->isCustomerSuppliedOption();
                                        $isSelected = $group->selections->firstWhere('repair_part_option_id', $option->id);
                                        $details = $isCustomerSupplied
                                            ? 'Customer supplied'
                                            : collect([$option->sku_snapshot ?: 'No SKU', $option->modelLabel()])->filter()->join(' · ');
                                    @endphp
                                    <div class="repair-part-option border rounded p-2">
                                        <div class="repair-part-option-content">
                                            @if ($isCustomerSupplied)
                                                <span class="repair-option-thumb repair-option-system-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                                            @else
                                                <img class="repair-option-thumb" src="{{ $option->imageUrl() }}" alt="{{ $option->name_snapshot }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                            @endif
                                            <div class="repair-part-option-details">
                                                <div class="d-flex flex-wrap align-items-center gap-2 min-width-0">
                                                    <strong class="repair-part-option-name" title="{{ $option->label() }}">{{ $option->label() }}</strong>
                                                    @if ($isCustomerSupplied)
                                                        <span class="badge text-bg-info">Customer supplied</span>
                                                    @elseif ($option->is_primary)
                                                        <span class="badge text-bg-primary">Recommended</span>
                                                    @endif
                                                </div>
                                                <div class="small muted repair-part-option-subtext" title="{{ $details }}">{{ $details }}</div>
                                            </div>
                                            <div class="repair-part-option-meta">
                                                <div class="fw-bold">${{ number_format((float) $option->price_snapshot, 2) }}</div>
                                                <div class="small muted">{{ $isSelected ? 'Selected' : 'Not selected' }}</div>
                                            </div>
                                        </div>
                                        @if (! $isCustomerSupplied && ! in_array($conversation->status, [\App\Models\RepairConversation::STATUS_PAYMENT_PENDING, \App\Models\RepairConversation::STATUS_PAID, \App\Models\RepairConversation::STATUS_CLOSED], true))
                                            <div class="repair-part-option-actions d-flex flex-wrap gap-2 mt-2">
                                                @if ($option->is_primary)
                                                    <button class="btn btn-primary btn-sm" type="button" disabled>Recommended</button>
                                                @else
                                                    <form method="POST" action="{{ route('admin.repairs.conversation.part-options.primary', [$repair, $option]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-outline-primary btn-sm" type="submit">Mark Recommended</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('admin.repairs.conversation.part-options.destroy', [$repair, $option]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Remove {{ $option->label() }}">Remove</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="muted mb-0">No options yet.</p>
                                @endforelse
                            </div>

                            @if (! in_array($conversation->status, [\App\Models\RepairConversation::STATUS_PAYMENT_PENDING, \App\Models\RepairConversation::STATUS_PAID, \App\Models\RepairConversation::STATUS_CLOSED], true))
                                <form class="repair-part-search-form" method="POST" action="{{ route('admin.repairs.conversation.part-options.store', [$repair, $group]) }}" data-repair-part-search-form data-search-url="{{ route('admin.repairs.conversation.part-search', $repair) }}">
                                    @csrf
                                    <input type="hidden" name="part_id" data-repair-part-id>
                                    <div class="position-relative">
                                        <label class="form-label" for="repair_part_search_{{ $group->id }}">Add Part Option</label>
                                        <div class="input-group">
                                            <input class="form-control" id="repair_part_search_{{ $group->id }}" type="search" autocomplete="off" placeholder="Search by SKU, part name, model, or model number" data-repair-part-search>
                                            <button class="btn btn-outline-secondary" type="button" data-repair-part-clear>Clear</button>
                                        </div>
                                        <div class="repair-part-search-results d-none" data-repair-part-results></div>
                                    </div>
                                    <div class="row g-2 align-items-end mt-2">
                                        <div class="col-md-4">
                                            <label class="form-label" for="sort_order_{{ $group->id }}">Sort order</label>
                                            <input class="form-control" id="sort_order_{{ $group->id }}" name="sort_order" type="number" min="0" value="0">
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-check">
                                                <input class="form-check-input" id="is_primary_{{ $group->id }}" name="is_primary" type="checkbox" value="1">
                                                <label class="form-check-label" for="is_primary_{{ $group->id }}">Mark selected part as recommended</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">Select a search result to add it to this required part group. Price and snapshots are generated by the server.</div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="muted mb-0">No part groups have been added.</p>
                    @endforelse
                </div>

                <div class="border rounded p-3">
                    <h3 class="h6 fw-bold">Conversation</h3>
                    <div class="d-flex flex-column gap-2">
                        @forelse ($conversationMessages as $message)
                            <div class="p-3 rounded bg-light">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong>{{ ucfirst($message->sender_type) }}</strong>
                                    <span class="small muted">{{ $message->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                                @if ($message->is_internal)
                                    <span class="badge text-bg-warning mb-2">Internal</span>
                                @endif
                                <p class="mb-0">{{ $message->message }}</p>
                            </div>
                        @empty
                            <p class="muted mb-0">No messages yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="surface p-4 mt-4">
        <h2 class="h5 fw-bold">Repair Agreement</h2>
        <p class="muted mb-0">A customer profile is required before a repair conversation can be created.</p>
    </div>
@endif

@push('scripts')
    <style>
        .repair-option-thumb,
        .repair-part-search-thumb {
            width: 56px;
            height: 56px;
            object-fit: contain;
            object-position: center;
            background: #f8f9fa;
            border: 1px solid #e5eaf1;
            border-radius: 8px;
            flex: 0 0 56px;
        }

        .repair-option-system-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            font-size: 1.35rem;
        }

        .repair-part-option {
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .repair-part-option-content {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            min-width: 0;
        }

        .repair-part-option-details {
            flex: 1 1 auto;
            min-width: 0;
        }

        .repair-part-option-name,
        .repair-part-option-subtext {
            display: block;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .repair-part-option-meta {
            flex: 0 0 auto;
            max-width: 9rem;
            text-align: right;
        }

        .repair-part-option-actions {
            max-width: 100%;
        }

        .repair-part-search-results {
            position: absolute;
            z-index: 1040;
            top: calc(100% + .25rem);
            left: 0;
            right: 0;
            max-height: 360px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 8px;
            box-shadow: 0 18px 42px rgba(7, 29, 58, .14);
        }

        .repair-part-search-row {
            width: 100%;
            border: 0;
            background: #fff;
            display: flex;
            gap: .75rem;
            align-items: center;
            padding: .75rem;
            text-align: left;
        }

        .repair-part-search-row:hover,
        .repair-part-search-row:focus {
            background: #f5f9ff;
            outline: none;
        }

        .repair-part-search-name {
            font-weight: 700;
            color: #071d3a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .repair-part-search-meta {
            color: #6c7a90;
            font-size: .85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 575.98px) {
            .repair-part-option-content {
                flex-wrap: wrap;
            }

            .repair-part-option-meta {
                flex: 1 0 100%;
                max-width: 100%;
                padding-left: 68px;
                text-align: left;
            }
        }
    </style>
    <script>
        (() => {
            const minimumSearchLength = 2;
            const debounceMs = 250;

            document.querySelectorAll('[data-repair-part-search-form]').forEach((form) => {
                const input = form.querySelector('[data-repair-part-search]');
                const results = form.querySelector('[data-repair-part-results]');
                const hiddenPartId = form.querySelector('[data-repair-part-id]');
                const clear = form.querySelector('[data-repair-part-clear]');
                let timer = null;
                let controller = null;

                if (!input || !results || !hiddenPartId || !form.dataset.searchUrl) return;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const hideResults = () => {
                    results.classList.add('d-none');
                    results.innerHTML = '';
                };

                const showMessage = (message) => {
                    results.innerHTML = `<div class="p-3 small text-muted">${message}</div>`;
                    results.classList.remove('d-none');
                };

                const renderResults = (items) => {
                    results.innerHTML = '';

                    if (!items.length) {
                        showMessage('No matching parts found.');
                        return;
                    }

                    items.forEach((part) => {
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'repair-part-search-row';
                        const meta = [
                            part.sku || 'No SKU',
                            part.model || '',
                            part.category || '',
                        ].filter(Boolean).join(' · ');
                        row.innerHTML = `
                            <img class="repair-part-search-thumb" src="${escapeHtml(part.image_url)}" alt="">
                            <span class="min-width-0 flex-grow-1">
                                <span class="repair-part-search-name d-block" title="${escapeHtml(part.name)}">${escapeHtml(part.name)}</span>
                                <span class="repair-part-search-meta d-block">${escapeHtml(meta)}</span>
                            </span>
                            <strong class="text-danger text-nowrap">$${escapeHtml(part.price)}</strong>
                        `;
                        row.addEventListener('click', () => {
                            hiddenPartId.value = part.id;
                            hideResults();
                            form.requestSubmit();
                        });
                        results.appendChild(row);
                    });

                    results.classList.remove('d-none');
                };

                const runSearch = () => {
                    const term = input.value.trim();
                    hiddenPartId.value = '';

                    if (controller) controller.abort();

                    if (term.length < minimumSearchLength) {
                        hideResults();
                        return;
                    }

                    controller = new AbortController();
                    showMessage('Searching parts...');

                    const url = new URL(form.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', term);

                    fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        signal: controller.signal,
                    })
                        .then((response) => {
                            if (!response.ok) throw new Error('search_failed');
                            return response.json();
                        })
                        .then((payload) => renderResults(payload.results || []))
                        .catch((error) => {
                            if (error.name === 'AbortError') return;
                            showMessage('Unable to search parts right now. Please try again.');
                        });
                };

                input.addEventListener('input', () => {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(runSearch, debounceMs);
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') hideResults();
                });

                clear?.addEventListener('click', () => {
                    input.value = '';
                    hiddenPartId.value = '';
                    if (controller) controller.abort();
                    hideResults();
                    input.focus();
                });
            });

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-repair-part-search-form]').forEach((form) => {
                    if (!form.contains(event.target)) {
                        const results = form.querySelector('[data-repair-part-results]');
                        results?.classList.add('d-none');
                    }
                });
            });
        })();
    </script>
@endpush
