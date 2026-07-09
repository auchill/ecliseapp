<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->storeIntendedUrl($request);

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

    public function showRegister(Request $request)
    {
        $this->storeIntendedUrl($request);

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

        return redirect()->intended(route('dashboard'));
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

        $cart = Customer::forUser($request->user())->getOrCreateActiveCart();

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
                    'source_id' => $device->entity_id,
                    'source_sku' => $device->sku,
                    'source' => CartItem::SOURCE_MOBILESENTRIX,
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
                'source_id' => $product->id,
                'source_sku' => $product->sku,
                'source' => CartItem::SOURCE_ECLISE,
            ]);
            $item->unit_price = $product->currentPrice();
            $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + (int) $quantity);
            $item->save();
        });

        $request->session()->forget('cart.items');
    }

    private function storeIntendedUrl(Request $request): void
    {
        $intended = (string) $request->query('intended', '');

        if ($intended === '') {
            return;
        }

        if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            $intended = url($intended);
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $intendedHost = parse_url($intended, PHP_URL_HOST);

        if (! $intendedHost || $intendedHost !== $appHost) {
            return;
        }

        if (str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/admin')) {
            return;
        }

        $request->session()->put('url.intended', $intended);
    }

    private function parseCartKey(string $key): array
    {
        $parts = explode(':', $key, 3);

        if (count($parts) === 3) {
            return [$parts[0], (int) $parts[1], rawurldecode($parts[2])];
        }

        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        return [CartItem::SOURCE_ECLISE, $key];
    }
}
