@extends('layouts.app')

@section('title', 'My Repairs')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">My Repairs</p>
            <h1 class="display-5 fw-bold mb-0">Repair bookings and status history.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Tracking</th>
                                <th>Device</th>
                                <th>Issue</th>
                                <th>Status</th>
                                <th>Estimated Completion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($repairs as $repair)
                                <tr>
                                    <td>{{ $repair->tracking_number }}</td>
                                    <td>{{ $repair->deviceLabel() }}</td>
                                    <td>{{ $repair->issue_category }}</td>
                                    <td><span class="status-pill">{{ $repair->status }}</span></td>
                                    <td>{{ $repair->estimated_completion_date?->format('M j, Y') ?? 'To be confirmed' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No repairs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $repairs->links() }}
            </div>
        </div>
    </section>
@endsection
