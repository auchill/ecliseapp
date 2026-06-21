@extends('layouts.app')

@section('title', 'Customer Login')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <form class="surface p-4 p-lg-5" method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <p class="eyebrow">Customer Login</p>
                        <h1 class="h2 fw-bold mb-4">Access your Eclise account.</h1>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
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
        </div>
    </section>
@endsection
