@extends('layouts.app')

@section('title', 'Repair Proposal '.$conversation->repair->repair_number)

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Repair {{ $conversation->repair->repair_number }}</p>
            <h1 class="display-5 fw-bold mb-0">{{ $conversation->repair->deviceLabel() }}</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <span class="status-pill">{{ $conversation->statusLabel() }}</span>
                    <p class="muted mb-0 mt-2">Proposal version {{ $conversation->proposal_version ?: 'not sent yet' }}</p>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('customer.repairs') }}"><i class="bi bi-arrow-left me-2"></i>My Repairs</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="surface p-4 mb-4">
                        <h2 class="h4 fw-bold mb-3">Part Options</h2>
                        @forelse ($conversation->activePartGroups as $group)
                            @php
                                $selection = $group->selections->firstWhere('customer_id', $customer->id);
                            @endphp
                            <form class="border rounded p-3 mb-3" method="POST" action="{{ route('repair-conversations.part-selections.store', $conversation) }}">
                                @csrf
                                <h3 class="h6 fw-bold mb-1">{{ $group->title }}</h3>
                                @if ($group->description)
                                    <p class="muted small">{{ $group->description }}</p>
                                @endif
                                @foreach ($group->activeOptions as $option)
                                    @php
                                        $isCustomerSupplied = $option->isCustomerSuppliedOption();
                                        $details = $isCustomerSupplied
                                            ? 'Customer supplied'
                                            : collect([$option->sku_snapshot ?: 'No SKU', $option->modelLabel()])->filter()->join(' · ');
                                    @endphp
                                    <div class="form-check repair-part-option border rounded p-3 ps-5 mb-2">
                                        <input class="form-check-input" id="option_{{ $option->id }}" name="option_id" type="radio" value="{{ $option->id }}" @checked($selection?->repair_part_option_id === $option->id) required>
                                        <label class="form-check-label w-100" for="option_{{ $option->id }}">
                                            <span class="repair-part-option-content">
                                                @if ($isCustomerSupplied)
                                                    <span class="repair-option-thumb repair-option-system-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                                                @else
                                                    <img class="repair-option-thumb" src="{{ $option->imageUrl() }}" alt="{{ $option->name_snapshot }}" onerror="this.onerror=null;this.src='{{ \App\Support\CatalogImage::fallbackUrl() }}';">
                                                @endif
                                                <span class="repair-part-option-details">
                                                    <span class="d-flex flex-wrap align-items-center gap-2">
                                                        <strong class="repair-part-option-name" title="{{ $option->label() }}">{{ $option->label() }}</strong>
                                                        @if ($isCustomerSupplied)
                                                            <span class="badge text-bg-info">Customer supplied</span>
                                                        @elseif ($option->is_primary)
                                                            <span class="badge text-bg-primary">Recommended</span>
                                                        @endif
                                                    </span>
                                                    <span class="small muted repair-part-option-subtext" title="{{ $details }}">{{ $details }}</span>
                                                </span>
                                                <span class="repair-part-option-meta text-danger fw-bold">${{ number_format((float) $option->price_snapshot, 2) }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                                <button class="btn btn-outline-primary btn-sm" type="submit">Save Selection</button>
                            </form>
                        @empty
                            <p class="muted mb-0">No part options have been proposed yet.</p>
                        @endforelse
                    </div>

                    <div class="surface p-4">
                        <h2 class="h4 fw-bold mb-3">Messages</h2>
                        <div class="d-flex flex-column gap-3 mb-4">
                            @forelse ($messages as $message)
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                        <strong>{{ ucfirst($message->sender_type) }}</strong>
                                        <span class="small muted">{{ $message->created_at->format('M j, Y g:i A') }}</span>
                                    </div>
                                    <p class="mb-0">{{ $message->message }}</p>
                                </div>
                            @empty
                                <p class="muted mb-0">No messages yet.</p>
                            @endforelse
                        </div>

                        <form method="POST" action="{{ route('repair-conversations.messages.store', $conversation) }}">
                            @csrf
                            <label class="form-label" for="message">Reply</label>
                            <textarea class="form-control mb-3" id="message" name="message" rows="4" required>{{ old('message') }}</textarea>
                            <button class="btn btn-primary" type="submit">Send Message</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="surface p-4 mb-4">
                        <h2 class="h4 fw-bold mb-3">Agreement Total</h2>
                        <table class="table">
                            <tbody>
                                <tr><th scope="row">Selected parts</th><td class="text-end">${{ number_format((float) $conversation->selected_parts_subtotal, 2) }}</td></tr>
                                <tr><th scope="row">Labour</th><td class="text-end">${{ number_format((float) $conversation->labour_amount, 2) }}</td></tr>
                                <tr><th scope="row">Diagnostic</th><td class="text-end">${{ number_format((float) $conversation->diagnostic_fee, 2) }}</td></tr>
                                <tr><th scope="row">Service</th><td class="text-end">${{ number_format((float) $conversation->service_fee, 2) }}</td></tr>
                                <tr><th scope="row">Discount</th><td class="text-end">-${{ number_format((float) $conversation->discount_amount, 2) }}</td></tr>
                                <tr><th scope="row">Tax</th><td class="text-end">${{ number_format((float) $conversation->tax_amount, 2) }}</td></tr>
                                <tr class="fw-bold"><th scope="row">Total</th><td class="text-end">${{ number_format((float) $conversation->final_total, 2) }}</td></tr>
                            </tbody>
                        </table>

                        @if ($errors->has('agreement'))
                            <div class="alert alert-danger">{{ $errors->first('agreement') }}</div>
                        @endif

                        @if ($conversation->status === \App\Models\RepairConversation::STATUS_AWAITING_CUSTOMER)
                            <form method="POST" action="{{ route('repair-conversations.accept', $conversation) }}">
                                @csrf
                                <div class="form-check mb-3">
                                    <input class="form-check-input" id="agreement_accepted" name="agreement_accepted" type="checkbox" value="1" required>
                                    <label class="form-check-label" for="agreement_accepted">I agree to the selected parts and repair total.</label>
                                </div>
                                <button class="btn btn-primary w-100" type="submit">Accept Proposal</button>
                            </form>
                        @elseif ($conversation->status === \App\Models\RepairConversation::STATUS_PAYMENT_PENDING)
                            <form method="POST" action="{{ route('repair-conversations.payment', $conversation) }}">
                                @csrf
                                <label class="form-label" for="payment_gateway">Payment method</label>
                                <select class="form-select mb-3" id="payment_gateway" name="payment_gateway" required>
                                    <option value="stripe">Card</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                                <button class="btn btn-primary w-100" type="submit">Pay ${{ number_format($conversation->repair->currentBalanceDue(), 2) }}</button>
                            </form>
                        @elseif ($conversation->status === \App\Models\RepairConversation::STATUS_PAID)
                            <div class="alert alert-success mb-0">Payment has been received for this repair agreement.</div>
                        @else
                            <p class="muted mb-0">The repair proposal is not ready for acceptance yet.</p>
                        @endif
                    </div>

                    <div class="surface p-4">
                        <h2 class="h5 fw-bold">Repair Details</h2>
                        <table class="table mb-0">
                            <tbody>
                                <tr><th scope="row">Issue</th><td>{{ $conversation->repair->issueCategoryName() }}</td></tr>
                                <tr><th scope="row">Status</th><td>{{ $conversation->repair->statusLabel() }}</td></tr>
                                <tr><th scope="row">Balance due</th><td>${{ number_format($conversation->repair->currentBalanceDue(), 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <style>
        .repair-option-thumb {
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
@endpush
