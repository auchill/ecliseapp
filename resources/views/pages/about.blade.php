@extends('layouts.app')

@section('title', 'About Us')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">About Eclise</p>
            <h1 class="display-5 fw-bold mb-3">Technology service built around repair, reuse, and reliable support.</h1>
            <p class="fs-5 mb-0">Eclise Technology Inc. helps customers extend the life of phones, computers, and accessories with practical repair and retail options.</p>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <img class="img-fluid" src="{{ asset('images/brand/logo_main2.png') }}" alt="Eclise Technology Inc.">
                </div>
                <div class="col-lg-7">
                    <p class="eyebrow">Our Work</p>
                    <h2 class="display-6 fw-bold">Repair first, replace when it makes sense.</h2>
                    <p class="muted fs-5">We support customers through diagnostics, repairs, used and new device options, accessories, and transparent parts price verification.</p>
                    <div class="row g-3 mt-3">
                        @foreach (['Phone and computer repairs', 'Used and new device sales', 'Accessory sales', 'Parts price verification'] as $item)
                            <div class="col-sm-6">
                                <div class="d-flex gap-2 align-items-start">
                                    <i class="bi bi-check-circle-fill text-primary mt-1"></i>
                                    <span>{{ $item }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="container">
            <div class="row g-4">
                @foreach ([
                    ['title' => 'Repair', 'copy' => 'Focused diagnostics and service for the problems customers bring in every day.'],
                    ['title' => 'Reuse', 'copy' => 'Quality used devices and practical repairs help reduce unnecessary replacement.'],
                    ['title' => 'Reconnect', 'copy' => 'Customers leave with working devices, clear status updates, and options that fit their needs.'],
                ] as $value)
                    <div class="col-md-4">
                        <div class="surface h-100 p-4">
                            <h2 class="h4 fw-bold">{{ $value['title'] }}</h2>
                            <p class="muted mb-0">{{ $value['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
