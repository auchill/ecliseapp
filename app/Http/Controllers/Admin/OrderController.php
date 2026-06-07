<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with('latestPayment')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'user', 'statusUpdates', 'latestPayment'),
            'statuses' => Order::STATUSES,
            'fulfillmentMethods' => Order::FULFILLMENT_METHODS,
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'payment_status' => ['required', 'string', 'max:80'],
            'fulfillment_method' => ['required', Rule::in(array_keys(Order::FULFILLMENT_METHODS))],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'shipping_full_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'shipping_address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'delivery_carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_notes' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'status_note' => ['nullable', 'string'],
            'is_customer_visible' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $statusChanged = $order->status !== $data['status'];
        $data = $this->normalizeFulfillmentData($data);
        $data['total'] = (float) $order->subtotal + (float) $order->tax + (float) $data['shipping_cost'];
        $data['address'] = $this->legacyAddressValue($data);

        $order->update($data);

        if ($statusChanged || filled($data['status_note'] ?? null)) {
            $statusUpdate = $order->statusUpdates()->create([
                'status' => $order->status,
                'note' => $data['status_note'] ?? $order->customer_notes,
                'is_customer_visible' => $request->boolean('is_customer_visible', true),
                'delivery_carrier' => $order->delivery_carrier,
                'tracking_number' => $order->tracking_number,
                'created_by' => $request->user()->id,
            ]);

            Mail::to($order->email)->send(new OrderStatusUpdatedMail($order->fresh(), $statusUpdate));
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated.');
    }

    private function normalizeFulfillmentData(array $data): array
    {
        if ($data['fulfillment_method'] === 'pickup') {
            $data['shipping_cost'] = 0;
            $data['shipping_base_cost'] = 0;
            $data['shipping_discount_amount'] = 0;

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
                'shipping_method_id',
                'shipping_method_name',
                'shipping_delivery_days',
                'delivery_carrier',
                'tracking_number',
                'tracking_notes',
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
}
