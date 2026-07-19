<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
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

        abort_unless($product->isAvailable(), 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        $this->addItem(
            $this->activeCart($request),
            CartItem::SOURCE_ECLISE,
            (int) $product->id,
            $product->sku,
            (int) $data['quantity'],
            $product->currentPrice(),
            (int) $product->quantity,
        );

        $message = 'Product added to cart.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return back()->with('status', $message);
    }

    public function storeDevice(Request $request, MobileSentrixDevice $device)
    {
        abort_if($request->user()?->isAdmin(), 403);

        if (! $request->user()?->isCustomer()) {
            return $this->authRequired($request);
        }

        abort_unless($device->entity_id && filled($device->sku) && $device->availableQuantity() > 0, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$device->availableQuantity()],
        ]);

        $this->addItem(
            $this->activeCart($request),
            CartItem::SOURCE_MOBILESENTRIX,
            (int) $device->entity_id,
            $device->sku,
            (int) $data['quantity'],
            $device->displayPrice() ?? 0,
            $device->availableQuantity(),
        );

        $message = 'Certified pre-owned device added to cart.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return back()->with('status', $message);
    }

    public function storeDevices(Request $request): RedirectResponse|JsonResponse
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
            return $this->cartValidationError($request, 'devices', 'Select at least one device quantity before adding to cart.');
        }

        $cart = $this->activeCart($request);
        $added = 0;

        foreach ($selected as $deviceId => $quantity) {
            $device = MobileSentrixDevice::query()->find($deviceId);

            if (! $device?->entity_id || blank($device->sku) || $device->availableQuantity() <= 0) {
                continue;
            }

            $this->addItem(
                $cart,
                CartItem::SOURCE_MOBILESENTRIX,
                (int) $device->entity_id,
                $device->sku,
                $quantity,
                $device->displayPrice() ?? 0,
                $device->availableQuantity(),
            );
            $added++;
        }

        if ($added === 0) {
            return $this->cartValidationError($request, 'devices', 'Selected devices are no longer available.');
        }

        $message = $added.' certified pre-owned device '.($added === 1 ? 'item was' : 'items were').' added to cart.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->quantity],
        ]);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('source', CartItem::SOURCE_ECLISE)
                ->where('source_id', $product->id)
                ->where('source_sku', $product->sku)
                ->update(['quantity' => $data['quantity']]);
        } else {
            $cart = $this->normalizedSessionCart($request);
            $cart[$this->cartKey(CartItem::SOURCE_ECLISE, $product->id, $product->sku)] = $data['quantity'];
            $request->session()->put('cart.items', $cart);
        }

        $message = 'Cart updated.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return redirect()->route('cart.index')->with('status', $message);
    }

    public function updateItem(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'item_key' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$source, $sourceId, $sourceSku] = $this->parseCartKey($data['item_key']);
        $maxQuantity = $this->maxQuantityFor($source, $sourceId, $sourceSku);

        abort_unless($maxQuantity > 0, 404);

        $quantity = min($maxQuantity, $data['quantity']);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('source', $source)
                ->where('source_id', $sourceId)
                ->where('source_sku', $sourceSku)
                ->update(['quantity' => $quantity]);
        } else {
            $cart = $this->normalizedSessionCart($request);
            $cart[$this->cartKey($source, $sourceId, $sourceSku)] = $quantity;
            $request->session()->put('cart.items', $cart);
        }

        $message = 'Cart updated.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return redirect()->route('cart.index')->with('status', $message);
    }

    public function destroy(Request $request, Product $product)
    {
        abort_if($request->user()?->isAdmin(), 403);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('source', CartItem::SOURCE_ECLISE)
                ->where('source_id', $product->id)
                ->where('source_sku', $product->sku)
                ->delete();
        } else {
            $cart = $this->normalizedSessionCart($request);
            unset($cart[$this->cartKey(CartItem::SOURCE_ECLISE, $product->id, $product->sku)]);
            $request->session()->put('cart.items', $cart);
        }

        $message = 'Item removed from cart.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return redirect()->route('cart.index')->with('status', $message);
    }

    public function destroyItem(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'item_key' => ['required', 'string'],
        ]);

        [$source, $sourceId, $sourceSku] = $this->parseCartKey($data['item_key']);

        if ($request->user()) {
            $this->activeCart($request)->items()
                ->where('source', $source)
                ->where('source_id', $sourceId)
                ->where('source_sku', $sourceSku)
                ->delete();
        } else {
            $cart = $this->normalizedSessionCart($request);
            unset($cart[$this->cartKey($source, $sourceId, $sourceSku)]);
            $request->session()->put('cart.items', $cart);
        }

        $message = 'Item removed from cart.';

        if ($request->expectsJson()) {
            return $this->cartJson($request, $message);
        }

        return redirect()->route('cart.index')->with('status', $message);
    }

    private function addItem(
        Cart $cart,
        string $source,
        int $sourceId,
        string $sourceSku,
        int $quantity,
        float $unitPrice,
        int $maxQuantity,
    ): void {
        $item = $cart->items()->firstOrNew([
            'source' => $source,
            'source_id' => $sourceId,
            'source_sku' => $sourceSku,
        ]);
        $item->unit_price = max(0, $unitPrice);
        $item->quantity = min($maxQuantity, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();
    }

    private function activeCart(Request $request): Cart
    {
        return Customer::forUser($request->user())->getOrCreateActiveCart();
    }

    private function authRequired(Request $request): RedirectResponse|JsonResponse
    {
        $intendedUrl = $request->headers->get('referer') ?: url()->previous() ?: route('shop.index');

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => 'Customer sign in is required to continue.',
                'auth_required' => true,
                'login_url' => route('login', ['intended' => $intendedUrl]),
                'register_url' => route('register', ['intended' => $intendedUrl]),
            ], 401);
        }

        return back()
            ->with('auth_required', true)
            ->with('auth_required_url', $intendedUrl);
    }

    private function cartValidationError(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => 'Please review the highlighted fields and try again.',
                'errors' => [
                    $field => [$message],
                ],
            ], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    private function cartJson(Request $request, string $message): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'cart' => $this->cartPayload($request),
        ]);
    }

    private function cartPayload(Request $request): array
    {
        $items = $this->cartItems($request);
        $subtotal = (float) $items->sum('line_total');

        return [
            'count' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'subtotal_display' => '$'.number_format($subtotal, 2),
            'items' => $items
                ->map(fn (array $item): array => [
                    ...$item,
                    'unit_price_display' => '$'.number_format((float) $item['unit_price'], 2),
                    'line_total_display' => '$'.number_format((float) $item['line_total'], 2),
                ])
                ->values()
                ->all(),
        ];
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

        return collect($this->normalizedSessionCart($request))
            ->map(function ($quantity, $key): ?array {
                [$source, $sourceId, $sourceSku] = $this->parseCartKey((string) $key);
                $item = new CartItem([
                    'source' => $source,
                    'source_id' => $sourceId,
                    'source_sku' => $sourceSku,
                    'quantity' => (int) $quantity,
                    'unit_price' => $this->unitPriceFor($source, $sourceId, $sourceSku),
                ]);

                return $this->displayCartItem($item);
            })
            ->filter()
            ->values();
    }

    private function displayCartItem(CartItem $item): ?array
    {
        if (! $item->purchasable()) {
            return null;
        }

        $maxQuantity = $item->maxQuantity();
        $quantity = min($item->quantity, $maxQuantity);
        $unitPrice = (float) $item->unit_price;

        return [
            'cart_key' => $this->cartKey($item->source, $item->source_id, $item->source_sku),
            'source' => $item->source,
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

                [$source, $sourceId, $sourceSku] = $this->parseCartKey((string) $key);

                return [$this->cartKey($source, $sourceId, $sourceSku) => (int) $quantity];
            })
            ->all();

        $request->session()->put('cart.items', $items);

        return $items;
    }

    private function cartKey(string $source, int $sourceId, string $sourceSku): string
    {
        return implode(':', [$source, $sourceId, rawurlencode($sourceSku)]);
    }

    private function parseCartKey(string $key): array
    {
        $parts = explode(':', $key, 3);

        if (count($parts) === 3 && in_array($parts[0], CartItem::SOURCES, true) && is_numeric($parts[1])) {
            return [$parts[0], (int) $parts[1], rawurldecode($parts[2])];
        }

        if (count($parts) === 2 && $parts[0] === CartItem::SOURCE_MOBILESENTRIX) {
            $device = MobileSentrixDevice::query()
                ->where('entity_id', $parts[1])
                ->orWhere('sku', $parts[1])
                ->firstOrFail();

            return [$parts[0], (int) $device->entity_id, $device->sku];
        }

        $legacyId = count($parts) === 2 ? $parts[1] : $parts[0];
        $productId = preg_replace('/^ecl/i', '', $legacyId);
        $product = is_numeric($productId) ? Product::query()->findOrFail((int) $productId) : abort(404);

        return [CartItem::SOURCE_ECLISE, (int) $product->id, $product->sku];
    }

    private function maxQuantityFor(string $source, int $sourceId, string $sourceSku): int
    {
        if ($source === CartItem::SOURCE_MOBILESENTRIX) {
            return MobileSentrixDevice::query()
                ->where('entity_id', $sourceId)
                ->where('sku', $sourceSku)
                ->first()?->availableQuantity() ?? 0;
        }

        return (int) (Product::query()->whereKey($sourceId)->where('sku', $sourceSku)->value('quantity') ?? 0);
    }

    private function unitPriceFor(string $source, int $sourceId, string $sourceSku): float
    {
        if ($source === CartItem::SOURCE_MOBILESENTRIX) {
            return (float) (MobileSentrixDevice::query()
                ->where('entity_id', $sourceId)
                ->where('sku', $sourceSku)
                ->first()?->displayPrice() ?? 0);
        }

        return (float) (Product::query()->whereKey($sourceId)->where('sku', $sourceSku)->first()?->currentPrice() ?? 0);
    }
}
