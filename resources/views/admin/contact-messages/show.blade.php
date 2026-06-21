@extends('layouts.admin')

@section('title', $message->subject)

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Contact Message</p>
                    <h1 class="display-6 fw-bold mb-0">{{ $message->subject }}</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.contact-messages.index') }}"><i class="bi bi-arrow-left me-2"></i>Messages</a>
            </div>

            <div class="surface p-4 p-lg-5">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <h2 class="h5 fw-bold">Sender</h2>
                        <p class="mb-1"><strong>Name:</strong> {{ $message->name }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $message->email }}</p>
                        <p class="mb-0"><strong>Phone:</strong> {{ $message->phone ?: 'Not provided' }}</p>
                    </div>
                    <div class="col-lg-8">
                        <h2 class="h5 fw-bold">Message</h2>
                        <p class="fs-5">{{ $message->message }}</p>
                        <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
