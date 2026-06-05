@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <form class="surface p-4 p-lg-5" method="POST" action="{{ route('register.store') }}">
                        @csrf
                        <p class="eyebrow">Customer Account</p>
                        <h1 class="h2 fw-bold mb-4">Create your Eclise account.</h1>
                        <div class="mb-3">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" name="password" type="password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus me-2"></i>Register</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
