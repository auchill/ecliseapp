<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Services\SquarePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function show(Request $request)
    {
        $cart = $this->activeCart($request)->load('items.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        return view('checkout.show', [
            'cart' => $cart,
        ]);
    }

    public function store(CheckoutRequest $request, SquarePaymentService $payments)
    {
        $cart = $this->activeCart($request)->load('items.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        $data = $request->validated();
        $payment = $payments->createCheckout($cart, $data);

        $order = DB::transaction(function () use ($cart, $data, $payment, $request): Order {
            $subtotal = $cart->subtotal();
            $tax = round($subtotal * 0.13, 2);
            $total = $subtotal + $tax;

            $order = Order::query()->create([
                'user_id' => $request->user()->id,
                'order_number' => $this->generateOrderNumber(),
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'Pending',
                'payment_provider' => $payment['provider'],
                'payment_reference' => $payment['reference'],
                'notes' => $data['notes'] ?? $payment['message'],
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->lineTotal(),
                ]);

                $item->product->decrement('quantity', $item->quantity);

                if ($item->product->fresh()->quantity === 0) {
                    $item->product->update(['status' => 'Out of Stock']);
                }
            }

            $cart->update(['status' => 'converted']);

            return $order;
        });

        return redirect()->route('checkout.confirmation', $order);
    }

    public function confirmation(Order $order)
    {
        abort_unless(auth()->id() === $order->user_id || auth()->user()?->isAdmin(), 403);

        return view('checkout.confirmation', [
            'order' => $order->load('items'),
        ]);
    }

    private function activeCart(Request $request): Cart
    {
        return Cart::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'status' => 'active',
        ]);
    }

    private function generateOrderNumber(): string
    {
        $year = now()->year;
        $next = Order::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $orderNumber = sprintf('ECL-ORD-%s-%04d', $year, $next++);
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
