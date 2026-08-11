<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MobileSentrixDevice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\PaymentFinalizer;
use App\Services\Payments\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Shop scenarios covering the states the payment module has to handle.
 *
 * Paid orders are driven through PaymentFinalizer rather than hand-written, so invoices,
 * receipts, transactions, inventory and the MobileSentrix procurement buffer all end up
 * internally consistent — exactly as a real webhook would leave them.
 */
class ShopOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Seeding must not send mail to real inboxes.
        Mail::fake();

        $finalizer = app(PaymentFinalizer::class);
        $invoices = app(InvoiceService::class);

        $product = Product::query()->where('quantity', '>', 3)->first();
        $device = MobileSentrixDevice::query()->available()->first();
        $secondDevice = MobileSentrixDevice::query()->available()->skip(1)->first();

        if (! $product || ! $device) {
            $this->command?->warn('ShopOrderSeeder skipped: no products or MobileSentrix devices available.');

            return;
        }

        $shipping = ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->first();

        // 1. Paid Stripe order mixing an Eclise product with two MobileSentrix devices.
        //    Proves Eclise stock stays out of procurement while MobileSentrix items enter it.
        $this->settledOrder(
            $finalizer,
            $invoices,
            'amara@example.com',
            [
                $this->line($product, 1),
                $this->deviceLine($device, 2),
            ],
            gateway: 'stripe',
            fulfillment: 'pickup',
            shipping: null,
        );

        // 2. Paid Interac order shipped, with a second MobileSentrix device.
        if ($secondDevice) {
            $this->settledOrder(
                $finalizer,
                $invoices,
                'daniel@example.com',
                [$this->deviceLine($secondDevice, 1)],
                gateway: 'interac',
                fulfillment: 'shipping',
                shipping: $shipping,
            );
        }

        // 3. A second paid Stripe order with a multi-unit device line, so the procurement cart
        //    has a requirement that can be partially ordered.
        $thirdDevice = MobileSentrixDevice::query()->available()->skip(2)->first();

        if ($thirdDevice) {
            $this->settledOrder(
                $finalizer,
                $invoices,
                'priya@example.com',
                [$this->deviceLine($thirdDevice, 3)],
                gateway: 'stripe',
                fulfillment: 'pickup',
                shipping: null,
            );
        }

        // 4. Pending Stripe checkout that never completed — the customer can retry.
        $this->pendingOrder($invoices, 'priya@example.com', [$this->line($product, 1)]);

        // 5. An active cart mid-shopping, so the cart and checkout screens have content.
        $this->activeCart('marcus@example.com', $product, $device);
    }

    private function line(Product $product, int $quantity): array
    {
        $price = (float) ($product->sale_price ?: $product->regular_price ?: 0);

        return [
            'source_id' => $product->id,
            'source_sku' => $product->sku,
            'source' => CartItem::SOURCE_ECLISE,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => round($price * $quantity, 2),
        ];
    }

    private function deviceLine(MobileSentrixDevice $device, int $quantity): array
    {
        $price = (float) ($device->displayPrice() ?? 0);

        return [
            'source_id' => (int) $device->entity_id,
            'source_sku' => $device->sku,
            'source' => CartItem::SOURCE_MOBILESENTRIX,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => round($price * $quantity, 2),
        ];
    }

    private function settledOrder(
        PaymentFinalizer $finalizer,
        InvoiceService $invoices,
        string $email,
        array $items,
        string $gateway,
        string $fulfillment,
        ?ShippingMethod $shipping,
    ): void {
        [$cart, $customer, $payment] = $this->checkout($invoices, $email, $items, $gateway, $fulfillment, $shipping);

        $finalizer->markPaid($payment, array_merge([
            'gateway_reference_id' => strtoupper($gateway).'-SEED-'.$cart->id,
            'gateway_payment_id' => strtoupper($gateway).'-SEED-'.$cart->id,
            'paid_at' => now()->subDays(random_int(1, 12)),
        ], $this->manualAttribution($gateway)));
    }

    /**
     * Manual and Interac payments are only valid with a recorded receiver and verifier; the
     * payment verifier and reconciliation both flag settled manual payments without them.
     */
    private function manualAttribution(string $gateway): array
    {
        if (in_array($gateway, ['stripe', 'paypal'], true)) {
            return [];
        }

        $adminId = User::query()->where('role', 'admin')->value('id');

        return [
            'received_by' => $adminId,
            'verified_by' => $gateway === 'interac' ? $adminId : null,
            'verified_at' => $gateway === 'interac' ? now() : null,
        ];
    }

    private function pendingOrder(InvoiceService $invoices, string $email, array $items): void
    {
        $this->checkout($invoices, $email, $items, 'stripe', 'pickup', null);
    }

    /**
     * @return array{0: Cart, 1: Customer, 2: Payment}
     */
    private function checkout(
        InvoiceService $invoices,
        string $email,
        array $items,
        string $gateway,
        string $fulfillment,
        ?ShippingMethod $shipping,
    ): array {
        $user = User::query()->where('email', $email)->firstOrFail();
        $customer = Customer::forUser($user);
        $cart = $customer->getOrCreateActiveCart();

        foreach ($items as $item) {
            $cart->items()->create([
                'source_id' => $item['source_id'],
                'source_sku' => $item['source_sku'],
                'source' => $item['source'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        $subtotal = round(collect($items)->sum('line_total'), 2);
        $shippingCost = $fulfillment === 'shipping' ? (float) ($shipping?->base_cost ?? 0) : 0.0;
        $tax = round($subtotal * 0.13, 2);
        $total = round($subtotal + $tax + $shippingCost, 2);

        $fulfillmentData = [
            'fulfillment_method' => $fulfillment,
            'shipping_method_id' => $fulfillment === 'shipping' ? $shipping?->id : null,
            'shipping_method_name' => $fulfillment === 'shipping' ? $shipping?->name : null,
            'shipping_base_cost' => $shippingCost,
            'shipping_discount_amount' => 0,
            'shipping_cost' => $shippingCost,
            'recipient_name' => $fulfillment === 'shipping' ? $customer->full_name : null,
            'address_line1' => $fulfillment === 'shipping' ? $customer->street_address : null,
            'address_line2' => $fulfillment === 'shipping' ? $customer->address_line_2 : null,
            'city' => $fulfillment === 'shipping' ? $customer->city : null,
            'province' => $fulfillment === 'shipping' ? $customer->province : null,
            'postal_code' => $fulfillment === 'shipping' ? $customer->postal_code : null,
            'country' => $fulfillment === 'shipping' ? $customer->country : null,
            'notes' => null,
        ];

        $invoice = $invoices->createShopCheckoutInvoice(
            $cart,
            ['full_name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone],
            $fulfillmentData,
            ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total],
            $this->snapshotItems($items),
        );

        $payment = $cart->payments()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'source' => 'shop',
            'gateway' => $gateway,
            'method' => $gateway,
            'provider' => $gateway === 'stripe' ? 'stripe' : 'manual',
            'purpose' => 'shop_order',
            'amount' => $total,
            'currency' => 'cad',
            'status' => $gateway === 'interac' ? 'pending_verification' : 'pending',
            'submitted_at' => $gateway === 'interac' ? now() : null,
            'checkout_data' => [
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'cart_reference' => $cart->id,
                'customer' => ['full_name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone],
                'fulfillment' => $fulfillmentData,
                'totals' => ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total],
                'items' => $this->snapshotItems($items),
            ],
        ]);

        return [$cart, $customer, $payment];
    }

    private function snapshotItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'source_id' => $item['source_id'],
                'source_sku' => $item['source_sku'],
                'source' => $item['source'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ])
            ->all();
    }

    private function activeCart(string $email, Product $product, MobileSentrixDevice $device): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $cart = Customer::forUser($user)->getOrCreateActiveCart();

        $cart->items()->create([
            'source_id' => $product->id,
            'source_sku' => $product->sku,
            'source' => CartItem::SOURCE_ECLISE,
            'quantity' => 1,
            'unit_price' => (float) ($product->sale_price ?: $product->regular_price ?: 0),
        ]);

        $cart->items()->create([
            'source_id' => (int) $device->entity_id,
            'source_sku' => $device->sku,
            'source' => CartItem::SOURCE_MOBILESENTRIX,
            'quantity' => 1,
            'unit_price' => (float) ($device->displayPrice() ?? 0),
        ]);
    }
}
