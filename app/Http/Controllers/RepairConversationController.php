<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Services\PaymentGatewayService;
use App\Services\RepairNegotiationService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RepairConversationController extends Controller
{
    public function showForRepair(Repair $repair, Request $request, RepairNegotiationService $negotiation)
    {
        $customer = Customer::forUser($request->user());
        abort_if($repair->customer_id !== $customer->id, 403);

        return $this->render($negotiation->conversationForRepair($repair), $customer);
    }

    public function show(RepairConversation $repairConversation, Request $request)
    {
        $customer = Customer::forUser($request->user());
        abort_if($repairConversation->customer_id !== $customer->id, 403);

        return $this->render($repairConversation, $customer);
    }

    public function storeMessage(RepairConversation $repairConversation, Request $request, RepairNegotiationService $negotiation)
    {
        $customer = Customer::forUser($request->user());
        abort_if($repairConversation->customer_id !== $customer->id, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $negotiation->addMessage($repairConversation, $request->user(), $data['message']);

        return redirect()->route('repair-conversations.show', $repairConversation)->with('status', 'Message sent.');
    }

    public function selectPart(RepairConversation $repairConversation, Request $request, RepairNegotiationService $negotiation)
    {
        $customer = Customer::forUser($request->user());
        abort_if($repairConversation->customer_id !== $customer->id, 403);

        $data = $request->validate([
            'option_id' => ['required', 'integer', 'exists:repair_part_options,id'],
        ]);

        $negotiation->selectOption($repairConversation, $customer, (int) $data['option_id']);

        return redirect()->route('repair-conversations.show', $repairConversation)->with('status', 'Part selection saved.');
    }

    public function accept(RepairConversation $repairConversation, Request $request, RepairNegotiationService $negotiation)
    {
        $customer = Customer::forUser($request->user());
        abort_if($repairConversation->customer_id !== $customer->id, 403);

        $request->validate([
            'agreement_accepted' => ['accepted'],
        ]);

        try {
            $negotiation->acceptProposal($repairConversation, $customer);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['agreement' => $exception->getMessage()]);
        }

        return redirect()->route('repair-conversations.show', $repairConversation)->with('status', 'Proposal accepted. Payment is now available.');
    }

    public function payment(
        RepairConversation $repairConversation,
        Request $request,
        RepairNegotiationService $negotiation,
        PaymentGatewayService $paymentGateways,
    ) {
        $customer = Customer::forUser($request->user());
        abort_if($repairConversation->customer_id !== $customer->id, 403);

        $data = $request->validate([
            'payment_gateway' => ['required', 'in:stripe,paypal'],
        ]);

        try {
            $payment = $negotiation->createPayment($repairConversation, $customer, $data['payment_gateway']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment_gateway' => $exception->getMessage()]);
        }

        return redirect()->away($paymentGateways->createCheckout($payment));
    }

    private function render(RepairConversation $conversation, Customer $customer)
    {
        $conversation->load([
            'repair.deviceBrand',
            'repair.deviceModel',
            'repair.issueCategory',
            'activePartGroups.activeOptions',
            'activePartGroups.selections.option',
        ]);

        return view('repair-conversations.show', [
            'conversation' => $conversation,
            'customer' => $customer,
            'messages' => $conversation->publicMessages()->oldest()->get(),
        ]);
    }
}
