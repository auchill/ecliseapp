<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Services\ShippingCostService;
use App\Services\SquarePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function show(Request $request, ShippingCostService $shippingCosts)
    {
        $cart = $this->activeCart($request)->load('items.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        $subtotal = $cart->subtotal();
        $shippingMethods = $shippingCosts->getAvailableShippingMethods();
        $shippingQuotes = $shippingMethods
            ->mapWithKeys(fn ($method) => [
                (string) $method->id => $shippingCosts->calculateForShopOrder($subtotal, $method->id),
            ])
            ->all();

        return view('checkout.show', [
            'cart' => $cart,
            'pickupQuote' => $shippingCosts->calculateForFulfillment('pickup', $subtotal, null),
            'shippingMethods' => $shippingMethods,
            'shippingQuotes' => $shippingQuotes,
        ]);
    }

    public function store(CheckoutRequest $request, SquarePaymentService $payments, ShippingCostService $shippingCosts)
    {
        $cart = $this->activeCart($request)->load('items.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        $data = $request->validated();

        try {
            $shippingQuote = $shippingCosts->calculateForFulfillment(
                $data['fulfillment_method'],
                $cart->subtotal(),
                isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['shipping_method_id' => $exception->getMessage()])
                ->withInput();
        }

        $data = $this->normalizeFulfillmentData($data, $shippingQuote);
        $payment = $payments->createCheckout($cart, $data);

        $order = DB::transaction(function () use ($cart, $data, $payment, $request): Order {
            $subtotal = $cart->subtotal();
            $tax = round($subtotal * 0.13, 2);
            $total = $subtotal + $tax + $data['shipping_cost'];

            $order = Order::query()->create([
                'user_id' => $request->user()->id,
                'order_number' => $this->generateOrderNumber(),
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $this->legacyAddressValue($data),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'Pending',
                'payment_provider' => $payment['provider'],
                'payment_reference' => $payment['reference'],
                'fulfillment_method' => $data['fulfillment_method'],
                'payment_status' => 'Pending',
                'shipping_full_name' => $data['shipping_full_name'] ?? null,
                'shipping_phone' => $data['shipping_phone'] ?? null,
                'shipping_email' => $data['shipping_email'] ?? null,
                'shipping_address_line1' => $data['shipping_address_line1'] ?? null,
                'shipping_address_line2' => $data['shipping_address_line2'] ?? null,
                'shipping_city' => $data['shipping_city'] ?? null,
                'shipping_province' => $data['shipping_province'] ?? null,
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'shipping_country' => $data['shipping_country'] ?? null,
                'shipping_method_id' => $data['shipping_method_id'],
                'shipping_method_name' => $data['shipping_method_name'],
                'shipping_delivery_days' => $data['shipping_delivery_days'],
                'shipping_base_cost' => $data['shipping_base_cost'],
                'shipping_discount_amount' => $data['shipping_discount_amount'],
                'shipping_cost' => $data['shipping_cost'],
                'delivery_carrier' => $data['delivery_carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'customer_notes' => $data['notes'] ?? $payment['message'],
                'notes' => $data['notes'] ?? $payment['message'],
            ]);

            $order->statusUpdates()->create([
                'status' => 'Pending',
                'note' => $order->isShipping() && $order->shipping_method_name
                    ? "Order placed for {$order->shipping_method_name}."
                    : 'Order placed for store pickup.',
                'is_customer_visible' => true,
                'delivery_carrier' => $order->delivery_carrier,
                'tracking_number' => $order->tracking_number,
                'created_by' => $request->user()->id,
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

    private function normalizeFulfillmentData(array $data, array $shippingQuote): array
    {
        $data = array_merge($data, $shippingQuote);

        if ($data['fulfillment_method'] === 'pickup') {
            foreach ([
                'shipping_full_name',
                'shipping_phone',
                'shipping_email',
                'shipping_address_line1',
                'shipping_address_line2',
                'shipping_city',
                'shipping_province',
                'shipping_postal_code',
                'shipping_country',
                'delivery_carrier',
                'tracking_number',
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function legacyAddressValue(array $data): ?string
    {
        if ($data['fulfillment_method'] !== 'shipping') {
            return null;
        }

        return implode("\n", array_filter([
            $data['shipping_full_name'] ?? null,
            $data['shipping_address_line1'] ?? null,
            $data['shipping_address_line2'] ?? null,
            trim(implode(', ', array_filter([
                $data['shipping_city'] ?? null,
                $data['shipping_province'] ?? null,
                $data['shipping_postal_code'] ?? null,
            ]))),
            $data['shipping_country'] ?? null,
        ]));
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
