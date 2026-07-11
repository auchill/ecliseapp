<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RepairStatusUpdatedMail;
use App\Models\Repair;
use App\Services\AddressSnapshotFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RepairController extends Controller
{
    public function index(Request $request)
    {
        $repairs = Repair::query()
            ->with('customer', 'latestPayment', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('repair_number', 'like', "%{$search}%")
                        ->orWhere('device_brand', 'like', "%{$search}%")
                        ->orWhere('device_model', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search): void {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.repairs.index', [
            'repairs' => $repairs,
            'statuses' => Repair::STATUS_LABELS,
            'paymentStatuses' => Repair::PAYMENT_STATUSES,
        ]);
    }

    public function show(Repair $repair)
    {
        return view('admin.repairs.show', [
            'repair' => $repair->load('customer', 'shipping', 'statusUpdates', 'latestPayment', 'quote', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory'),
            'statuses' => Repair::STATUS_LABELS,
            'paymentStatuses' => Repair::PAYMENT_STATUSES,
            'fulfillmentMethods' => Repair::FULFILLMENT_METHODS,
        ]);
    }

    public function update(Request $request, Repair $repair, AddressSnapshotFormatter $addressFormatter)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Repair::STATUS_LABELS))],
            'payment_status' => ['required', Rule::in(array_keys(Repair::PAYMENT_STATUSES))],
            'fulfillment_method' => ['required', Rule::in(array_keys(Repair::FULFILLMENT_METHODS))],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'recipient_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'recipient_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'delivery_carrier' => ['nullable', 'string', 'max:120'],
            'delivery_tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_notes' => ['nullable', 'string'],
            'estimated_completion_date' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'status_note' => ['nullable', 'string'],
            'is_customer_visible' => ['nullable', 'boolean'],
        ]);

        $statusChanged = $repair->status !== $data['status'];
        $data = $this->normalizeFulfillmentData($data);
        $data['repair_total'] = round((float) $repair->subtotal + (float) $repair->tax_amount + (float) $data['shipping_cost'], 2);
        $shippingSnapshot = $data['fulfillment_method'] === 'pickup'
            ? [
                'shipping_method_id' => null,
                'shipping_method_name' => null,
                'shipping_delivery_days' => null,
                'shipping_base_cost' => 0,
                'shipping_discount_amount' => 0,
            ]
            : [
                'shipping_method_id' => $repair->shipping_method_id,
                'shipping_method_name' => $repair->shipping_method_name,
                'shipping_delivery_days' => $repair->shipping_delivery_days,
                'shipping_base_cost' => $repair->shipping_base_cost,
                'shipping_discount_amount' => $repair->shipping_discount_amount,
            ];

        $repair->update($shippingSnapshot + [
            'status' => $data['status'],
            'repair_status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'fulfillment_method' => $data['fulfillment_method'],
            'shipping_cost' => $data['shipping_cost'],
            'shipping_amount' => $data['shipping_cost'],
            'repair_total' => $data['repair_total'],
            'total_amount' => $data['repair_total'],
            'balance_due' => max(0, $data['repair_total'] - (float) $repair->amount_paid),
            'delivery_carrier' => $data['delivery_carrier'] ?? null,
            'delivery_tracking_number' => $data['delivery_tracking_number'] ?? null,
            'tracking_notes' => $data['tracking_notes'] ?? null,
            'estimated_completion_date' => $data['estimated_completion_date'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);

        if ($repair->isShipping()) {
            $address = $addressFormatter->format($data);

            if ($address !== '') {
                $repair->shipping()->updateOrCreate(
                    ['repair_id' => $repair->id],
                    ['shipping_address' => $address],
                );
            }
        }

        if ($statusChanged || filled($data['status_note'] ?? null)) {
            $statusUpdate = $repair->statusUpdates()->create([
                'status' => $data['status'],
                'note' => $data['status_note'] ?? $data['customer_notes'] ?? null,
                'is_customer_visible' => $request->boolean('is_customer_visible', true),
                'delivery_carrier' => $repair->delivery_carrier,
                'tracking_number' => $repair->delivery_tracking_number,
                'created_by' => $request->user()->id,
            ]);

            Mail::to($repair->customer?->email)->send(new RepairStatusUpdatedMail($repair->fresh('customer', 'shipping'), $statusUpdate));
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Repair updated.');
    }

    private function normalizeFulfillmentData(array $data): array
    {
        if ($data['fulfillment_method'] === 'pickup') {
            $data['shipping_cost'] = 0;
            $data['shipping_base_cost'] = 0;
            $data['shipping_discount_amount'] = 0;

            foreach ([
                'recipient_name',
                'recipient_phone',
                'recipient_email',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
                'country',
                'shipping_method_id',
                'shipping_method_name',
                'shipping_delivery_days',
                'delivery_carrier',
                'delivery_tracking_number',
                'tracking_notes',
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
