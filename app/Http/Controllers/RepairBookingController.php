<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepairBookingRequest;
use App\Http\Requests\TrackRepairRequest;
use App\Models\RepairBooking;
use App\Services\ShippingCostService;
use Illuminate\Support\Facades\Log;

class RepairBookingController extends Controller
{
    public function create(ShippingCostService $shippingCosts)
    {
        return view('repairs.book', [
            'pickupShippingCost' => $shippingCosts->calculate('pickup'),
            'canadaShippingCost' => $shippingCosts->calculate('shipping', 'Canada'),
            'internationalShippingCost' => $shippingCosts->calculate('shipping', 'United States'),
        ]);
    }

    public function store(StoreRepairBookingRequest $request, ShippingCostService $shippingCosts)
    {
        $data = $request->validated();
        $data = $this->normalizeFulfillmentData($data, $shippingCosts);

        if ($request->hasFile('device_image')) {
            $data['device_image_path'] = $request->file('device_image')->store('repair-images', 'public');
        }

        $data['user_id'] = $request->user()?->id;
        $data['tracking_number'] = $this->generateTrackingNumber();
        $data['status'] = 'Submitted';
        $data['terms_accepted'] = true;
        $data['repair_total'] = $data['shipping_cost'];

        unset($data['device_image']);

        $booking = RepairBooking::query()->create($data);

        $booking->statusUpdates()->create([
            'status' => 'Submitted',
            'note' => $booking->isShipping()
                ? 'Repair request received. Repaired device will be shipped after service.'
                : 'Repair request received. Repaired device will be held for store pickup.',
            'is_customer_visible' => true,
            'delivery_carrier' => $booking->delivery_carrier,
            'tracking_number' => $booking->delivery_tracking_number,
            'created_by' => $request->user()?->id,
        ]);

        Log::info('Repair booking notification placeholder', [
            'tracking_number' => $booking->tracking_number,
            'email' => $booking->email,
        ]);

        return redirect()->route('repairs.confirmation', $booking);
    }

    public function confirmation(RepairBooking $repairBooking)
    {
        return view('repairs.confirmation', [
            'booking' => $repairBooking->load('publicStatusUpdates'),
        ]);
    }

    public function trackForm()
    {
        return view('repairs.track');
    }

    public function track(TrackRepairRequest $request)
    {
        $data = $request->validated();
        $contact = trim($data['contact']);

        $booking = RepairBooking::query()
            ->where('tracking_number', $data['tracking_number'])
            ->where(function ($query) use ($contact): void {
                $query->where('email', $contact)->orWhere('phone', $contact);
            })
            ->with('publicStatusUpdates')
            ->first();

        if (! $booking) {
            return back()
                ->withErrors(['tracking_number' => 'No repair was found for that tracking number and contact detail.'])
                ->withInput();
        }

        return view('repairs.track', [
            'booking' => $booking,
        ]);
    }

    private function generateTrackingNumber(): string
    {
        $year = now()->year;
        $next = RepairBooking::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $trackingNumber = sprintf('ECL-REP-%s-%04d', $year, $next++);
        } while (RepairBooking::query()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    private function normalizeFulfillmentData(array $data, ShippingCostService $shippingCosts): array
    {
        $data['shipping_cost'] = $shippingCosts->calculate(
            $data['fulfillment_method'],
            $data['shipping_country'] ?? null,
        );

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
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
