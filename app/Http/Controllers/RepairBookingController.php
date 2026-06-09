<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackRepairRequest;
use App\Models\RepairBooking;
use App\Services\PaymentGatewayService;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RepairBookingController extends Controller
{
    public function create()
    {
        return view('repairs.book');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40'],
        ]);

        $booking = RepairBooking::query()
            ->where('tracking_number', $data['tracking_number'])
            ->first();

        if (! $booking) {
            return back()->withErrors(['tracking_number' => 'No repair booking was found for that tracking number.'])->withInput();
        }

        if (! $booking->canCustomerPay() && $booking->repair_status !== 'awaiting_device') {
            return back()->withErrors(['tracking_number' => 'This repair booking is not available for customer booking or payment.'])->withInput();
        }

        return redirect()->route('repairs.complete', $booking->tracking_number);
    }

    public function complete(string $trackingNumber, Request $request, ShippingCostService $shippingCosts)
    {
        $booking = $this->bookingForCustomer($trackingNumber, $request);
        $baseSubtotal = (float) $booking->subtotal + (float) $booking->tax_amount;
        $shippingMethods = $shippingCosts->getAvailableShippingMethods();
        $shippingQuotes = $shippingMethods
            ->mapWithKeys(fn ($method) => [
                (string) $method->id => $shippingCosts->calculateForRepairOrder($baseSubtotal, $method->id),
            ])
            ->all();

        return view('repairs.complete', [
            'booking' => $booking->load('deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'latestPayment'),
            'pickupQuote' => $shippingCosts->calculateForFulfillment('pickup', $baseSubtotal, null),
            'shippingMethods' => $shippingMethods,
            'shippingQuotes' => $shippingQuotes,
        ]);
    }

    public function completeStore(string $trackingNumber, Request $request, ShippingCostService $shippingCosts, PaymentGatewayService $paymentGateways)
    {
        $booking = $this->bookingForCustomer($trackingNumber, $request);

        abort_unless($booking->canCustomerPay(), 422, 'This repair booking is not available for payment.');

        $data = $request->validate([
            'fulfillment_method' => ['required', 'in:pickup,shipping'],
            'shipping_method_id' => ['required_if:fulfillment_method,shipping', 'nullable', 'exists:shipping_methods,id'],
            'shipping_full_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'shipping_address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'shipping_country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'customer_remark' => ['nullable', 'string', 'max:3000'],
            'payment_gateway' => ['required', 'in:stripe,paypal'],
            'payment_amount_option' => ['required', 'in:minimum,full'],
            'terms_accepted' => ['accepted'],
        ]);

        $baseSubtotal = (float) $booking->subtotal + (float) $booking->tax_amount;

        try {
            $shippingQuote = $shippingCosts->calculateForFulfillment(
                $data['fulfillment_method'],
                $baseSubtotal,
                isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['shipping_method_id' => $exception->getMessage()])->withInput();
        }

        $data = $this->normalizeFulfillmentData($data, $shippingQuote);
        $shippingAmount = (float) $data['shipping_cost'];
        $totalAmount = round((float) $booking->subtotal + (float) $booking->tax_amount + $shippingAmount, 2);
        $partsTotal = $booking->partsTotal();
        $minimumPayment = $partsTotal > 0
            ? round($partsTotal + (0.5 * max(0, $totalAmount - $partsTotal)), 2)
            : round($totalAmount * 0.5, 2);
        $minimumDue = round(max(0, $minimumPayment - (float) $booking->amount_paid), 2);
        $balanceBeforePayment = round(max(0, $totalAmount - (float) $booking->amount_paid), 2);
        $paymentAmount = $data['payment_amount_option'] === 'full'
            ? $balanceBeforePayment
            : min($balanceBeforePayment, $minimumDue);

        if ($paymentAmount <= 0 && $balanceBeforePayment > 0) {
            $paymentAmount = $balanceBeforePayment;
        }

        if ($paymentAmount + 0.01 < $minimumDue) {
            return back()->withErrors(['payment_amount_option' => 'Payment must cover at least the required minimum amount.'])->withInput();
        }

        $booking->update([
            'user_id' => $booking->user_id ?: $request->user()->id,
            'terms_accepted' => true,
            'fulfillment_method' => $data['fulfillment_method'],
            'pickup_or_shipping_option' => $data['fulfillment_method'],
            'shipping_method_id' => $data['shipping_method_id'] ?? null,
            'shipping_method_name' => $data['shipping_method_name'] ?? null,
            'shipping_delivery_days' => $data['shipping_delivery_days'] ?? null,
            'shipping_base_cost' => $data['shipping_base_cost'] ?? 0,
            'shipping_discount_amount' => $data['shipping_discount_amount'] ?? 0,
            'shipping_cost' => $shippingAmount,
            'shipping_amount' => $shippingAmount,
            'shipping_full_name' => $data['shipping_full_name'] ?? null,
            'shipping_phone' => $data['shipping_phone'] ?? null,
            'shipping_email' => $data['shipping_email'] ?? null,
            'shipping_address_line1' => $data['shipping_address_line1'] ?? null,
            'shipping_address_line2' => $data['shipping_address_line2'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_province' => $data['shipping_province'] ?? null,
            'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
            'shipping_country' => $data['shipping_country'] ?? null,
            'customer_remark' => $data['customer_remark'] ?? null,
            'total_amount' => $totalAmount,
            'repair_total' => $totalAmount,
            'balance_due' => $balanceBeforePayment,
            'payment_gateway' => $data['payment_gateway'],
            'payment_amount' => $paymentAmount,
            'currency' => 'cad',
            'repair_status' => 'awaiting_customer_payment',
            'status' => 'awaiting_customer_payment',
        ]);

        $payment = $booking->payments()->create([
            'repair_order_id' => $booking->id,
            'gateway' => $data['payment_gateway'],
            'amount' => $paymentAmount,
            'currency' => 'cad',
            'status' => 'pending',
        ]);

        return redirect()->away($paymentGateways->createCheckout($payment));
    }

    public function confirmation(RepairBooking $repairBooking)
    {
        return view('repairs.confirmation', [
            'booking' => $repairBooking->load('publicStatusUpdates', 'latestPayment', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory'),
        ]);
    }

    public function trackForm()
    {
        return view('repairs.track');
    }

    public function track(TrackRepairRequest $request)
    {
        $data = $request->validated();

        $booking = RepairBooking::query()
            ->where('tracking_number', $data['tracking_number'])
            ->when(filled($data['contact'] ?? null), function ($query) use ($data): void {
                $contact = trim($data['contact']);
                $query->where(function ($query) use ($contact): void {
                    $query->where('email', $contact)->orWhere('phone', $contact);
                });
            })
            ->with('publicStatusUpdates', 'latestPayment', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory')
            ->first();

        if (! $booking) {
            return back()
                ->withErrors(['tracking_number' => 'No repair was found for that tracking number.'])
                ->withInput();
        }

        return view('repairs.track', [
            'booking' => $booking,
        ]);
    }

    private function bookingForCustomer(string $trackingNumber, Request $request): RepairBooking
    {
        $booking = RepairBooking::query()
            ->where('tracking_number', $trackingNumber)
            ->firstOrFail();

        abort_if($booking->user_id && $booking->user_id !== $request->user()->id, 403);

        return $booking;
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
