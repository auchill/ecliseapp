<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackRepairRequest;
use App\Models\Customer;
use App\Models\Repair;
use App\Services\AddressSnapshotFormatter;
use App\Services\PaymentGatewayService;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RepairController extends Controller
{
    public function create()
    {
        abort_if(auth()->user()?->isAdmin(), 403);

        return view('repairs.book');
    }

    public function store(Request $request)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'repair_number' => ['required', 'string', 'max:40'],
        ]);

        $booking = Repair::query()
            ->where('repair_number', $data['repair_number'])
            ->first();

        if (! $booking) {
            return back()->withErrors(['repair_number' => 'No repair was found for that repair number.'])->withInput();
        }

        if (! $booking->canCustomerPay() && $booking->repair_status !== 'awaiting_device') {
            return back()->withErrors(['repair_number' => 'This repair is not available for customer completion or payment.'])->withInput();
        }

        return redirect()->route('repairs.complete', $booking->repair_number);
    }

    public function complete(string $repairNumber, Request $request, ShippingCostService $shippingCosts)
    {
        abort_if($request->user()?->isAdmin(), 403);

        $booking = $this->bookingForCustomer($repairNumber, $request);
        if ($booking->repairConversation) {
            return redirect()->route('repair-conversations.show', $booking->repairConversation);
        }

        $baseSubtotal = (float) $booking->subtotal + (float) $booking->tax_amount;
        $shippingMethods = $shippingCosts->getAvailableShippingMethods();
        $shippingQuotes = $shippingMethods
            ->mapWithKeys(fn ($method) => [
                (string) $method->id => $shippingCosts->calculateForRepairOrder($baseSubtotal, $method->id),
            ])
            ->all();

        return view('repairs.complete', [
            'booking' => $booking->load('customer', 'shipping', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'latestPayment'),
            'pickupQuote' => $shippingCosts->calculateForFulfillment('pickup', $baseSubtotal, null),
            'shippingMethods' => $shippingMethods,
            'shippingQuotes' => $shippingQuotes,
        ]);
    }

    public function completeStore(
        string $repairNumber,
        Request $request,
        ShippingCostService $shippingCosts,
        PaymentGatewayService $paymentGateways,
        AddressSnapshotFormatter $addressFormatter,
    ) {
        abort_if($request->user()?->isAdmin(), 403);

        $booking = $this->bookingForCustomer($repairNumber, $request);

        if ($booking->repairConversation) {
            return redirect()->route('repair-conversations.show', $booking->repairConversation)
                ->withErrors(['payment_gateway' => 'Please pay from the accepted repair proposal.']);
        }

        abort_unless($booking->canCustomerPay(), 422, 'This repair is not available for payment.');

        $data = $request->validate([
            'fulfillment_method' => ['required', 'in:pickup,shipping'],
            'shipping_method_id' => ['required_if:fulfillment_method,shipping', 'nullable', 'exists:shipping_methods,id'],
            'recipient_name' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'recipient_email' => ['required_if:fulfillment_method,shipping', 'nullable', 'email', 'max:255'],
            'address_line1' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'province' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
            'postal_code' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:40'],
            'country' => ['required_if:fulfillment_method,shipping', 'nullable', 'string', 'max:120'],
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

        $customer = Customer::forUser($request->user());

        $booking->update([
            'customer_id' => $booking->customer_id ?: $customer->id,
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

        if ($booking->isShipping()) {
            $address = $addressFormatter->format($data);

            if ($address !== '') {
                $booking->shipping()->updateOrCreate(
                    ['repair_id' => $booking->id],
                    ['shipping_address' => $address],
                );
            }
        }

        $payment = $booking->payments()->create([
            'repair_id' => $booking->id,
            'source' => 'repair',
            'gateway' => $data['payment_gateway'],
            'amount' => $paymentAmount,
            'currency' => 'cad',
            'status' => 'pending',
            'checkout_data' => [
                'customer_id' => $customer->id,
                'fulfillment' => $data,
            ],
        ]);

        return redirect()->away($paymentGateways->createCheckout($payment));
    }

    public function confirmation(Repair $repair)
    {
        return view('repairs.confirmation', [
            'booking' => $repair->load('customer', 'shipping', 'publicStatusUpdates', 'latestPayment', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory'),
        ]);
    }

    public function trackForm()
    {
        return view('repairs.track');
    }

    public function track(TrackRepairRequest $request)
    {
        $data = $request->validated();

        $booking = Repair::query()
            ->where('repair_number', $data['repair_number'])
            ->when(filled($data['contact'] ?? null), function ($query) use ($data): void {
                $contact = trim($data['contact']);
                $query->whereHas('customer', function ($query) use ($contact): void {
                    $query->where('email', $contact)->orWhere('phone', $contact);
                });
            })
            ->with('customer', 'shipping', 'publicStatusUpdates', 'latestPayment', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory')
            ->first();

        if (! $booking) {
            return back()
                ->withErrors(['repair_number' => 'No repair was found for that repair number.'])
                ->withInput();
        }

        return view('repairs.track', [
            'booking' => $booking,
        ]);
    }

    private function bookingForCustomer(string $repairNumber, Request $request): Repair
    {
        $customer = Customer::forUser($request->user());

        $booking = Repair::query()
            ->where('repair_number', $repairNumber)
            ->firstOrFail();

        abort_if($booking->customer_id && $booking->customer_id !== $customer->id, 403);
        abort_if(! $booking->customer_id, 403);

        return $booking;
    }

    private function normalizeFulfillmentData(array $data, array $shippingQuote): array
    {
        $data = array_merge($data, $shippingQuote);

        if ($data['fulfillment_method'] === 'pickup') {
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
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
