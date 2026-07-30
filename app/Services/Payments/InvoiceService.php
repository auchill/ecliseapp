<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Repair;
use App\Models\RepairConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(
        private readonly PaymentSettingsService $settings,
        private readonly PaymentBalanceService $balances,
        private readonly PaymentAuditLogger $audit,
    ) {}

    public function createShopCheckoutInvoice(
        Cart $cart,
        array $customerData,
        array $fulfillment,
        array $totals,
        iterable $items,
    ): Invoice {
        return DB::transaction(function () use ($cart, $customerData, $fulfillment, $totals, $items): Invoice {
            $cart = Cart::query()->with('customer')->lockForUpdate()->findOrFail($cart->id);

            $attributes = [
                'subtotal' => (float) $totals['subtotal'],
                'tax_amount' => (float) $totals['tax'],
                'shipping_amount' => (float) ($fulfillment['shipping_cost'] ?? 0),
                'total' => (float) $totals['total'],
                'billing_snapshot' => $this->customerSnapshot($cart->customer, $customerData),
                'shipping_snapshot' => $fulfillment,
            ];

            $invoice = $this->firstOpenInvoice($cart, InvoiceType::ShopOrder->value)
                ?: $this->baseInvoice($cart, $cart->customer, InvoiceType::ShopOrder->value, $attributes);
            $this->refreshEditableInvoice($invoice, $attributes);

            $this->replaceItems($invoice, collect($items)->map(function (array $item): array {
                return [
                    'item_type' => $item['source'] ?? 'cart_item',
                    'item_id' => $item['source_id'] ?? null,
                    'sku' => $item['source_sku'] ?? null,
                    'name' => $item['display_name'] ?? $item['source_sku'] ?? 'Shop item',
                    'description' => null,
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ];
            })->push([
                'item_type' => 'tax',
                'name' => 'HST',
                'quantity' => 1,
                'unit_price' => (float) $totals['tax'],
                'line_total' => (float) $totals['tax'],
            ])->when((float) ($fulfillment['shipping_cost'] ?? 0) > 0, fn (Collection $items): Collection => $items->push([
                'item_type' => 'shipping',
                'name' => $fulfillment['shipping_method_name'] ?? 'Shipping',
                'quantity' => 1,
                'unit_price' => (float) $fulfillment['shipping_cost'],
                'line_total' => (float) $fulfillment['shipping_cost'],
            ]))->all());

            $this->audit->log('invoice.created', $invoice, null, ['invoice_id' => $invoice->id]);

            return $this->balances->synchronizeInvoice($invoice);
        });
    }

    public function createShopOrderInvoice(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            $order = Order::query()->with('customer', 'items', 'shipping')->lockForUpdate()->findOrFail($order->id);

            $attributes = [
                'subtotal' => (float) $order->subtotal,
                'tax_amount' => (float) $order->tax,
                'shipping_amount' => (float) $order->shipping_cost,
                'total' => (float) $order->total,
                'billing_snapshot' => $this->customerSnapshot($order->customer),
                'shipping_snapshot' => [
                    'fulfillment_method' => $order->fulfillment_method,
                    'shipping_method_name' => $order->shipping_method_name,
                    'shipping_address' => $order->shipping?->shipping_address,
                ],
            ];

            $invoice = $this->firstOpenInvoice($order, InvoiceType::ShopOrder->value)
                ?: $this->baseInvoice($order, $order->customer, InvoiceType::ShopOrder->value, $attributes);
            $this->refreshEditableInvoice($invoice, $attributes);

            $this->replaceItems($invoice, $order->items->map(fn ($item): array => [
                'item_type' => $item->source,
                'item_id' => $item->source_id,
                'sku' => $item->source_sku,
                'name' => $item->display_name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->push([
                'item_type' => 'tax',
                'name' => 'HST',
                'quantity' => 1,
                'unit_price' => (float) $order->tax,
                'line_total' => (float) $order->tax,
            ])->when((float) $order->shipping_cost > 0, fn (Collection $items): Collection => $items->push([
                'item_type' => 'shipping',
                'name' => $order->shipping_method_name ?: 'Shipping',
                'quantity' => 1,
                'unit_price' => (float) $order->shipping_cost,
                'line_total' => (float) $order->shipping_cost,
            ]))->all());

            return $this->balances->synchronizeInvoice($invoice);
        });
    }

    public function attachPaymentInvoiceToOrder(Invoice $invoice, Order $order): Invoice
    {
        $invoice->forceFill([
            'invoiceable_type' => $order->getMorphClass(),
            'invoiceable_id' => $order->id,
            'customer_id' => $order->customer_id,
        ])->save();

        return $invoice->fresh('items', 'payments');
    }

    public function createRepairDepositInvoice(RepairConversation $conversation): Invoice
    {
        return DB::transaction(function () use ($conversation): Invoice {
            $conversation = RepairConversation::query()
                ->with('repair.customer', 'partGroups.selections.option')
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $total = round((float) $conversation->final_total, 2);
            $deposit = $this->settings->repairDepositAmount($total);

            if ($deposit <= 0) {
                throw new InvalidArgumentException('No repair deposit is required by the current settings.');
            }

            $invoice = $this->firstOpenInvoice($conversation->repair, InvoiceType::RepairDeposit->value)
                ?: $this->baseInvoice($conversation->repair, $conversation->customer, InvoiceType::RepairDeposit->value, [
                    'subtotal' => $deposit,
                    'total' => $deposit,
                    'billing_snapshot' => $this->customerSnapshot($conversation->customer),
                    'terms_snapshot' => [
                        'proposal_version' => $conversation->accepted_proposal_version,
                        'proposal_total' => $total,
                        'deposit_type' => $this->settings->get('repair_deposit_type'),
                    ],
                ]);

            $this->replaceItems($invoice, [[
                'item_type' => 'repair_deposit',
                'name' => 'Repair deposit',
                'description' => 'Deposit for accepted repair proposal #'.$conversation->accepted_proposal_version,
                'quantity' => 1,
                'unit_price' => $deposit,
                'line_total' => $deposit,
                'metadata' => [
                    'proposal_total' => $total,
                    'proposal_version' => $conversation->accepted_proposal_version,
                ],
            ]]);

            return $this->balances->synchronizeInvoice($invoice);
        });
    }

    public function createRepairFinalInvoice(Repair $repair): Invoice
    {
        return DB::transaction(function () use ($repair): Invoice {
            $repair = Repair::query()->with('customer', 'repairConversation')->lockForUpdate()->findOrFail($repair->id);
            $total = round((float) ($repair->total_amount ?: $repair->repair_total), 2);
            $paid = round((float) $repair->payments()->whereIn('status', ['paid', 'succeeded'])->sum('amount'), 2);
            $balance = round(max(0, $total - $paid), 2);

            $attributes = [
                'subtotal' => $balance,
                'total' => $balance,
                'billing_snapshot' => $this->customerSnapshot($repair->customer),
                'terms_snapshot' => [
                    'repair_total' => $total,
                    'paid_to_date' => $paid,
                ],
            ];

            $invoice = $this->firstEditableInvoice($repair, InvoiceType::RepairFinal->value)
                ?: $this->baseInvoice($repair, $repair->customer, InvoiceType::RepairFinal->value, $attributes);
            $this->refreshEditableInvoice($invoice, $attributes);

            $this->replaceItems($invoice, [[
                'item_type' => 'repair_final_balance',
                'name' => 'Repair final balance',
                'quantity' => 1,
                'unit_price' => $balance,
                'line_total' => $balance,
            ]]);

            return $this->balances->synchronizeInvoice($invoice);
        });
    }

    public function createRepairAdditionalChargeInvoice(Repair $repair, string $description, float $amount): Invoice
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Additional charge amount must be greater than zero.');
        }

        return DB::transaction(function () use ($repair, $description, $amount): Invoice {
            $repair = Repair::query()->with('customer')->lockForUpdate()->findOrFail($repair->id);

            $invoice = $this->baseInvoice($repair, $repair->customer, InvoiceType::RepairAdditionalCharge->value, [
                'subtotal' => $amount,
                'total' => $amount,
                'billing_snapshot' => $this->customerSnapshot($repair->customer),
            ]);

            $this->replaceItems($invoice, [[
                'item_type' => 'repair_additional_charge',
                'name' => 'Repair additional charge',
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
            ]]);

            return $this->balances->synchronizeInvoice($invoice);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value], true)) {
            throw new InvalidArgumentException('Cancelled invoices cannot be issued.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => $invoice->issued_at ?: now(),
            'due_at' => $invoice->due_at ?: now()->addDays((int) $this->settings->get('invoice_due_days', 14)),
        ]);

        $this->audit->log('invoice.issued', $invoice, auth()->user());

        return $invoice->fresh();
    }

    public function cancel(Invoice $invoice, string $reason = ''): Invoice
    {
        if ((float) $invoice->amount_paid > 0) {
            throw new InvalidArgumentException('Invoices with payments cannot be silently cancelled.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Cancelled->value,
            'cancelled_at' => now(),
            'notes' => trim($invoice->notes."\n".$reason),
        ]);

        $this->audit->log('invoice.cancelled', $invoice, auth()->user(), ['reason' => $reason]);

        return $invoice->fresh();
    }

    private function baseInvoice(Model $invoiceable, ?Customer $customer, string $type, array $attributes): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoiceable_type' => $invoiceable->getMorphClass(),
            'invoiceable_id' => $invoiceable->getKey(),
            'customer_id' => $customer?->id,
            'type' => $type,
            'status' => InvoiceStatus::Issued->value,
            'currency' => strtolower((string) $this->settings->get('default_currency', 'CAD')),
            'discount_amount' => 0,
            'fee_amount' => 0,
            'amount_paid' => 0,
            'refunded_amount' => 0,
            'balance_due' => $attributes['total'] ?? 0,
            'issued_at' => now(),
            'due_at' => now()->addDays((int) $this->settings->get('invoice_due_days', 14)),
            'terms_snapshot' => [
                'terms' => $this->settings->get('invoice_terms'),
            ],
        ], $attributes));
    }

    private function firstOpenInvoice(Model $invoiceable, string $type): ?Invoice
    {
        return Invoice::query()
            ->whereMorphedTo('invoiceable', $invoiceable)
            ->where('type', $type)
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value])
            ->oldest('id')
            ->first();
    }

    private function replaceItems(Invoice $invoice, array $items): void
    {
        if ((float) $invoice->amount_paid > 0) {
            throw new InvalidArgumentException('Paid invoices cannot be edited.');
        }

        $invoice->items()->delete();

        foreach (array_values($items) as $index => $item) {
            if ((float) ($item['line_total'] ?? 0) <= 0) {
                continue;
            }

            $invoice->items()->create(array_merge([
                'quantity' => 1,
                'unit_price' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
                'sort_order' => $index + 1,
            ], $item));
        }
    }

    private function refreshEditableInvoice(Invoice $invoice, array $attributes): void
    {
        if ((float) $invoice->amount_paid > 0) {
            return;
        }

        $invoice->forceFill(array_merge([
            'balance_due' => $attributes['total'] ?? $invoice->total,
        ], $attributes))->save();
    }

    private function firstEditableInvoice(Model $invoiceable, string $type): ?Invoice
    {
        return Invoice::query()
            ->whereMorphedTo('invoiceable', $invoiceable)
            ->where('type', $type)
            ->where('amount_paid', '<=', 0)
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value])
            ->oldest('id')
            ->first();
    }

    private function customerSnapshot(?Customer $customer, array $overrides = []): array
    {
        return array_filter([
            'full_name' => $overrides['full_name'] ?? $customer?->full_name,
            'email' => $overrides['email'] ?? $customer?->email,
            'phone' => $overrides['phone'] ?? $customer?->phone,
            'street_address' => $customer?->street_address,
            'address_line_2' => $customer?->address_line_2,
            'city' => $customer?->city,
            'province' => $customer?->province,
            'postal_code' => $customer?->postal_code,
            'country' => $customer?->country,
        ], fn ($value): bool => filled($value));
    }
}
