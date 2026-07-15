<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Models\RepairMessage;
use App\Models\RepairPartGroup;
use App\Models\RepairPartOption;
use App\Models\RepairPartSelection;
use App\Models\User;
use App\Services\Parts\PartSearchService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RepairNegotiationService
{
    public function __construct(private readonly PartSearchService $partSearch) {}

    public function conversationForRepair(Repair $repair): RepairConversation
    {
        if (! $repair->customer_id) {
            throw new InvalidArgumentException('A repair must have a customer before negotiation can begin.');
        }

        return RepairConversation::query()->firstOrCreate(
            ['repair_id' => $repair->id],
            [
                'customer_id' => $repair->customer_id,
                'status' => RepairConversation::STATUS_OPEN,
                'labour_amount' => (float) ($repair->subtotal ?: 0),
                'tax_amount' => (float) ($repair->tax_amount ?: 0),
                'final_total' => (float) ($repair->total_amount ?: $repair->repair_total ?: 0),
            ],
        );
    }

    public function addMessage(RepairConversation $conversation, User $actor, string $message, bool $internal = false): RepairMessage
    {
        $senderType = $actor->isAdmin() ? RepairMessage::SENDER_ADMIN : RepairMessage::SENDER_CUSTOMER;
        $internal = $senderType === RepairMessage::SENDER_ADMIN && $internal;

        return DB::transaction(function () use ($conversation, $actor, $message, $senderType, $internal): RepairMessage {
            $message = $conversation->messages()->create([
                'sender_type' => $senderType,
                'sender_id' => $actor->id,
                'message_type' => 'text',
                'message' => trim($message),
                'is_internal' => $internal,
            ]);

            $conversation->update(['last_message_at' => now()]);
            $this->recordEvent($conversation, $senderType, $actor->id, 'message_created', [
                'message_id' => $message->id,
                'is_internal' => $internal,
            ]);

            return $message;
        });
    }

    public function updateCharges(RepairConversation $conversation, array $data, User $actor): RepairConversation
    {
        return DB::transaction(function () use ($conversation, $data, $actor): RepairConversation {
            $conversation = RepairConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->ensureProposalEditable($conversation);
            $conversation->fill([
                'labour_amount' => $this->money($data['labour_amount'] ?? 0),
                'diagnostic_fee' => $this->money($data['diagnostic_fee'] ?? 0),
                'service_fee' => $this->money($data['service_fee'] ?? 0),
                'discount_amount' => $this->money($data['discount_amount'] ?? 0),
                'tax_amount' => $this->money($data['tax_amount'] ?? 0),
            ]);
            $this->invalidateCurrentProposal($conversation);
            $this->recalculateTotals($conversation);
            $conversation->save();

            $this->recordEvent($conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'charges_updated', $conversation->only([
                'labour_amount',
                'diagnostic_fee',
                'service_fee',
                'discount_amount',
                'tax_amount',
                'selected_parts_subtotal',
                'final_total',
            ]));

            return $conversation->fresh();
        });
    }

    public function addPartGroup(RepairConversation $conversation, array $data, User $actor): RepairPartGroup
    {
        return DB::transaction(function () use ($conversation, $data, $actor): RepairPartGroup {
            $conversation = RepairConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->ensureProposalEditable($conversation);
            $group = $conversation->partGroups()->create([
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'is_required' => true,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'proposal_version' => max(1, (int) $conversation->proposal_version + 1),
                'is_active' => true,
            ]);
            $this->ensureCustomerSuppliedOption($group);
            $this->invalidateCurrentProposal($conversation);
            $conversation->save();

            $this->recordEvent($conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'part_group_created', [
                'group_id' => $group->id,
            ]);

            return $group;
        });
    }

    public function addPartOption(RepairPartGroup $group, array $data, User $actor): RepairPartOption
    {
        return DB::transaction(function () use ($group, $data, $actor): RepairPartOption {
            $group = RepairPartGroup::query()->with('conversation')->lockForUpdate()->findOrFail($group->id);
            $this->ensureProposalEditable($group->conversation);
            $this->ensureCustomerSuppliedOption($group);

            $partId = (int) ($data['part_id'] ?? 0);
            $part = $partId > 0 ? $this->partSearch->eligibleRepairPart($partId) : null;

            if (! $part) {
                throw new InvalidArgumentException('The selected part is no longer available. Search for another part.');
            }

            $duplicateExists = $group->options()
                ->where('source_type', Part::class)
                ->where('source_id', $part->id)
                ->where('is_active', true)
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('This part has already been added to this required part group.');
            }

            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                RepairPartOption::query()
                    ->where('repair_part_group_id', $group->id)
                    ->where('option_type', RepairPartOption::TYPE_PART)
                    ->where('is_system_option', false)
                    ->update(['is_primary' => false]);
            }

            $option = $group->options()->create([
                'option_type' => RepairPartOption::TYPE_PART,
                'is_system_option' => false,
                'system_option_key' => null,
                'source_type' => Part::class,
                'source_id' => $part->id,
                'sku_snapshot' => $part->sku ?: $part->new_sku,
                'name_snapshot' => $part->name,
                'model_snapshot' => $part->modelName(),
                'image_url_snapshot' => $part->imageUrl(),
                'description_snapshot' => $part->displayDescription(),
                'quality_label' => null,
                'price_snapshot' => $this->money($part->displayPrice()),
                'is_primary' => $isPrimary,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'proposal_version' => max(1, (int) $group->conversation->proposal_version + 1),
                'is_active' => true,
            ]);
            $this->invalidateCurrentProposal($group->conversation);
            $group->conversation->save();

            $this->recordEvent($group->conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'part_option_created', [
                'group_id' => $group->id,
                'option_id' => $option->id,
                'price_snapshot' => $option->price_snapshot,
            ]);

            return $option;
        });
    }

    public function selectOption(RepairConversation $conversation, Customer $customer, int $optionId): RepairPartSelection
    {
        return DB::transaction(function () use ($conversation, $customer, $optionId): RepairPartSelection {
            $conversation = RepairConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($conversation->customer_id !== $customer->id) {
                throw new InvalidArgumentException('This repair proposal does not belong to the current customer.');
            }

            if ($conversation->status !== RepairConversation::STATUS_AWAITING_CUSTOMER) {
                throw new InvalidArgumentException('Part selections can only be changed while a proposal is awaiting customer review.');
            }

            $option = RepairPartOption::query()
                ->where('is_active', true)
                ->whereHas('group', fn ($query) => $query
                    ->where('repair_conversation_id', $conversation->id)
                    ->where('is_active', true))
                ->findOrFail($optionId);

            $selection = RepairPartSelection::query()->updateOrCreate(
                [
                    'repair_part_group_id' => $option->repair_part_group_id,
                    'customer_id' => $customer->id,
                ],
                [
                    'repair_part_option_id' => $option->id,
                    'selected_at' => now(),
                ],
            );

            $this->recalculateTotals($conversation);
            $conversation->save();
            $this->recordEvent($conversation, RepairMessage::SENDER_CUSTOMER, $customer->user_id, 'part_option_selected', [
                'group_id' => $option->repair_part_group_id,
                'option_id' => $option->id,
            ]);

            return $selection->fresh('option');
        });
    }

    public function sendProposal(RepairConversation $conversation, User $actor): RepairConversation
    {
        return DB::transaction(function () use ($conversation, $actor): RepairConversation {
            $conversation = RepairConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->ensureProposalEditable($conversation);
            $this->ensureCustomerSuppliedOptions($conversation);
            $conversation->proposal_version++;
            $conversation->accepted_proposal_version = null;
            $conversation->agreed_at = null;
            $conversation->status = RepairConversation::STATUS_AWAITING_CUSTOMER;
            $this->recalculateTotals($conversation);
            $conversation->save();

            $conversation->messages()->create([
                'sender_type' => RepairMessage::SENDER_SYSTEM,
                'sender_id' => null,
                'message_type' => 'proposal',
                'message' => 'A formal repair proposal was sent for customer review.',
                'is_internal' => false,
            ]);
            $conversation->update(['last_message_at' => now()]);
            $this->recordEvent($conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'proposal_sent', [
                'proposal_version' => $conversation->proposal_version,
                'final_total' => $conversation->final_total,
            ]);

            return $conversation->fresh();
        });
    }

    public function acceptProposal(RepairConversation $conversation, Customer $customer): RepairConversation
    {
        return DB::transaction(function () use ($conversation, $customer): RepairConversation {
            $conversation = RepairConversation::query()
                ->with('repair')
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($conversation->customer_id !== $customer->id) {
                throw new InvalidArgumentException('This repair proposal does not belong to the current customer.');
            }

            if ($conversation->status !== RepairConversation::STATUS_AWAITING_CUSTOMER || $conversation->proposal_version < 1) {
                throw new InvalidArgumentException('There is no active proposal available for acceptance.');
            }

            $this->assertRequiredSelectionsComplete($conversation, $customer);
            $this->recalculateTotals($conversation);
            $conversation->accepted_proposal_version = $conversation->proposal_version;
            $conversation->agreed_at = now();
            $conversation->status = RepairConversation::STATUS_PAYMENT_PENDING;
            $conversation->save();

            $repair = $conversation->repair;
            $repair->update([
                'subtotal' => round((float) $conversation->labour_amount + (float) $conversation->diagnostic_fee + (float) $conversation->service_fee + (float) $conversation->selected_parts_subtotal - (float) $conversation->discount_amount, 2),
                'tax_amount' => $conversation->tax_amount,
                'total_amount' => $conversation->final_total,
                'repair_total' => $conversation->final_total,
                'balance_due' => round(max(0, (float) $conversation->final_total - (float) $repair->amount_paid), 2),
                'payment_status' => $repair->amount_paid > 0 ? 'partially_paid' : 'unpaid',
                'status' => 'awaiting_customer_payment',
                'repair_status' => 'awaiting_customer_payment',
            ]);

            $conversation->messages()->create([
                'sender_type' => RepairMessage::SENDER_SYSTEM,
                'sender_id' => null,
                'message_type' => 'agreement',
                'message' => 'The customer accepted proposal version '.$conversation->proposal_version.'.',
                'is_internal' => false,
            ]);
            $conversation->update(['last_message_at' => now()]);
            $this->recordEvent($conversation, RepairMessage::SENDER_CUSTOMER, $customer->user_id, 'proposal_accepted', [
                'proposal_version' => $conversation->proposal_version,
                'final_total' => $conversation->final_total,
            ]);

            return $conversation->fresh('repair');
        });
    }

    public function createPayment(RepairConversation $conversation, Customer $customer, string $gateway): Payment
    {
        return DB::transaction(function () use ($conversation, $customer, $gateway): Payment {
            $conversation = RepairConversation::query()
                ->with('repair.customer')
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($conversation->customer_id !== $customer->id || ! $conversation->isPayable()) {
                throw new InvalidArgumentException('This repair agreement is not ready for payment.');
            }

            $repair = $conversation->repair;
            $amount = round(max(0, (float) $conversation->final_total - (float) $repair->amount_paid), 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException('There is no repair balance available for payment.');
            }

            $payment = $repair->payments()->create([
                'repair_id' => $repair->id,
                'source' => 'repair',
                'gateway' => $gateway,
                'amount' => $amount,
                'currency' => 'cad',
                'status' => 'pending',
                'checkout_data' => [
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation->id,
                    'proposal_version' => $conversation->accepted_proposal_version,
                    'customer' => [
                        'full_name' => $customer->full_name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ],
                ],
            ]);

            $this->recordEvent($conversation, RepairMessage::SENDER_CUSTOMER, $customer->user_id, 'payment_created', [
                'payment_id' => $payment->id,
                'amount' => $amount,
            ]);

            return $payment;
        });
    }

    public function removePartOption(RepairPartOption $option, User $actor): RepairPartOption
    {
        return DB::transaction(function () use ($option, $actor): RepairPartOption {
            $option = RepairPartOption::query()->with('group.conversation')->lockForUpdate()->findOrFail($option->id);
            $conversation = $option->group->conversation;
            $this->ensureProposalEditable($conversation);

            if (! $option->is_active || ! $option->isPartOption()) {
                throw new InvalidArgumentException('Only active MobileSentrix part options can be removed.');
            }

            $hadSelection = $option->selections()->exists();
            $wasPresented = $conversation->status === RepairConversation::STATUS_AWAITING_CUSTOMER;

            $option->update(['is_active' => false]);
            $option->selections()->delete();
            $this->invalidateCurrentProposal($conversation);
            $this->recalculateTotals($conversation);
            $conversation->save();

            if ($hadSelection && $wasPresented) {
                $conversation->messages()->create([
                    'sender_type' => RepairMessage::SENDER_SYSTEM,
                    'sender_id' => null,
                    'message_type' => 'part_option_removed',
                    'message' => 'A previously selected part option was removed. Please choose another option for this group.',
                    'is_internal' => false,
                ]);
                $conversation->update(['last_message_at' => now()]);
            }

            $this->recordEvent($conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'part_option_removed', [
                'option_id' => $option->id,
            ]);

            return $option->fresh();
        });
    }

    public function markPrimaryPartOption(RepairPartOption $option, User $actor): RepairPartOption
    {
        return DB::transaction(function () use ($option, $actor): RepairPartOption {
            $option = RepairPartOption::query()->with('group.conversation')->lockForUpdate()->findOrFail($option->id);
            $conversation = $option->group->conversation;
            $this->ensureProposalEditable($conversation);

            if (! $option->is_active || ! $option->isPartOption()) {
                throw new InvalidArgumentException('Only active MobileSentrix part options can be recommended.');
            }

            RepairPartOption::query()
                ->where('repair_part_group_id', $option->repair_part_group_id)
                ->where('option_type', RepairPartOption::TYPE_PART)
                ->where('is_system_option', false)
                ->update(['is_primary' => false]);
            $option->update(['is_primary' => true]);
            $this->invalidateCurrentProposal($conversation);
            $conversation->save();

            $this->recordEvent($conversation, RepairMessage::SENDER_ADMIN, $actor->id, 'part_option_marked_primary', [
                'option_id' => $option->id,
            ]);

            return $option->fresh();
        });
    }

    private function recalculateTotals(RepairConversation $conversation): void
    {
        $partsSubtotal = RepairPartSelection::query()
            ->whereHas('group', fn ($query) => $query
                ->where('repair_conversation_id', $conversation->id)
                ->where('is_active', true))
            ->whereHas('option', fn ($query) => $query->where('is_active', true))
            ->with('option')
            ->get()
            ->sum(fn (RepairPartSelection $selection): float => (float) $selection->option?->price_snapshot);

        $subtotal = (float) $conversation->labour_amount
            + (float) $conversation->diagnostic_fee
            + (float) $conversation->service_fee
            + $partsSubtotal;

        $conversation->selected_parts_subtotal = round($partsSubtotal, 2);
        $conversation->final_total = round(max(0, $subtotal - (float) $conversation->discount_amount) + (float) $conversation->tax_amount, 2);
    }

    private function assertRequiredSelectionsComplete(RepairConversation $conversation, Customer $customer): void
    {
        $missing = RepairPartGroup::query()
            ->where('repair_conversation_id', $conversation->id)
            ->where('is_active', true)
            ->whereDoesntHave('selections', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->whereHas('option', fn ($query) => $query->where('is_active', true)))
            ->exists();

        if ($missing) {
            throw new InvalidArgumentException('Please select one option for each part group before accepting the proposal.');
        }
    }

    private function ensureCustomerSuppliedOptions(RepairConversation $conversation): void
    {
        $conversation->activePartGroups()->get()->each(function (RepairPartGroup $group): void {
            $this->ensureCustomerSuppliedOption($group);
        });
    }

    private function ensureCustomerSuppliedOption(RepairPartGroup $group): RepairPartOption
    {
        $existing = $group->options()
            ->where('system_option_key', RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED)
            ->first();

        if (! $existing) {
            $existing = $group->options()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->whereIn(DB::raw('LOWER(name_snapshot)'), ['i have the parts', 'come with parts'])
                ->first();
        }

        if ($existing) {
            $existing->forceFill($this->customerSuppliedOptionAttributes($group))->save();
            $this->deactivateDuplicateCustomerSuppliedOptions($group, $existing);

            return $existing->fresh();
        }

        return $group->options()->create($this->customerSuppliedOptionAttributes($group));
    }

    private function customerSuppliedOptionAttributes(RepairPartGroup $group): array
    {
        return [
            'option_type' => RepairPartOption::TYPE_CUSTOMER_SUPPLIED,
            'is_system_option' => true,
            'system_option_key' => RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED,
            'source_type' => null,
            'source_id' => null,
            'sku_snapshot' => null,
            'name_snapshot' => RepairPartOption::CUSTOMER_SUPPLIED_LABEL,
            'model_snapshot' => null,
            'image_url_snapshot' => null,
            'description_snapshot' => 'Customer will provide this required part.',
            'quality_label' => null,
            'price_snapshot' => 0,
            'is_primary' => false,
            'sort_order' => 0,
            'proposal_version' => max(1, (int) $group->proposal_version),
            'is_active' => true,
        ];
    }

    private function deactivateDuplicateCustomerSuppliedOptions(RepairPartGroup $group, RepairPartOption $keptOption): void
    {
        RepairPartOption::query()
            ->where('repair_part_group_id', $group->id)
            ->whereKeyNot($keptOption->id)
            ->where(function ($query): void {
                $query->where('system_option_key', RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED)
                    ->orWhere(function ($query): void {
                        $query->whereNull('source_type')
                            ->whereNull('source_id')
                            ->whereIn(DB::raw('LOWER(name_snapshot)'), ['i have the parts', 'come with parts']);
                    });
            })
            ->update([
                'is_active' => false,
                'is_primary' => false,
                'system_option_key' => null,
            ]);
    }

    private function recordEvent(RepairConversation $conversation, ?string $actorType, ?int $actorId, string $eventType, array $payload = []): void
    {
        $conversation->events()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    private function invalidateCurrentProposal(RepairConversation $conversation): void
    {
        if (in_array($conversation->status, [RepairConversation::STATUS_PAID, RepairConversation::STATUS_CLOSED], true)) {
            return;
        }

        $conversation->status = RepairConversation::STATUS_OPEN;
        $conversation->accepted_proposal_version = null;
        $conversation->agreed_at = null;
    }

    private function ensureProposalEditable(RepairConversation $conversation): void
    {
        if (in_array($conversation->status, [
            RepairConversation::STATUS_PAYMENT_PENDING,
            RepairConversation::STATUS_PAID,
            RepairConversation::STATUS_CLOSED,
        ], true)) {
            throw new InvalidArgumentException('This repair proposal is locked and can no longer be changed.');
        }
    }

    private function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }
}
