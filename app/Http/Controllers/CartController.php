<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MobileSentrixDevice;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
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

        if (! $request->user()?->isCustomer()) {
            return $this->authRequired($request);
        }

        abort_unless($product->status === 'Active' && $product->quantity > 0, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        $cart = $this->activeCart($request);
        $item = $cart->items()->firstOrNew([
            'product_id' => $this->ecliseProductId($product),
            'item_source' => CartItem::SOURCE_ECLISE,
        ]);
        $item->unit_price = $product->currentPrice();
        $item->quantity = min($product->quantity, ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->save();

        return back()->with('status', 'Product added to cart.');
    }

    public function storeDevice(Request $request, MobileSentrixDevice $device)
    {
        abort_if($request->user()?->isAdmin(), 403);

        if (! $request->user()?->isCustomer()) {
            return $this->authRequired($request);
        }

        abort_unless($device->availableQuantity() > 0, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$device->availableQuantity()],
        ]);

        $cart = $this->activeCart($request);
        $item = $cart->items()->firstOrNew([
            'product_id' => $device->cartProductId(),
            'item_source' => CartItem::SOURCE_MOBILESENTRIX,
        ]);
        $item->unit_price = $device->displayPrice() ?? 0;
        $item->quantity = min($device->availableQuantity(), ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->save();

        return back()->with('status', 'Certified pre-owned device added to cart.');
    }

    public function storeDevices(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isAdmin(), 403);

        if (! $request->user()?->isCustomer()) {
            return $this->authRequired($request);
        }

        $data = $request->validate([
            'devices' => ['required', 'array'],
            'devices.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $selected = collect($data['devices'])
            ->map(fn ($quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0);

        if ($selected->isEmpty()) {
            return back()->withErrors(['devices' => 'Select at least one device quantity before adding to cart.']);
        }

        $cart = $this->activeCart($request);
        $added = 0;

        foreach ($selected as $deviceId => $quantity) {
            $device = MobileSentrixDevice::query()->find($deviceId);

            if (! $device || $device->availableQuantity() <= 0) {
                continue;
            }

            $item = $cart->items()->firstOrNew([
                'product_id' => $device->cartProductId(),
                'item_source' => CartItem::SOURCE_MOBILESENTRIX,
            ]);
            $item->unit_price = $device->displayPrice() ?? 0;
            $item->quantity = min($device->availableQuantity(), ($item->exists ? $item->quantity : 0) + $quantity);
            $item->save();
            $added++;
        }

        if ($added === 0) {
            return back()->withErrors(['devices' => 'Selected devices are no longer available.']);
        }

        return back()->with('status', $added.' certified pre-owned device '.($added === 1 ? 'item was' : 'items were').' added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        if ($request->user()) {
            $cart = $this->activeCart($request);
            $cart->items()
                ->where('product_id', $this->ecliseProductId($product))
                ->where('item_source', CartItem::SOURCE_ECLISE)
                ->update([
                    'quantity' => $data['quantity'],
                ]);
        } else {
            $cart = $this->normalizedSessionCart($request);
            $cart[$this->cartKey(CartItem::SOURCE_ECLISE, $this->ecliseProductId($product))] = $data['quantity'];
            $request->session()->put('cart.items', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Cart updated.');
    }

    public function updateItem(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'item_key' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$source, $productId] = $this->parseCartKey($data['item_key']);
        $maxQuantity = $this->maxQuantityFor($source, $productId);

        abort_unless($maxQuantity > 0, 404);

        $quantity = min($maxQuantity, $data['quantity']);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('product_id', $productId)
                ->where('item_source', $source)
                ->update(['quantity' => $quantity]);
        } else {
            $cart = $this->normalizedSessionCart($request);
            $cart[$this->cartKey($source, $productId)] = $quantity;
            $request->session()->put('cart.items', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('product_id', $this->ecliseProductId($product))
                ->where('item_source', CartItem::SOURCE_ECLISE)
                ->delete();
        } else {
            $cart = $this->normalizedSessionCart($request);
            unset($cart[$this->cartKey(CartItem::SOURCE_ECLISE, $this->ecliseProductId($product))]);
            $request->session()->put('cart.items', $cart);
        }

        return redirect()->route('cart.index')->with('status', 'Item removed from cart.');
    }

    public function destroyItem(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'item_key' => ['required', 'string'],
        ]);

        [$source, $productId] = $this->parseCartKey($data['item_key']);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('product_id', $productId)
                ->where('item_source', $source)
                ->delete();
        } else {
            $cart = $this->normalizedSessionCart($request);
            unset($cart[$this->cartKey($source, $productId)]);
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

    private function authRequired(Request $request): RedirectResponse
    {
        return back()
            ->with('auth_required', true)
            ->with('auth_required_url', $request->headers->get('referer') ?: url()->previous() ?: route('shop.index'));
    }

    private function cartItems(Request $request)
    {
        if ($request->user()) {
            return $this->activeCart($request)
                ->items
                ->map(fn (CartItem $item) => $this->displayCartItem($item))
                ->filter()
                ->values();
        }

        $sessionItems = collect($this->normalizedSessionCart($request))
            ->filter(fn ($quantity) => (int) $quantity > 0);

        if ($sessionItems->isEmpty()) {
            return collect();
        }

        return $sessionItems
            ->map(function ($quantity, $key) {
                [$source, $productId] = $this->parseCartKey((string) $key);
                $cartItem = new CartItem([
                    'product_id' => $productId,
                    'item_source' => $source,
                    'quantity' => (int) $quantity,
                    'unit_price' => $this->unitPriceFor($source, $productId),
                ]);

                return $this->displayCartItem($cartItem);
            })
            ->filter()
            ->values();
    }

    private function displayCartItem(CartItem $item): ?array
    {
        $purchasable = $item->purchasable();

        if (! $purchasable) {
            return null;
        }

        $maxQuantity = $item->maxQuantity();
        $quantity = min($item->quantity, $maxQuantity);
        $unitPrice = (float) $item->unit_price;

        return [
            'cart_key' => $this->cartKey($item->item_source ?: CartItem::SOURCE_ECLISE, (string) $item->product_id),
            'source' => $item->item_source ?: CartItem::SOURCE_ECLISE,
            'name' => $item->displayName(),
            'sku' => $item->displaySku(),
            'image_url' => $item->displayImageUrl(),
            'quantity' => $quantity,
            'max_quantity' => $maxQuantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ];
    }

    private function normalizedSessionCart(Request $request): array
    {
        $items = collect($request->session()->get('cart.items', []))
            ->mapWithKeys(function ($quantity, $key): array {
                if ((int) $quantity <= 0) {
                    return [];
                }

                if (str_contains((string) $key, ':')) {
                    return [(string) $key => (int) $quantity];
                }

                return [$this->cartKey(CartItem::SOURCE_ECLISE, $this->ecliseProductId((string) $key)) => (int) $quantity];
            })
            ->all();

        $request->session()->put('cart.items', $items);

        return $items;
    }

    private function cartKey(string $source, string $productId): string
    {
        return $source.':'.$productId;
    }

    private function parseCartKey(string $key): array
    {
        if (! str_contains($key, ':')) {
            return [CartItem::SOURCE_ECLISE, $this->ecliseProductId($key)];
        }

        [$source, $productId] = explode(':', $key, 2);

        return [$source ?: CartItem::SOURCE_ECLISE, $productId];
    }

    private function ecliseProductId(Product|string|int $product): string
    {
        $id = $product instanceof Product ? $product->id : $product;

        return str_starts_with((string) $id, 'ecl') ? (string) $id : 'ecl'.$id;
    }

    private function maxQuantityFor(string $source, string $productId): int
    {
        if ($source === CartItem::SOURCE_MOBILESENTRIX) {
            return MobileSentrixDevice::query()
                ->where('entity_id', $productId)
                ->orWhere('sku', $productId)
                ->first()?->availableQuantity() ?? 0;
        }

        $id = preg_replace('/^ecl/i', '', $productId);

        return is_numeric($id) ? (int) (Product::query()->find((int) $id)?->quantity ?? 0) : 0;
    }

    private function unitPriceFor(string $source, string $productId): float
    {
        if ($source === CartItem::SOURCE_MOBILESENTRIX) {
            return (float) (MobileSentrixDevice::query()
                ->where('entity_id', $productId)
                ->orWhere('sku', $productId)
                ->first()?->displayPrice() ?? 0);
        }

        $id = preg_replace('/^ecl/i', '', $productId);

        return is_numeric($id) ? (float) (Product::query()->find((int) $id)?->currentPrice() ?? 0) : 0;
    }
}
