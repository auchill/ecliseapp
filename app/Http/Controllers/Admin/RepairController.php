<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RepairStatusUpdatedMail;
use App\Models\RepairBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RepairController extends Controller
{
    public function index(Request $request)
    {
        $repairs = RepairBooking::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('device_brand', 'like', "%{$search}%")
                        ->orWhere('device_model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.repairs.index', [
            'repairs' => $repairs,
            'statuses' => RepairBooking::STATUSES,
        ]);
    }

    public function show(RepairBooking $repair)
    {
        return view('admin.repairs.show', [
            'repair' => $repair->load('statusUpdates', 'user'),
            'statuses' => RepairBooking::STATUSES,
            'fulfillmentMethods' => RepairBooking::FULFILLMENT_METHODS,
        ]);
    }

    public function update(Request $request, RepairBooking $repair)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RepairBooking::STATUSES)],
            'fulfillment_method' => ['required', Rule::in(array_keys(RepairBooking::FULFILLMENT_METHODS))],
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
        $data['repair_total'] = $data['shipping_cost'];
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
            'fulfillment_method' => $data['fulfillment_method'],
            'shipping_full_name' => $data['shipping_full_name'] ?? null,
            'shipping_phone' => $data['shipping_phone'] ?? null,
            'shipping_email' => $data['shipping_email'] ?? null,
            'shipping_address_line1' => $data['shipping_address_line1'] ?? null,
            'shipping_address_line2' => $data['shipping_address_line2'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_province' => $data['shipping_province'] ?? null,
            'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
            'shipping_country' => $data['shipping_country'] ?? null,
            'shipping_cost' => $data['shipping_cost'],
            'repair_total' => $data['repair_total'],
            'delivery_carrier' => $data['delivery_carrier'] ?? null,
            'delivery_tracking_number' => $data['delivery_tracking_number'] ?? null,
            'tracking_notes' => $data['tracking_notes'] ?? null,
            'estimated_completion_date' => $data['estimated_completion_date'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);

        if ($statusChanged || filled($data['status_note'] ?? null)) {
            $statusUpdate = $repair->statusUpdates()->create([
                'status' => $data['status'],
                'note' => $data['status_note'] ?? $data['customer_notes'] ?? null,
                'is_customer_visible' => $request->boolean('is_customer_visible', true),
                'delivery_carrier' => $repair->delivery_carrier,
                'tracking_number' => $repair->delivery_tracking_number,
                'created_by' => $request->user()->id,
            ]);

            Mail::to($repair->email)->send(new RepairStatusUpdatedMail($repair->fresh(), $statusUpdate));
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
                'delivery_tracking_number',
                'tracking_notes',
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
