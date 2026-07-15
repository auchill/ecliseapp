<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Models\RepairPartGroup;
use App\Models\RepairPartOption;
use App\Services\Parts\PartSearchService;
use App\Services\RepairNegotiationService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RepairConversationController extends Controller
{
    public function partSearch(Repair $repair, Request $request, PartSearchService $partSearch)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $conversation = app(RepairNegotiationService::class)->conversationForRepair($repair);

        return response()->json([
            'repair_id' => $repair->id,
            'conversation_id' => $conversation->id,
            'results' => $partSearch->repairProposalResults(
                $request->string('q')->toString(),
                (int) $request->integer('limit', 10),
            ),
        ]);
    }

    public function storeMessage(Repair $repair, Request $request, RepairNegotiationService $negotiation)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $conversation = $negotiation->conversationForRepair($repair);
        $negotiation->addMessage($conversation, $request->user(), $data['message'], $request->boolean('is_internal'));

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Message added.');
    }

    public function updateCharges(Repair $repair, Request $request, RepairNegotiationService $negotiation)
    {
        $data = $request->validate([
            'labour_amount' => ['required', 'numeric', 'min:0'],
            'diagnostic_fee' => ['required', 'numeric', 'min:0'],
            'service_fee' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $negotiation->updateCharges($negotiation->conversationForRepair($repair), $data, $request->user());

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Proposal charges updated.');
    }

    public function storePartGroup(Repair $repair, Request $request, RepairNegotiationService $negotiation)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $negotiation->addPartGroup($negotiation->conversationForRepair($repair), $data, $request->user());

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Part group added.');
    }

    public function storePartOption(Repair $repair, RepairPartGroup $repairPartGroup, Request $request, RepairNegotiationService $negotiation)
    {
        $conversation = $negotiation->conversationForRepair($repair);
        abort_if($repairPartGroup->repair_conversation_id !== $conversation->id, 404);

        $data = $request->validate([
            'part_id' => ['required', 'integer'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');
        try {
            $negotiation->addPartOption($repairPartGroup, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['part_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Part option added.');
    }

    public function markPrimary(Repair $repair, RepairPartOption $repairPartOption, Request $request, RepairNegotiationService $negotiation)
    {
        $conversation = $negotiation->conversationForRepair($repair);
        abort_if($repairPartOption->group?->repair_conversation_id !== $conversation->id, 404);

        try {
            $negotiation->markPrimaryPartOption($repairPartOption, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['part_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Recommended option updated.');
    }

    public function destroyPartOption(Repair $repair, RepairPartOption $repairPartOption, Request $request, RepairNegotiationService $negotiation)
    {
        $conversation = $negotiation->conversationForRepair($repair);
        abort_if($repairPartOption->group?->repair_conversation_id !== $conversation->id, 404);

        try {
            $negotiation->removePartOption($repairPartOption, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['part_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Part option removed.');
    }

    public function sendProposal(Repair $repair, Request $request, RepairNegotiationService $negotiation)
    {
        try {
            $negotiation->sendProposal($negotiation->conversationForRepair($repair), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Repair proposal sent.');
    }

    public function close(RepairConversation $repairConversation)
    {
        $repairConversation->update([
            'status' => RepairConversation::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()->route('admin.repairs.show', $repairConversation->repair_id)->with('status', 'Conversation closed.');
    }
}
