<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Services\PaymentGatewayService;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function show(Request $request, ShippingCostService $shippingCosts)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $cart = $this->activeCart($request)->load('items');
        $cartItems = $this->cartItems($cart);

        if ($cartItems->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        $subtotal = (float) $cartItems->sum('line_total');
        $shippingMethods = $shippingCosts->getAvailableShippingMethods();
        $shippingQuotes = $shippingMethods
            ->mapWithKeys(fn ($method) => [
                (string) $method->id => $shippingCosts->calculateForShopOrder($subtotal, $method->id),
            ])
            ->all();

        return view('checkout.show', [
            'cart' => $cart,
            'cartItems' => $cartItems,
            'customerProfile' => $request->user()->customer,
            'pickupQuote' => $shippingCosts->calculateForFulfillment('pickup', $subtotal, null),
            'shippingMethods' => $shippingMethods,
            'shippingQuotes' => $shippingQuotes,
        ]);
    }

    public function store(
        CheckoutRequest $request,
        ShippingCostService $shippingCosts,
        PaymentGatewayService $paymentGateways,
    ) {
        abort_if($request->user()?->isAdmin(), 403);

        $cart = $this->activeCart($request)->load('items');
        $cartItems = $this->cartItems($cart);

        if ($cartItems->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Add an item before checkout.');
        }

        $data = $request->validated();

        try {
            $shippingQuote = $shippingCosts->calculateForFulfillment(
                $data['fulfillment_method'],
                (float) $cartItems->sum('line_total'),
                isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['shipping_method_id' => $exception->getMessage()])
                ->withInput();
        }

        $data = $this->normalizeFulfillmentData($data, $shippingQuote);
        $subtotal = round((float) $cartItems->sum('line_total'), 2);
        $tax = round($subtotal * 0.13, 2);
        $total = round($subtotal + $tax + (float) $data['shipping_cost'], 2);

        $payment = $cart->payments()->create([
            'source' => 'shop',
            'gateway' => $data['payment_gateway'],
            'amount' => $total,
            'currency' => 'cad',
            'status' => 'pending',
            'checkout_data' => [
                'user_id' => $request->user()->id,
                'customer_id' => $cart->customer_id,
                'cart_id' => $cart->id,
                'customer' => [
                    'full_name' => $data['customer_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ],
                'fulfillment' => $data,
                'totals' => [
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ],
                'items' => $cartItems
                    ->map(fn (array $item): array => Arr::only($item, [
                        'source_id',
                        'source_sku',
                        'source',
                        'quantity',
                        'unit_price',
                        'line_total',
                    ]))
                    ->all(),
            ],
        ]);

        return redirect()->away($paymentGateways->createCheckout($payment));
    }

    public function confirmation(Order $order)
    {
        abort_unless(
            auth()->user()?->isCustomer()
            && $order->customer?->user_id === auth()->id(),
            403,
        );

        return view('checkout.confirmation', [
            'order' => $order->load('items', 'latestPayment'),
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

    private function activeCart(Request $request): Cart
    {
        return Customer::forUser($request->user())->getOrCreateActiveCart();
    }

    private function cartItems(Cart $cart)
    {
        return $cart->items
            ->map(function (CartItem $item): ?array {
                if (! $item->purchasable()) {
                    return null;
                }

                $quantity = min($item->quantity, $item->maxQuantity());
                $unitPrice = (float) $item->unit_price;

                if ($quantity <= 0 || $unitPrice < 0) {
                    return null;
                }

                return [
                    'source_id' => (int) $item->source_id,
                    'source_sku' => $item->source_sku,
                    'source' => $item->source,
                    'display_name' => $item->displayName(),
                    'display_image_url' => $item->displayImageUrl(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                ];
            })
            ->filter()
            ->values();
    }
}
