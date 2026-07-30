@extends('layouts.admin')

@section('title', 'Payment Webhooks')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Webhook Events</h1></div>
            <div class="surface p-4 table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Provider</th><th>Event</th><th>Type</th><th>Status</th><th>Attempts</th><th>Received</th></tr></thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>{{ ucfirst($event->provider) }}</td>
                                <td class="text-break">{{ $event->provider_event_id }}</td>
                                <td>{{ $event->event_type }}</td>
                                <td><span class="status-pill">{{ ucfirst($event->status) }}</span></td>
                                <td>{{ $event->attempt_count }}</td>
                                <td>{{ $event->received_at?->format('M j, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No webhook events found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $events->links() }}
            </div>
        </div>
    </section>
@endsection
