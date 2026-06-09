<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->mergeSessionCart($request);

        return redirect()->intended(Auth::user()->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'customer',
        ]);

        Auth::login($user);
        $this->mergeSessionCart($request);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function mergeSessionCart(Request $request): void
    {
        $sessionItems = collect($request->session()->get('cart.items', []))
            ->filter(fn ($quantity) => (int) $quantity > 0);

        if ($sessionItems->isEmpty() || ! $request->user()) {
            return;
        }

        $cart = Cart::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'status' => 'active',
        ]);

        $products = Product::query()
            ->whereIn('id', $sessionItems->keys())
            ->get()
            ->keyBy('id');

        $sessionItems->each(function ($quantity, $productId) use ($cart, $products): void {
            $product = $products->get((int) $productId);

            if (! $product || $product->status !== 'Active' || $product->quantity <= 0) {
                return;
            }

            $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
            $item->unit_price = $product->currentPrice();
            $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + (int) $quantity);
            $item->save();
        });

        $request->session()->forget('cart.items');
    }
}
