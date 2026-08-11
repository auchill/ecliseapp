<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\Part;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Models\RepairPartGroup;
use App\Models\RepairPartOption;
use App\Models\RepairPartSelection;
use App\Models\User;
use App\Services\PaymentFinalizer;
use App\Services\Payments\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Repair scenarios across the negotiation and payment lifecycle.
 *
 * Includes a paid repair whose selected MobileSentrix part enters procurement, and a paid
 * repair where the customer supplies the part so that nothing is procured — the two cases the
 * buffer rules must distinguish.
 */
class RepairScenarioSeeder extends Seeder
{
    public function run(): void
    {
        Mail::fake();

        $parts = Part::query()
            ->where('is_api_item', true)
            ->whereNotNull('sku')
            ->where('api_price', '>', 0)
            ->limit(3)
            ->get();

        if ($parts->count() < 2) {
            $this->command?->warn('RepairScenarioSeeder skipped: not enough MobileSentrix parts available.');

            return;
        }

        // 1. Freshly booked, awaiting diagnosis.
        $this->repair('amara@example.com', 'Phone', 'Apple', 'iPhone 15 Pro', 'Cracked screen after a drop.', [
            'status' => 'diagnosis_in_progress',
            'repair_status' => 'diagnosis_in_progress',
            'payment_status' => 'unpaid',
        ]);

        // 2. Proposal sent with alternatives, customer has not chosen yet.
        $pending = $this->repair('daniel@example.com', 'Phone', 'Samsung', 'Galaxy S24', 'Battery drains within two hours.', [
            'status' => 'awaiting_customer_approval',
            'repair_status' => 'awaiting_customer_approval',
            'payment_status' => 'unpaid',
            'subtotal' => 180.00,
            'tax_amount' => 23.40,
            'total_amount' => 203.40,
            'balance_due' => 203.40,
            'repair_total' => 203.40,
        ]);
        $group = $this->proposal($pending, 'Battery', $parts[0], $parts[1]);

        // 3. Accepted proposal, paid in full, MobileSentrix part selected.
        //    This is the case that must create a procurement requirement.
        $paid = $this->repair('priya@example.com', 'Phone', 'Apple', 'iPhone 14', 'Screen replacement approved.', [
            'status' => 'awaiting_customer_payment',
            'repair_status' => 'awaiting_customer_payment',
            'payment_status' => 'unpaid',
            'subtotal' => 220.00,
            'tax_amount' => 28.60,
            'total_amount' => 248.60,
            'balance_due' => 248.60,
            'repair_total' => 248.60,
        ]);
        $paidGroup = $this->proposal($paid, 'Display', $parts[0], $parts[1]);
        $this->select($paidGroup, $paidGroup->options()->where('is_system_option', false)->first());
        $this->settleRepair($paid, 248.60, 'stripe');

        // 4. Paid repair where the customer supplied the part — nothing may be procured.
        $supplied = $this->repair('marcus@example.com', 'Phone', 'Google', 'Pixel 8', 'Customer supplied replacement screen.', [
            'status' => 'awaiting_customer_payment',
            'repair_status' => 'awaiting_customer_payment',
            'payment_status' => 'unpaid',
            'subtotal' => 90.00,
            'tax_amount' => 11.70,
            'total_amount' => 101.70,
            'balance_due' => 101.70,
            'repair_total' => 101.70,
        ]);
        $suppliedGroup = $this->proposal($supplied, 'Display', $parts[0], $parts[1]);
        $this->select($suppliedGroup, $suppliedGroup->options()->where('is_system_option', true)->first());
        $this->settleRepair($supplied, 101.70, 'cash');

        unset($group);
    }

    private function repair(string $email, string $type, string $brand, string $model, string $issue, array $overrides): Repair
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $customer = Customer::forUser($user);

        $repair = Repair::query()->create(array_merge([
            'customer_id' => $customer->id,
            'repair_number' => 'ECL-REP-'.now()->year.'-'.str_pad((string) (Repair::query()->count() + 1), 7, '0', STR_PAD_LEFT),
            'device_type' => $type,
            'device_type_id' => DeviceType::query()->where('name', $type)->value('id'),
            'device_brand' => $brand,
            'device_model' => $model,
            'issue_category' => 'Screen replacement',
            'issue_category_id' => IssueCategory::query()->value('id'),
            'issue_description' => $issue,
            'preferred_appointment_date' => now()->addDays(random_int(1, 6))->toDateString(),
            'preferred_appointment_time' => '10:30',
            'terms_accepted' => true,
            'fulfillment_method' => 'pickup',
            'pickup_or_shipping_option' => 'pickup',
            'currency' => 'cad',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'repair_total' => 0,
        ], $overrides));

        $repair->statusUpdates()->create([
            'status' => 'booking_created',
            'note' => 'Repair request received.',
            'is_customer_visible' => true,
        ]);

        return $repair->fresh();
    }

    private function proposal(Repair $repair, string $title, Part $primary, Part $alternative): RepairPartGroup
    {
        $conversation = RepairConversation::query()->create([
            'repair_id' => $repair->id,
            'customer_id' => $repair->customer_id,
            'status' => RepairConversation::STATUS_AWAITING_CUSTOMER,
            'proposal_version' => 1,
            'labour_amount' => 80.00,
            'tax_amount' => round((float) $repair->tax_amount, 2),
            'final_total' => (float) $repair->total_amount,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_type' => 'admin',
            'message_type' => 'text',
            'message' => 'We inspected your device and prepared a repair proposal. Please choose a part option.',
            'is_internal' => false,
        ]);

        $group = RepairPartGroup::query()->create([
            'repair_conversation_id' => $conversation->id,
            'title' => $title,
            'description' => 'Choose the replacement part quality that suits you.',
            'is_required' => true,
            'sort_order' => 1,
            'proposal_version' => 1,
            'is_active' => true,
        ]);

        foreach ([[$primary, 'OEM', true], [$alternative, 'Aftermarket', false]] as [$part, $quality, $isPrimary]) {
            RepairPartOption::query()->create([
                'repair_part_group_id' => $group->id,
                'option_type' => RepairPartOption::TYPE_PART,
                'is_system_option' => false,
                'source_type' => Part::class,
                'source_id' => $part->id,
                'sku_snapshot' => $part->sku,
                'name_snapshot' => $part->name,
                'quality_label' => $quality,
                // The customer-facing price is the marked-up figure, not MobileSentrix cost.
                'price_snapshot' => round((float) ($part->api_price ?? 0) * 1.25, 2),
                'is_primary' => $isPrimary,
                'sort_order' => $isPrimary ? 1 : 2,
                'proposal_version' => 1,
                'is_active' => true,
            ]);
        }

        // Every group offers the customer-supplied option, which must never be procured.
        RepairPartOption::query()->create([
            'repair_part_group_id' => $group->id,
            'option_type' => RepairPartOption::TYPE_CUSTOMER_SUPPLIED,
            'is_system_option' => true,
            'system_option_key' => RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED,
            'name_snapshot' => RepairPartOption::CUSTOMER_SUPPLIED_LABEL,
            'price_snapshot' => 0,
            'sort_order' => 0,
            'proposal_version' => 1,
            'is_active' => true,
        ]);

        return $group->fresh();
    }

    private function select(RepairPartGroup $group, ?RepairPartOption $option): void
    {
        if (! $option) {
            return;
        }

        RepairPartSelection::query()->create([
            'repair_part_group_id' => $group->id,
            'repair_part_option_id' => $option->id,
            'customer_id' => $group->conversation->customer_id,
            'selected_at' => now(),
        ]);

        $group->conversation->update([
            'status' => RepairConversation::STATUS_PAYMENT_PENDING,
            'accepted_proposal_version' => 1,
            'agreed_at' => now(),
        ]);
    }

    private function settleRepair(Repair $repair, float $amount, string $gateway): void
    {
        $invoice = app(InvoiceService::class)->createRepairFinalInvoice($repair);

        $payment = $repair->payments()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $repair->customer_id,
            'repair_id' => $repair->id,
            'source' => 'repair',
            'gateway' => $gateway,
            'method' => $gateway,
            'provider' => $gateway === 'stripe' ? 'stripe' : 'manual',
            'purpose' => 'balance',
            'amount' => $amount,
            'currency' => 'cad',
            'status' => 'pending',
        ]);

        // A settled manual payment must record the staff member who took it.
        $adminId = in_array($gateway, ['stripe', 'paypal'], true)
            ? null
            : User::query()->where('role', 'admin')->value('id');

        app(PaymentFinalizer::class)->markPaid($payment, array_filter([
            'gateway_reference_id' => strtoupper($gateway).'-REP-SEED-'.$repair->id,
            'gateway_payment_id' => strtoupper($gateway).'-REP-SEED-'.$repair->id,
            'paid_at' => now()->subDays(random_int(1, 8)),
            'received_by' => $adminId,
        ], fn ($value): bool => $value !== null));
    }
}
