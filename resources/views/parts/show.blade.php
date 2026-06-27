@extends('layouts.app')

@section('title', $part->name)

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Parts Price Check</p>
            <h1 class="display-5 fw-bold mb-3">{{ $part->name }}</h1>
            <p class="fs-5 mb-0">{{ $part->brandName() }} &middot; {{ $part->modelName() }}</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="surface part-card overflow-hidden">
                        <img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}">
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="surface p-4 h-100">
                        <p class="eyebrow mb-2">{{ $part->categoryName() }}</p>
                        <h2 class="h4 fw-bold mb-3">${{ number_format($part->displayPrice(), 2) }}</h2>
                        <dl class="row">
                            <dt class="col-sm-4">SKU</dt>
                            <dd class="col-sm-8">{{ $part->sku ?: $part->new_sku ?: 'N/A' }}</dd>
                            <dt class="col-sm-4">Brand</dt>
                            <dd class="col-sm-8">{{ $part->brandName() ?: 'N/A' }}</dd>
                            <dt class="col-sm-4">Model</dt>
                            <dd class="col-sm-8">{{ $part->modelName() ?: 'N/A' }}</dd>
                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8">{{ $part->categoryName() ?: 'N/A' }}</dd>
                            <dt class="col-sm-4">Stock</dt>
                            <dd class="col-sm-8">{{ $part->stockLabel() }}</dd>
                        </dl>
                        @if ($part->short_description ?: $part->description)
                            <div class="mt-3">
                                {!! nl2br(e($part->short_description ?: $part->description)) !!}
                            </div>
                        @endif
                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" href="{{ route('contact.create') }}">Contact Us</a>
                            <a class="btn btn-outline-primary" href="{{ route('parts.index') }}">Back to Parts</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
