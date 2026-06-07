<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepairBookingRequest;
use App\Http\Requests\TrackRepairRequest;
use App\Models\RepairBooking;
use App\Services\ShippingCostService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RepairBookingController extends Controller
{
    public function create(ShippingCostService $shippingCosts)
    {
        $repairSubtotal = 0.00;
        $shippingMethods = $shippingCosts->getAvailableShippingMethods();
        $shippingQuotes = $shippingMethods
            ->mapWithKeys(fn ($method) => [
                (string) $method->id => $shippingCosts->calculateForRepairOrder($repairSubtotal, $method->id),
            ])
            ->all();

        return view('repairs.book', [
            'pickupQuote' => $shippingCosts->calculateForFulfillment('pickup', $repairSubtotal, null),
            'shippingMethods' => $shippingMethods,
            'shippingQuotes' => $shippingQuotes,
        ]);
    }

    public function store(StoreRepairBookingRequest $request, ShippingCostService $shippingCosts)
    {
        $data = $request->validated();

        try {
            $shippingQuote = $shippingCosts->calculateForFulfillment(
                $data['fulfillment_method'],
                0.00,
                isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['shipping_method_id' => $exception->getMessage()])
                ->withInput();
        }

        $data = $this->normalizeFulfillmentData($data, $shippingQuote);

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
            'note' => $booking->isShipping() && $booking->shipping_method_name
                ? "Repair request received. Repaired device will use {$booking->shipping_method_name} after service."
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
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
