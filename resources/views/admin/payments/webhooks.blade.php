@extends('layouts.admin')

@section('title', 'Payment Webhooks')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Webhook Events</h1></div>
            @if ($errors->has('webhook'))
                <div class="alert alert-danger">{{ $errors->first('webhook') }}</div>
            @endif
            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Provider</th><th>Event</th><th>Type</th><th>Status</th><th>Attempts</th><th>Received</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>{{ ucfirst($event->provider) }}</td>
                                <td class="text-break">{{ $event->provider_event_id }}</td>
                                <td>{{ $event->event_type }}</td>
                                <td><span class="status-pill">{{ ucfirst($event->status) }}</span></td>
                                <td>{{ $event->attempt_count }}</td>
                                <td>{{ $event->received_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-end">
                                    @if ($event->provider === 'stripe' && in_array($event->status, [\App\Models\PaymentWebhookEvent::STATUS_FAILED, \App\Models\PaymentWebhookEvent::STATUS_IGNORED], true))
                                        <form method="POST" action="{{ route('admin.payment-webhooks.retry', $event) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-outline-primary btn-sm" type="submit">Retry</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if (filled($event->error_message))
                                {{-- Only the safe processing error is shown. Raw provider payloads are never rendered. --}}
                                <tr class="border-0">
                                    <td class="pt-0 border-0" colspan="7">
                                        <span class="muted small">Needs investigation: {{ \Illuminate\Support\Str::limit($event->error_message, 300) }}</span>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="7">No webhook events found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $events->links() }}
            </div>
        </div>
    </section>
@endsection
