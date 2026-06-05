@extends('layouts.app')

@section('title', 'Contact Messages')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1 class="display-6 fw-bold mb-0">Contact Messages</h1>
                </div>
            </div>

            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($messages as $message)
                                <tr>
                                    <td>{{ $message->name }}<div class="small muted">{{ $message->email }}</div></td>
                                    <td>{{ $message->subject }}</td>
                                    <td><span class="status-pill">{{ $message->read_at ? 'Read' : 'Unread' }}</span></td>
                                    <td>{{ $message->created_at->format('M j, Y') }}</td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.contact-messages.show', $message) }}"><i class="bi bi-eye"></i><span class="visually-hidden">Open</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No messages found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $messages->links() }}
            </div>
        </div>
    </section>
@endsection
