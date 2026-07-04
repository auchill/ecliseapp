<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MobileSentrixDevice;
use App\Models\Permission;
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
                'email' => 'Invalid customer credentials.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user()->load('permission');

        if (! $user->isCustomer()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Invalid customer credentials.',
            ])->onlyInput('email');
        }

        $this->mergeSessionCart($request);

        return redirect()->intended(route('dashboard'));
    }

    public function showAdminLogin()
    {
        if (auth()->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.admin-login');
    }

    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user()->load('permission');

        if (! $user->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Invalid admin credentials.',
            ])->onlyInput('email');
        }

        return redirect()->intended(route('admin.dashboard'));
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
            'permission_id' => Permission::query()->where('name', 'customer')->where('status', 'active')->value('id'),
            'status' => 'active',
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

    public function adminLogout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
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

        $sessionItems->each(function ($quantity, $key) use ($cart): void {
            [$source, $productId] = $this->parseCartKey((string) $key);

            if ($source === CartItem::SOURCE_MOBILESENTRIX) {
                $device = MobileSentrixDevice::query()
                    ->where('entity_id', $productId)
                    ->orWhere('sku', $productId)
                    ->first();

                if (! $device || $device->availableQuantity() <= 0) {
                    return;
                }

                $item = $cart->items()->firstOrNew([
                    'product_id' => $device->cartProductId(),
                    'item_source' => CartItem::SOURCE_MOBILESENTRIX,
                ]);
                $item->unit_price = $device->displayPrice() ?? 0;
                $item->quantity = min($device->availableQuantity(), ($item->exists ? $item->quantity : 0) + (int) $quantity);
                $item->save();

                return;
            }

            $id = preg_replace('/^ecl/i', '', $productId);
            $product = is_numeric($id) ? Product::query()->find((int) $id) : null;

            if (! $product || $product->status !== 'Active' || $product->quantity <= 0) {
                return;
            }

            $item = $cart->items()->firstOrNew([
                'product_id' => 'ecl'.$product->id,
                'item_source' => CartItem::SOURCE_ECLISE,
            ]);
            $item->unit_price = $product->currentPrice();
            $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + (int) $quantity);
            $item->save();
        });

        $request->session()->forget('cart.items');
    }

    private function parseCartKey(string $key): array
    {
        if (! str_contains($key, ':')) {
            return [CartItem::SOURCE_ECLISE, str_starts_with($key, 'ecl') ? $key : 'ecl'.$key];
        }

        [$source, $productId] = explode(':', $key, 2);

        return [$source ?: CartItem::SOURCE_ECLISE, $productId];
    }
}
