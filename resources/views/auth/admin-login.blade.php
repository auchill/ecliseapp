@extends('layouts.admin-auth')

@section('title', 'Admin Login')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5 col-xl-4">
            <form class="surface p-4 p-lg-5" method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="text-center mb-4">
                    <img src="{{ asset('images/brand/header_logo.png') }}" alt="Eclise Technology Inc." style="max-width: 220px;">
                </div>
                <p class="eyebrow">Admin Login</p>
                <h1 class="h3 fw-bold mb-4">Sign in to admin.</h1>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
            </form>
        </div>
    </div>
@endsection
