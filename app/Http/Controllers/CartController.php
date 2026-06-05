<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        return view('cart.index', [
            'cart' => $this->activeCart($request)->load('items.product'),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($product->status === 'Active' && $product->quantity > 0, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        $cart = $this->activeCart($request);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->unit_price = $product->currentPrice();
        $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->save();

        return redirect()->route('cart.index')->with('status', 'Product added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        $cart = $this->activeCart($request);
        $cart->items()->where('product_id', $product->id)->update([
            'quantity' => $data['quantity'],
        ]);

        return redirect()->route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->activeCart($request)->items()->where('product_id', $product->id)->delete();

        return redirect()->route('cart.index')->with('status', 'Item removed from cart.');
    }

    private function activeCart(Request $request): Cart
    {
        return Cart::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'status' => 'active',
        ]);
    }
}
