<?php

namespace Database\Seeders;

use App\Models\MobileSentrixBuffer;
use App\Models\MobileSentrixOrder;
use App\Models\User;
use App\Services\MobileSentrix\MobileSentrixProcurementService;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Turns part of the procurement buffer into a real procurement order.
 *
 * The buffer itself is not seeded directly: it is produced by PaymentFinalizer when the shop and
 * repair seeders settle their payments, which is the only way requirements are ever created.
 * This seeder only exercises the admin side, leaving a mix of processed, partially processed and
 * still-pending requirements to test against.
 */
class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();

        if (! $admin) {
            $this->command?->warn('ProcurementSeeder skipped: no admin user.');

            return;
        }

        $pending = MobileSentrixBuffer::query()->pending()->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->command?->warn('ProcurementSeeder skipped: procurement buffer is empty.');

            return;
        }

        $service = app(MobileSentrixProcurementService::class);

        // The Procurement Cart screen needs pending work to show, so the last requirement is
        // always left untouched and only part of a multi-unit requirement is ordered.
        $workable = $pending->count() > 1 ? $pending->slice(0, $pending->count() - 1) : collect();

        if ($workable->isEmpty()) {
            $this->command?->warn('ProcurementSeeder: only one requirement pending, leaving it for manual testing.');

            return;
        }

        $quantities = [];
        $partial = $workable->firstWhere(fn (MobileSentrixBuffer $b): bool => $b->remainingQuantity() > 1);

        if ($partial) {
            // Leaves this requirement Pending with a remainder, covering partial processing.
            $quantities[$partial->id] = (int) floor($partial->remainingQuantity() / 2);
        }

        $full = $workable->first(fn (MobileSentrixBuffer $b): bool => ! isset($quantities[$b->id]));

        if ($full) {
            $quantities[$full->id] = $full->remainingQuantity();
        }

        try {
            $order = $service->createProcurementOrder($quantities, $admin);
        } catch (InvalidArgumentException $exception) {
            $this->command?->warn('ProcurementSeeder skipped: '.$exception->getMessage());

            return;
        }

        // Record the manual supplier order the admin would have placed on MobileSentrix.
        $service->updateOrder($order, [
            'supplier_order_number' => 'MS-SUPPLIER-'.now()->format('Ymd').'-001',
            'tax' => 0,
            'shipping_cost' => 24.95,
            'shipping_discount_amount' => 0,
            'payment_amount' => 0,
            'delivery_carrier' => 'Purolator',
            'tracking_number' => 'PUR'.random_int(100000000, 999999999),
            'admin_notes' => 'Placed manually on the MobileSentrix portal.',
        ], $admin);

        // A second order that has already arrived, so the Received state has coverage too. It
        // draws only from the partially processed requirement, never the reserved last one.
        $stillPending = MobileSentrixBuffer::query()
            ->pending()
            ->whereKey(array_keys($quantities))
            ->orderBy('id')
            ->first();

        if ($stillPending && $stillPending->remainingQuantity() > 0) {
            try {
                $received = $service->createProcurementOrder(
                    [$stillPending->id => 1],
                    $admin,
                );

                $service->updateOrder($received, [
                    'supplier_order_number' => 'MS-SUPPLIER-'.now()->subDays(9)->format('Ymd').'-004',
                    'tax' => 0,
                    'shipping_cost' => 18.50,
                    'shipping_discount_amount' => 0,
                    'payment_amount' => 0,
                    'delivery_carrier' => 'UPS',
                    'tracking_number' => '1Z'.strtoupper(bin2hex(random_bytes(6))),
                ], $admin);

                $service->transitionStatus($received, MobileSentrixOrder::STATUS_RECEIVED, $admin);
            } catch (InvalidArgumentException) {
                // Nothing left to order; the first order already consumed the buffer.
            }
        }
    }
}
