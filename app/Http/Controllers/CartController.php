<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $items = $this->cartItems($request);

        return view('cart.index', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);
        abort_unless($product->status === 'Active' && $product->quantity > 0, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        if ($request->user()) {
            $cart = $this->activeCart($request);
            $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
            $item->unit_price = $product->currentPrice();
            $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + $data['quantity']);
            $item->save();
        } else {
            $cart = $request->session()->get('cart.items', []);
            $cart[$product->id] = min($product->quantity, ($cart[$product->id] ?? 0) + $data['quantity']);
            $request->session()->put('cart.items', $cart);
        }

        return back()->with('status', 'Product added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        if ($request->user()) {
            $cart = $this->activeCart($request);
            $cart->items()->where('product_id', $product->id)->update([
                'quantity' => $data['quantity'],
            ]);
        } else {
            $cart = $request->session()->get('cart.items', []);
            $cart[$product->id] = $data['quantity'];
            $request->session()->put('cart.items', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        if ($request->user()) {
            $this->activeCart($request)->items()->where('product_id', $product->id)->delete();
        } else {
            $cart = $request->session()->get('cart.items', []);
            unset($cart[$product->id]);
            $request->session()->put('cart.items', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Item removed from cart.');
    }

    private function activeCart(Request $request): Cart
    {
        return Cart::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'status' => 'active',
        ]);
    }

    private function cartItems(Request $request)
    {
        if ($request->user()) {
            return $this->activeCart($request)
                ->load('items.product')
                ->items
                ->filter(fn ($item) => $item->product)
                ->map(fn ($item) => [
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => $item->lineTotal(),
                ])
                ->values();
        }

        $sessionItems = collect($request->session()->get('cart.items', []))
            ->filter(fn ($quantity) => (int) $quantity > 0);

        if ($sessionItems->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $sessionItems->keys())
            ->get()
            ->keyBy('id');

        return $sessionItems
            ->map(function ($quantity, $productId) use ($products) {
                $product = $products->get((int) $productId);

                if (! $product) {
                    return null;
                }

                $quantity = min((int) $quantity, max(1, $product->quantity));
                $unitPrice = $product->currentPrice();

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $quantity,
                ];
            })
            ->filter()
            ->values();
    }
}
