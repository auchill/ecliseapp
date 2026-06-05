@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Checkout</p>
            <h1 class="display-5 fw-bold mb-0">Square payment placeholder.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <form class="surface p-4" method="POST" action="{{ route('checkout.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="customer_name">Name</label>
                                <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone</label>
                                <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address">Pickup or shipping address</label>
                                <textarea class="form-control" id="address" name="address" rows="3">{{ old('address') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Order notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-credit-card me-2"></i>Place Order</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="surface p-4">
                        <h2 class="h4 fw-bold mb-3">Order Summary</h2>
                        @foreach ($cart->items as $item)
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div>
                                    <strong>{{ $item->product->name }}</strong>
                                    <div class="small muted">Qty {{ $item->quantity }}</div>
                                </div>
                                <strong>${{ number_format($item->lineTotal(), 2) }}</strong>
                            </div>
                        @endforeach
                        @php
                            $subtotal = $cart->subtotal();
                            $tax = round($subtotal * 0.13, 2);
                        @endphp
                        <div class="d-flex justify-content-between pt-3">
                            <span>Subtotal</span>
                            <strong>${{ number_format($subtotal, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span>Estimated tax</span>
                            <strong>${{ number_format($tax, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-3 mt-3 border-top h4">
                            <span>Total</span>
                            <strong>${{ number_format($subtotal + $tax, 2) }}</strong>
                        </div>
                        <p class="muted small mb-0">Payment capture is structured for Square and currently uses a placeholder reference.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
