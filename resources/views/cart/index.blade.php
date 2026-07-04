@extends('layouts.app')

@section('title', 'Cart')

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">Cart</p>
            <h1 class="display-5 fw-bold mb-0">Review selected products.</h1>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="container">
            <div class="surface p-4">
                @if ($items->isEmpty())
                    <p class="muted">Your cart is empty.</p>
                    <a class="btn btn-primary" href="{{ route('shop.index') }}"><i class="bi bi-bag me-2"></i>Shop Products</a>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th>Line Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="72" height="72" style="object-fit: contain;">
                                                <div>
                                                    <strong>{{ $item['name'] }}</strong>
                                                    <div class="small muted">{{ $item['source'] }} &middot; {{ $item['sku'] ?: 'No SKU' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>${{ number_format($item['unit_price'], 2) }}</td>
                                        <td>
                                            <form class="d-flex gap-2" method="POST" action="{{ route('cart.items.update') }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="item_key" value="{{ $item['cart_key'] }}">
                                                <input class="form-control" name="quantity" type="number" min="1" max="{{ $item['max_quantity'] }}" value="{{ $item['quantity'] }}" style="width: 96px;">
                                                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i><span class="visually-hidden">Update</span></button>
                                            </form>
                                        </td>
                                        <td>${{ number_format($item['line_total'], 2) }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('cart.items.destroy') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="item_key" value="{{ $item['cart_key'] }}">
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i><span class="visually-hidden">Remove</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-3">
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-primary" href="{{ route('shop.index') }}"><i class="bi bi-arrow-left me-2"></i>New & Retail Products</a>
                            <a class="btn btn-outline-primary" href="{{ route('shop.certified-pre-owned-devices.index') }}"><i class="bi bi-phone me-2"></i>Certified Pre-Owned Devices</a>
                        </div>
                        <div class="text-end">
                            <p class="h4 fw-bold mb-2">Subtotal: ${{ number_format($subtotal, 2) }}</p>
                            @auth
                                @if(auth()->user()->isCustomer())
                                    <a class="btn btn-primary btn-lg" href="{{ route('checkout.show') }}"><i class="bi bi-credit-card me-2"></i>Checkout</a>
                                @endif
                            @else
                                <a class="btn btn-primary btn-lg" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Login to Checkout</a>
                            @endauth
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
