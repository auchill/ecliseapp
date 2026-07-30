@extends('layouts.admin')

@section('title', 'Payment Settings')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="mb-4"><p class="eyebrow">Payments</p><h1 class="display-6 fw-bold mb-0">Settings</h1></div>
            <form class="surface p-4" method="POST" action="{{ route('admin.payments.settings.update') }}">
                @csrf
                <div class="row g-3">
                    @foreach ($settings as $key => $value)
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label" for="{{ $key }}">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                            @if (is_bool($value))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" id="{{ $key }}" name="{{ $key }}" type="checkbox" value="1" @checked($value)>
                                </div>
                            @elseif (str_contains($key, 'instructions') || str_contains($key, 'terms'))
                                <textarea class="form-control" id="{{ $key }}" name="{{ $key }}" rows="3">{{ old($key, $value) }}</textarea>
                            @else
                                <input class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $value) }}">
                            @endif
                        </div>
                    @endforeach
                    <div class="col-12"><button class="btn btn-primary">Save Settings</button></div>
                </div>
            </form>
        </div>
    </section>
@endsection
